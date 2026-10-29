<?php
/**
 * REST API Endpoint Handler
 * Exposes /wp-json/wp-ai-chatbot/v1/chat and handles lead recording & notifications.
 *
 * @package WP_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_AI_Chatbot_API_Handler {

    /**
     * Namespace for REST API
     */
    const NAMESPACE = 'wp-ai-chatbot/v1';

    public function __construct() {
        add_action('wp_ai_chatbot_lead_captured', [$this, 'save_and_notify_lead'], 10, 3);
        add_action('wp_ai_chatbot_human_handoff_triggered', [$this, 'notify_team_webhook'], 10, 3);
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route(self::NAMESPACE, '/chat', [
            'methods'             => WP_REST_Server::CREATABLE, // POST
            'callback'            => [$this, 'handle_chat_request'],
            'permission_callback' => [$this, 'check_permissions'],
            'args'                => [
                'message' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return !empty(trim($param)) && mb_strlen($param) <= 3000;
                    },
                ],
                'history' => [
                    'required'          => false,
                    'type'              => 'array',
                    'default'           => [],
                    'sanitize_callback' => [$this, 'sanitize_history'],
                ],
                'page_title' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'page_url' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/test-connection', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_test_connection'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route(self::NAMESPACE, '/clear-leads', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_clear_leads'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route(self::NAMESPACE, '/add-to-cart', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_add_to_cart'],
            'permission_callback' => '__return_true',
            'args'                => [
                'product_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'quantity'   => [
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Check permission / rate limiting
     */
    public function check_permissions(WP_REST_Request $request) {
        $settings = get_option('wp_ai_chatbot_settings', []);

        // Rate limiting check
        $enable_rate_limit = isset($settings['enable_rate_limit']) ? (bool)$settings['enable_rate_limit'] : true;
        if ($enable_rate_limit) {
            $ip = $this->get_client_ip();
            $transient_key = 'wp_ai_chat_rl_' . md5($ip);
            $limit = !empty($settings['rate_limit_count']) ? intval($settings['rate_limit_count']) : 20;
            $window = !empty($settings['rate_limit_window']) ? intval($settings['rate_limit_window']) : 600;

            $current_hits = (int) get_transient($transient_key);
            if ($current_hits >= $limit) {
                return new WP_Error(
                    'rate_limit_exceeded',
                    __('You have sent too many messages. Please wait a few minutes before trying again.', 'wp-ai-chatbot'),
                    ['status' => 429]
                );
            }
        }

        // Nonce verification if supplied
        $nonce = $request->get_header('x_wp_nonce');
        if (!empty($nonce) && !wp_verify_nonce($nonce, 'wp_rest')) {
            if (is_user_logged_in()) {
                return new WP_Error('rest_forbidden', __('Invalid security token.', 'wp-ai-chatbot'), ['status' => 403]);
            }
        }

        return true;
    }

    /**
     * Handle chat POST request
     */
    public function handle_chat_request(WP_REST_Request $request) {
        $message = trim($request->get_param('message'));
        $history = $request->get_param('history');
        $page_context = [
            'page_title' => sanitize_text_field($request->get_param('page_title') ?? ''),
            'page_url'   => esc_url_raw($request->get_param('page_url') ?? ''),
        ];

        if (empty($message)) {
            return new WP_Error('missing_message', __('Message cannot be empty.', 'wp-ai-chatbot'), ['status' => 400]);
        }

        // Track rate limiting counter
        $this->increment_rate_limit();

        $settings = get_option('wp_ai_chatbot_settings', []);
        $llm_service = new WP_AI_Chatbot_LLM_Service($settings);

        try {
            $result = $llm_service->generate_response($message, $history, $page_context);
            $reply = is_array($result) ? ($result['reply'] ?? '') : $result;

            $handoff_payload = null;
            if (is_array($result) && !empty($result['handoff_triggered']) && !empty($settings['enable_human_handoff'])) {
                $handoff_payload = $this->build_handoff_payload($settings);
            }

            return rest_ensure_response([
                'success'        => true,
                'reply'          => $reply,
                'handoff'        => $handoff_payload,
                'products'       => $result['products'] ?? [],
                'order_tracking' => $result['order_tracking'] ?? null,
            ]);
        } catch (Exception $e) {
            return new WP_Error('ai_service_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Handle test connection request from Admin dashboard
     */
    public function handle_test_connection(WP_REST_Request $request) {
        $settings = get_option('wp_ai_chatbot_settings', []);
        $llm_service = new WP_AI_Chatbot_LLM_Service($settings);

        try {
            $test_message = 'Respond with exactly: "Connection successful! WP AI Chatbot is ready."';
            $result = $llm_service->generate_response($test_message, []);
            $reply = is_array($result) ? ($result['reply'] ?? '') : $result;

            return rest_ensure_response([
                'success' => true,
                'reply'   => $reply,
            ]);
        } catch (Exception $e) {
            return new WP_Error('connection_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    /**
     * Build the human handoff action card payload
     */
    private function build_handoff_payload($settings) {
        $channel = $settings['handoff_channel'] ?? 'calendly';
        $target = trim($settings['handoff_target'] ?? '');
        $label = !empty($settings['handoff_button_label']) ? $settings['handoff_button_label'] : '';

        $url = '';
        switch ($channel) {
            case 'calendly':
                $url = !empty($target) ? esc_url_raw($target) : '';
                if (empty($label)) $label = __('📅 Schedule a Discovery Call', 'wp-ai-chatbot');
                break;
            case 'telegram':
                $username = ltrim(trim($target), '@');
                $url = !empty($username) ? 'https://t.me/' . rawurlencode($username) : '';
                if (empty($label)) $label = __('💬 Message Us on Telegram', 'wp-ai-chatbot');
                break;
            case 'whatsapp':
                $phone = preg_replace('/[^0-9]/', '', $target);
                $url = !empty($phone) ? 'https://wa.me/' . $phone : '';
                if (empty($label)) $label = __('💬 Chat on WhatsApp', 'wp-ai-chatbot');
                break;
            case 'sms':
                $phone = preg_replace('/[^0-9+]/', '', $target);
                $url = !empty($phone) ? 'sms:' . $phone : '';
                if (empty($label)) $label = __('📱 Text Support', 'wp-ai-chatbot');
                break;
            case 'phone':
                $phone = preg_replace('/[^0-9+]/', '', $target);
                $url = !empty($phone) ? 'tel:' . $phone : '';
                if (empty($label)) $label = __('📞 Call Support Now', 'wp-ai-chatbot');
                break;
            case 'custom':
                $url = esc_url_raw($target);
                if (empty($label)) $label = __('🔗 Open Support Ticket / Helpdesk', 'wp-ai-chatbot');
                break;
            case 'email':
                $admin_email = !empty($settings['lead_notification_email']) ? $settings['lead_notification_email'] : get_option('admin_email');
                $url = 'mailto:' . antispambot($admin_email) . '?subject=' . rawurlencode('Support Request via Website Chatbot');
                if (empty($label)) $label = __('📩 Email Support Team', 'wp-ai-chatbot');
                break;
        }

        if (empty($url)) {
            return null;
        }

        return [
            'enabled' => true,
            'channel' => $channel,
            'url'     => $url,
            'label'   => $label,
        ];
    }

    /**
     * Send instant notification to Slack or Discord webhook when a human is requested
     */
    public function notify_team_webhook($message, $history, $page_context) {
        $settings = get_option('wp_ai_chatbot_settings', []);
        $webhook_url = trim($settings['slack_discord_webhook'] ?? '');
        if (empty($webhook_url) || !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            return;
        }

        $site_name = get_bloginfo('name');
        $page_info = !empty($page_context['page_title']) ? $page_context['page_title'] . " (" . ($page_context['page_url'] ?? '') . ")" : home_url();

        $is_discord = strpos($webhook_url, 'discord.com') !== false;

        if ($is_discord) {
            $payload = [
                'content' => "🚨 **Human Handoff Alert** on **{$site_name}**!\nA website visitor requested human assistance.",
                'embeds'  => [
                    [
                        'title'       => 'Visitor Inquiry Details',
                        'description' => "Last Visitor Message:\n> \"{$message}\"\n\nPage Viewed:\n{$page_info}",
                        'color'       => 15158332,
                        'footer'      => ['text' => 'WP AI Chatbot • Real-Time Alert']
                    ]
                ]
            ];
        } else {
            // Slack format
            $payload = [
                'text' => "🚨 *Human Handoff Alert* on *{$site_name}*!\nA visitor requested human support.\n*Page:* {$page_info}\n*Visitor Query:* \"{$message}\"",
            ];
        }

        wp_remote_post($webhook_url, [
            'timeout' => 5,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'    => wp_json_encode($payload),
        ]);
    }

    /**
     * Handle clearing captured leads
     */
    public function handle_clear_leads(WP_REST_Request $request) {
        update_option('wp_ai_chatbot_leads', []);
        return rest_ensure_response(['success' => true]);
    }

    /**
     * Save captured lead and email site admin
     */
    public function save_and_notify_lead($lead, $message, $history) {
        $settings = get_option('wp_ai_chatbot_settings', []);

        // 1. Store in WordPress database (wp_options)
        $leads = get_option('wp_ai_chatbot_leads', []);
        if (!is_array($leads)) {
            $leads = [];
        }

        array_unshift($leads, $lead);
        $leads = array_slice($leads, 0, 150);
        update_option('wp_ai_chatbot_leads', $leads);

        // 2. Email notification to administrator
        $to = !empty($settings['lead_notification_email']) ? trim($settings['lead_notification_email']) : get_option('admin_email');
        if (is_email($to)) {
            $site_name = get_bloginfo('name');
            $lead_name = !empty($lead['name']) ? $lead['name'] : 'Website Visitor';
            $subject = sprintf('[New Lead] %s via %s Chatbot', $lead_name, $site_name);

            $body = "A new client inquiry/lead was captured by the AI Chatbot on {$site_name}!\n\n";
            $body .= "Name: " . ($lead['name'] ?: 'Not provided') . "\n";
            $body .= "Email: " . ($lead['email'] ?: 'Not provided') . "\n";
            if (!empty($lead['phone'])) $body .= "Phone: " . $lead['phone'] . "\n";
            if (!empty($lead['notes'])) $body .= "Project / Inquiry: " . $lead['notes'] . "\n";
            if (!empty($lead['page_title'])) $body .= "Page Viewed: " . $lead['page_title'] . " (" . $lead['page_url'] . ")\n";
            $body .= "Date: " . current_time('F j, Y, g:i a') . "\n\n";

            $body .= "--- Recent Conversation Excerpt ---\n";
            if (!empty($history) && is_array($history)) {
                foreach (array_slice($history, -6) as $turn) {
                    $body .= ucfirst($turn['role'] ?? 'User') . ": " . ($turn['content'] ?? '') . "\n";
                }
            }
            $body .= "User: " . $message . "\n\n";
            $body .= "You can view all leads directly in WordPress under Settings > AI Chatbot.\n";

            $headers = ['Content-Type: text/plain; charset=UTF-8'];
            if (!empty($lead['email']) && is_email($lead['email'])) {
                $headers[] = 'Reply-To: ' . $lead_name . ' <' . $lead['email'] . '>';
            }

            wp_mail($to, $subject, $body, $headers);
        }
    }

    /**
     * Sanitize history array
     */
    public function sanitize_history($history) {
        if (!is_array($history)) {
            return [];
        }

        $clean = [];
        $history_slice = array_slice($history, -10);

        foreach ($history_slice as $item) {
            if (!is_array($item) || empty($item['content'])) {
                continue;
            }
            $clean[] = [
                'role'    => in_array($item['role'] ?? '', ['assistant', 'bot', 'model']) ? 'assistant' : 'user',
                'content' => sanitize_text_field(mb_substr($item['content'], 0, 1500)),
            ];
        }

        return $clean;
    }

    /**
     * Increment rate limit transient
     */
    private function increment_rate_limit() {
        $settings = get_option('wp_ai_chatbot_settings', []);
        if (isset($settings['enable_rate_limit']) && !(bool)$settings['enable_rate_limit']) {
            return;
        }

        $ip = $this->get_client_ip();
        $transient_key = 'wp_ai_chat_rl_' . md5($ip);
        $window = !empty($settings['rate_limit_window']) ? intval($settings['rate_limit_window']) : 600;

        $current = (int) get_transient($transient_key);
        set_transient($transient_key, $current + 1, $window);
    }

    /**
     * Get client IP reliably behind proxies/CDNs
     */
    private function get_client_ip() {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $raw_ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
                $ips = explode(',', $raw_ip);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    /**
     * Handle 1-click AJAX Add to Cart for WooCommerce (DigitalGenius style)
     */
    public function handle_add_to_cart(WP_REST_Request $request) {
        if (!class_exists('WooCommerce') || !function_exists('WC')) {
            return new WP_Error('wc_unavailable', __('WooCommerce is not active on this site.', 'wp-ai-chatbot'), ['status' => 400]);
        }

        $product_id = absint($request->get_param('product_id'));
        $quantity = max(1, absint($request->get_param('quantity') ?? 1));

        if (empty($product_id)) {
            return new WP_Error('invalid_product', __('Invalid product ID.', 'wp-ai-chatbot'), ['status' => 400]);
        }

        // Initialize session, customer, and cart if not set (REST context)
        if (null === WC()->session) {
            $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');
            WC()->session = new $session_class();
            WC()->session->init();
        }
        if (null === WC()->customer) {
            WC()->customer = new WC_Customer(get_current_user_id(), true);
        }
        if (null === WC()->cart) {
            WC()->cart = new WC_Cart();
        }

        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);

        if ($cart_item_key) {
            return rest_ensure_response([
                'success'      => true,
                'product_id'   => $product_id,
                'cart_count'   => WC()->cart->get_cart_contents_count(),
                'cart_total'   => html_entity_decode(wp_strip_all_tags(WC()->cart->get_cart_total())),
                'cart_url'     => wc_get_cart_url(),
                'checkout_url' => wc_get_checkout_url(),
                'message'      => __('Added to cart!', 'wp-ai-chatbot'),
            ]);
        } else {
            return new WP_Error('add_failed', __('Could not add this product to cart. Please check available options.', 'wp-ai-chatbot'), ['status' => 400]);
        }
    }
}
