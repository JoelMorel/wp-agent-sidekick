<?php
/**
 * LLM Service Handler
 * Handles communication with AI providers (Gemini, OpenAI, Groq, n8n webhook),
 * dynamic site context/knowledge, lead capture, and WooCommerce store integration.
 *
 * @package WP_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_AI_Chatbot_LLM_Service {

    /**
     * Settings array
     *
     * @var array
     */
    private $settings;

    public function __construct($settings = []) {
        $this->settings = wp_parse_args($settings, [
            'provider'                => 'gemini',
            'gemini_api_key'          => '',
            'gemini_model'            => 'auto',
            'openai_api_key'          => '',
            'openai_model'            => 'gpt-4o-mini',
            'groq_api_key'            => '',
            'groq_model'              => 'llama-3.3-70b-versatile',
            'n8n_webhook_url'         => '',
            'system_prompt'           => 'You are a helpful and polite website customer support assistant.',
            'knowledge_base'          => '',
            'auto_learn_content'      => 1,
            'enable_lead_capture'     => 1,
            'lead_notification_email' => get_option('admin_email'),
            'enable_wc_order_lookup'  => 1,
            'enable_wc_product_search'=> 1,
            'enable_human_handoff'    => 1,
            'handoff_channel'         => 'calendly',
            'handoff_target'          => '',
            'handoff_button_label'    => 'Connect with a Team Member',
            'slack_discord_webhook'   => '',
        ]);
    }

    /**
     * Build the enhanced system instructions combining persona, page context, business knowledge, and store details
     *
     * @param string $message Current user message
     * @param array $page_context Page title and URL
     * @return string
     */
    public function build_full_system_prompt($message = '', $page_context = []) {
        $prompt = trim($this->settings['system_prompt']);
        $prompt .= "\n\nYou are representing the website: " . get_bloginfo('name') . " (" . home_url() . ").";

        // 1. Current Page Context (Universal)
        if (!empty($page_context['page_title']) || !empty($page_context['page_url'])) {
            $prompt .= "\n\n--- CURRENT PAGE THE VISITOR IS VIEWING ---\n";
            if (!empty($page_context['page_title'])) {
                $prompt .= "Page Title: " . sanitize_text_field($page_context['page_title']) . "\n";
            }
            if (!empty($page_context['page_url'])) {
                $prompt .= "Page URL: " . esc_url($page_context['page_url']) . "\n";
            }
            $prompt .= "Use this context if the visitor asks about 'this service', 'this product', 'pricing on this page', or mentions where they are currently looking.\n";
            $prompt .= "-------------------------------------------";
        }

        // 2. Static Business Knowledge Base & FAQs
        if (!empty($this->settings['knowledge_base'])) {
            $prompt .= "\n\n--- OFFICIAL WEBSITE KNOWLEDGE & FAQs ---\n";
            $prompt .= trim($this->settings['knowledge_base']);
            $prompt .= "\n----------------------------------------";
        }

        // 3. Dynamic Site Content / Product Search (Native Mini-RAG)
        if (!empty($this->settings['auto_learn_content']) && !empty($message)) {
            $site_context = $this->retrieve_site_context($message);
            if (!empty($site_context)) {
                $prompt .= "\n\n--- RELEVANT CONTENT FOUND ON THIS WEBSITE ---\n";
                $prompt .= $site_context;
                $prompt .= "\nUse this information when answering user questions. If relevant, provide the link so the visitor can view or purchase.";
                $prompt .= "\n---------------------------------------------";
            }
        }

        // 4. WooCommerce Order Status Lookup (E-Commerce)
        if (!empty($this->settings['enable_wc_order_lookup']) && function_exists('wc_get_order') && !empty($message)) {
            $order_context = $this->check_wc_order($message);
            if (!empty($order_context)) {
                $prompt .= "\n\n--- WOOCOMMERCE ORDER LOOKUP RESULT ---\n";
                $prompt .= $order_context . "\n";
                $prompt .= "Politely communicate this order status to the customer. For e-commerce orders, remind them of standard fulfillment and shipping timelines.\n";
                $prompt .= "--------------------------------------";
            }
        }

        // 5. Agency Lead Capture Guidelines
        if (!empty($this->settings['enable_lead_capture'])) {
            $prompt .= "\n\n--- LEAD CAPTURE & INQUIRY INSTRUCTIONS ---\n";
            $prompt .= "If the user expresses interest in booking a consultation, hiring your agency/team, requesting a price quote, asking for custom project work, or asking someone to get in touch with them:\n";
            $prompt .= "1. Warmly offer to connect them with a team specialist or prepare a custom quote.\n";
            $prompt .= "2. Ask for their Name and Email address (and phone number or project details if relevant).\n";
            $prompt .= "3. When the visitor provides their contact details, confirm politely that your team will reach out.\n";
            $prompt .= "4. IMPORTANT: Once you have captured their contact details, append this exact tag at the very end of your response on its own line (it will be saved and hidden automatically from the visitor):\n";
            $prompt .= "[LEAD_CAPTURE: name=\"...\", email=\"...\", phone=\"...\", notes=\"...\"]\n";
            $prompt .= "-------------------------------------------";
        }

        // 6. Human Support Handoff Guidelines
        if (!empty($this->settings['enable_human_handoff'])) {
            $prompt .= "\n\n--- HUMAN SUPPORT HANDOFF INSTRUCTIONS ---\n";
            $prompt .= "If the user explicitly asks to speak to a human, real person, agent, representative, manager, or if they have an issue/question you cannot resolve with available website information:\n";
            $prompt .= "1. Warmly and politely assure them that you will connect them with a human team member.\n";
            $prompt .= "2. At the very end of your response, on a new line, include this exact tag:\n";
            $prompt .= "[HUMAN_HANDOFF]\n";
            $prompt .= "------------------------------------------";
        }

        return $prompt;
    }

    /**
     * Search published WordPress posts/pages/products matching the user query
     *
     * @param string $query
     * @return string
     */
    private function retrieve_site_context($query) {
        $stopwords = ['hello', 'hi', 'hey', 'please', 'can', 'you', 'help', 'me', 'with', 'what', 'where', 'when', 'how', 'is', 'are', 'the', 'a', 'an', 'i', 'want', 'to', 'know', 'tell', 'about'];
        $words = preg_split('/\s+/', strtolower($query));
        $keywords = array_diff($words, $stopwords);
        $search_term = !empty($keywords) ? implode(' ', array_slice($keywords, 0, 5)) : $query;

        $post_types = ['page', 'post'];
        if (post_type_exists('product')) {
            $post_types[] = 'product';
        }

        $args = [
            's'                   => $search_term,
            'post_type'           => $post_types,
            'post_status'         => 'publish',
            'posts_per_page'      => 3,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ];

        $search_query = new WP_Query($args);
        if (!$search_query->have_posts()) {
            return '';
        }

        $results = [];
        while ($search_query->have_posts()) {
            $search_query->the_post();
            $title = get_the_title();
            $permalink = get_permalink();
            $post_type = get_post_type();

            $raw_content = get_the_excerpt();
            if (empty($raw_content)) {
                $raw_content = wp_strip_all_tags(get_the_content());
            }
            $clean_excerpt = wp_trim_words($raw_content, 60, '...');

            // WooCommerce Product Enhancement
            if ($post_type === 'product' && function_exists('wc_get_product')) {
                $product = wc_get_product(get_the_ID());
                if ($product) {
                    $price = wp_strip_all_tags($product->get_price_html());
                    $stock = $product->is_in_stock() ? 'In Stock' : 'Out of Stock';
                    $sku = $product->get_sku();
                    $sku_str = $sku ? " (SKU: {$sku})" : '';

                    $results[] = sprintf(
                        "• Product: %s%s\n  Price: %s | Status: %s | URL: %s\n  %s",
                        $title,
                        $sku_str,
                        $price,
                        $stock,
                        $permalink,
                        $clean_excerpt
                    );
                    continue;
                }
            }

            $results[] = sprintf(
                "• %s (URL: %s):\n  %s",
                $title,
                $permalink,
                $clean_excerpt
            );
        }
        wp_reset_postdata();

        return implode("\n\n", $results);
    }

    /**
     * Check WooCommerce order details if an order number is mentioned
     *
     * @param string $message
     * @return string
     */
    private function check_wc_order($message) {
        if (!function_exists('wc_get_order')) {
            return '';
        }

        if (preg_match('/\b(?:order\s*#?|#)\s*([0-9]{2,8})\b/i', $message, $matches)) {
            $order_id = intval($matches[1]);
            $order = wc_get_order($order_id);

            if ($order) {
                $status = wc_get_order_status_name($order->get_status());
                $date = $order->get_date_created() ? $order->get_date_created()->date('F j, Y') : '';
                $total = html_entity_decode(wp_strip_all_tags($order->get_formatted_order_total()));

                $items = [];
                foreach ($order->get_items() as $item) {
                    $items[] = $item->get_quantity() . 'x ' . $item->get_name();
                }
                $items_str = !empty($items) ? implode(', ', $items) : 'Ordered items';
                $shipping_method = $order->get_shipping_method() ?: 'Standard Shipping';

                // Check customer order notes for tracking details
                $tracking_info = '';
                $notes = $order->get_customer_order_notes();
                if (!empty($notes)) {
                    foreach ($notes as $n) {
                        if (stripos($n->comment_content, 'tracking') !== false || stripos($n->comment_content, 'usps') !== false || stripos($n->comment_content, 'ups') !== false || stripos($n->comment_content, 'fedex') !== false) {
                            $tracking_info = wp_strip_all_tags($n->comment_content);
                            break;
                        }
                    }
                }

                $output = "Order #{$order_id}:\n";
                $output .= "- Current Status: {$status}\n";
                if ($date) $output .= "- Date Placed: {$date}\n";
                $output .= "- Total: {$total}\n";
                $output .= "- Items: {$items_str}\n";
                $output .= "- Shipping: {$shipping_method}\n";
                if ($tracking_info) {
                    $output .= "- Tracking Details: {$tracking_info}\n";
                }
                return $output;
            }
        }

        return '';
    }

    /**
     * Dispatch prompt to configured provider and handle lead capture
     */
    public function generate_response($message, $history = [], $page_context = []) {
        $provider = $this->settings['provider'];

        switch ($provider) {
            case 'gemini':
                $reply = $this->call_gemini($message, $history, $page_context);
                break;
            case 'openai':
                $reply = $this->call_openai($message, $history, $page_context);
                break;
            case 'groq':
                $reply = $this->call_groq($message, $history, $page_context);
                break;
            case 'n8n':
                $reply = $this->call_n8n_webhook($message, $history, $page_context);
                break;
            default:
                throw new Exception(__('Invalid AI provider configured in settings.', 'wp-ai-chatbot'));
        }

        // Process Lead Capture tag if returned by AI
        if (!empty($this->settings['enable_lead_capture']) && preg_match('/\[LEAD_CAPTURE:\s*(.*?)\]/is', $reply, $lead_matches)) {
            $raw_params = $lead_matches[1];
            $lead = [
                'date'       => current_time('mysql'),
                'page_title' => $page_context['page_title'] ?? '',
                'page_url'   => $page_context['page_url'] ?? '',
                'name'       => '',
                'email'      => '',
                'phone'      => '',
                'notes'      => '',
            ];

            if (preg_match('/name\s*=\s*"([^"]+)"/i', $raw_params, $m)) $lead['name'] = sanitize_text_field($m[1]);
            if (preg_match('/email\s*=\s*"([^"]+)"/i', $raw_params, $m)) $lead['email'] = sanitize_email($m[1]);
            if (preg_match('/phone\s*=\s*"([^"]+)"/i', $raw_params, $m)) $lead['phone'] = sanitize_text_field($m[1]);
            if (preg_match('/notes\s*=\s*"([^"]+)"/i', $raw_params, $m)) $lead['notes'] = sanitize_textarea_field($m[1]);

            // Strip the machine tag so user never sees it
            $reply = trim(str_replace($lead_matches[0], '', $reply));

            if (!empty($lead['email']) || !empty($lead['name']) || !empty($lead['phone'])) {
                do_action('wp_ai_chatbot_lead_captured', $lead, $message, $history);
            }
        }

        // Process Human Handoff tag
        $handoff_triggered = false;
        if (!empty($this->settings['enable_human_handoff'])) {
            if (strpos($reply, '[HUMAN_HANDOFF]') !== false) {
                $handoff_triggered = true;
                $reply = trim(str_replace('[HUMAN_HANDOFF]', '', $reply));
            } elseif (preg_match('/\b(speak to a human|talk to a human|real person|human agent|support agent|customer service rep|talk to someone|speak with someone)\b/i', $message)) {
                $handoff_triggered = true;
            }

            if ($handoff_triggered) {
                do_action('wp_ai_chatbot_human_handoff_triggered', $message, $history, $page_context);
            }
        }

        return [
            'reply'             => $reply,
            'handoff_triggered' => $handoff_triggered,
        ];
    }

    /**
     * Call Google Gemini API with smart auto-discovery and self-healing fallback
     */
    private function call_gemini($message, $history, $page_context = []) {
        $api_key = trim($this->settings['gemini_api_key']);
        if (empty($api_key)) {
            throw new Exception(__('Google Gemini API Key is missing. Please add it in WP Admin > Settings > AI Chatbot.', 'wp-ai-chatbot'));
        }

        $preferred_model = !empty($this->settings['gemini_model']) ? trim($this->settings['gemini_model']) : 'auto';
        $preferred_model = preg_replace('#^models/#', '', $preferred_model);

        $contents = [];

        if (!empty($history) && is_array($history)) {
            foreach ($history as $item) {
                if (empty($item['content'])) continue;
                $role = (isset($item['role']) && in_array($item['role'], ['assistant', 'bot', 'model'])) ? 'model' : 'user';
                $contents[] = [
                    'role'  => $role,
                    'parts' => [['text' => (string)$item['content']]]
                ];
            }
        }

        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => (string)$message]]
        ];

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature'     => 0.7,
                'maxOutputTokens' => 1200,
            ]
        ];

        $full_system_prompt = $this->build_full_system_prompt($message, $page_context);
        if (!empty($full_system_prompt)) {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $full_system_prompt]
                ]
            ];
        }

        $model = $this->resolve_active_gemini_model($api_key, $preferred_model);

        try {
            return $this->dispatch_gemini_request($api_key, $model, $payload);
        } catch (Exception $e) {
            $error_message = $e->getMessage();

            if (stripos($error_message, 'no longer available') !== false || stripos($error_message, 'not found') !== false || stripos($error_message, '404') !== false) {
                delete_transient('wp_ai_gemini_active_model_' . md5($api_key));
                $new_model = $this->discover_best_gemini_model($api_key, true);

                if (!empty($new_model) && $new_model !== $model) {
                    return $this->dispatch_gemini_request($api_key, $new_model, $payload);
                }
            }

            throw $e;
        }
    }

    /**
     * Dispatch HTTP POST to Gemini endpoints (v1beta supports system_instruction)
     */
    private function dispatch_gemini_request($api_key, $model, $payload) {
        $endpoints = [
            'v1beta' => "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . rawurlencode($api_key),
            'v1'     => "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . rawurlencode($api_key),
        ];

        $last_error = '';

        foreach ($endpoints as $version => $url) {
            $payload_to_send = $payload;

            if ($version === 'v1' && isset($payload_to_send['system_instruction'])) {
                $sys_text = $payload_to_send['system_instruction']['parts'][0]['text'] ?? '';
                unset($payload_to_send['system_instruction']);

                if (!empty($sys_text) && !empty($payload_to_send['contents'])) {
                    $payload_to_send['contents'][0]['parts'][0]['text'] = "[Instructions: {$sys_text}]\n\n" . $payload_to_send['contents'][0]['parts'][0]['text'];
                }
            }

            $response = wp_remote_post($url, [
                'timeout' => 30,
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body'    => wp_json_encode($payload_to_send),
            ]);

            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                continue;
            }

            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($status === 200 && !empty($data['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($data['candidates'][0]['content']['parts'][0]['text']);
            }

            $msg = $data['error']['message'] ?? '';
            $last_error = !empty($msg) ? $msg : "HTTP status {$status}";

            if ($status === 404 || stripos($msg, 'v1beta') !== false || stripos($msg, 'Developer instruction') !== false) {
                continue;
            }

            if (!empty($data['error']['message'])) {
                throw new Exception(__('Gemini API Error: ', 'wp-ai-chatbot') . $data['error']['message']);
            }
        }

        throw new Exception(__('Gemini API Error: ', 'wp-ai-chatbot') . $last_error);
    }

    /**
     * Resolve active model with caching (expires every 7 days)
     */
    private function resolve_active_gemini_model($api_key, $preferred_model) {
        if ($preferred_model !== 'auto' && !empty($preferred_model)) {
            return $preferred_model;
        }

        $cache_key = 'wp_ai_gemini_active_model_' . md5($api_key);
        $cached = get_transient($cache_key);
        if (!empty($cached)) {
            return $cached;
        }

        $discovered = $this->discover_best_gemini_model($api_key);
        return !empty($discovered) ? $discovered : 'gemini-2.5-flash';
    }

    /**
     * Automatically inspect user's Google account and pick the newest/best flash model
     */
    public function discover_best_gemini_model($api_key, $force_refresh = false) {
        $cache_key = 'wp_ai_gemini_active_model_' . md5($api_key);
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if (!empty($cached)) return $cached;
        }

        $models = $this->get_available_gemini_models($api_key);
        if (empty($models)) {
            return 'gemini-2.5-flash';
        }

        $best_model = null;
        foreach ($models as $m) {
            if (strpos($m, 'flash') !== false && strpos($m, 'preview') === false) {
                $best_model = $m;
                break;
            }
        }

        if (!$best_model) {
            $best_model = $models[0];
        }

        set_transient($cache_key, $best_model, 7 * DAY_IN_SECONDS);
        return $best_model;
    }

    /**
     * Discover which models are active for this API key via Google's ListModels endpoint
     */
    public function get_available_gemini_models($api_key) {
        $endpoints = [
            'https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode($api_key),
            'https://generativelanguage.googleapis.com/v1/models?key=' . rawurlencode($api_key),
        ];

        foreach ($endpoints as $url) {
            $res = wp_remote_get($url, ['timeout' => 10]);
            if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
                $data = json_decode(wp_remote_retrieve_body($res), true);
                if (!empty($data['models']) && is_array($data['models'])) {
                    $models = [];
                    foreach ($data['models'] as $m) {
                        if (!empty($m['supportedGenerationMethods']) && in_array('generateContent', $m['supportedGenerationMethods'])) {
                            $models[] = str_replace('models/', '', $m['name']);
                        }
                    }
                    if (!empty($models)) {
                        return $models;
                    }
                }
            }
        }
        return [];
    }

    /**
     * Call OpenAI API
     */
    private function call_openai($message, $history, $page_context = []) {
        $api_key = trim($this->settings['openai_api_key']);
        if (empty($api_key)) {
            throw new Exception(__('OpenAI API Key is missing. Please add it in settings.', 'wp-ai-chatbot'));
        }

        $model = !empty($this->settings['openai_model']) ? trim($this->settings['openai_model']) : 'gpt-4o-mini';
        $url = 'https://api.openai.com/v1/chat/completions';

        $messages = [];
        $full_system_prompt = $this->build_full_system_prompt($message, $page_context);
        if (!empty($full_system_prompt)) {
            $messages[] = [
                'role'    => 'system',
                'content' => $full_system_prompt
            ];
        }

        if (!empty($history) && is_array($history)) {
            foreach ($history as $item) {
                if (empty($item['content'])) continue;
                $role = (isset($item['role']) && in_array($item['role'], ['assistant', 'bot', 'model'])) ? 'assistant' : 'user';
                $messages[] = [
                    'role'    => $role,
                    'content' => (string)$item['content']
                ];
            }
        }

        $messages[] = [
            'role'    => 'user',
            'content' => (string)$message
        ];

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 1200,
        ];

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            throw new Exception(__('Could not connect to OpenAI: ', 'wp-ai-chatbot') . $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status !== 200) {
            $err_msg = isset($data['error']['message']) ? $data['error']['message'] : __('HTTP Error ' . $status, 'wp-ai-chatbot');
            throw new Exception(__('OpenAI API Error: ', 'wp-ai-chatbot') . $err_msg);
        }

        $reply = $data['choices'][0]['message']['content'] ?? null;
        if (empty($reply)) {
            throw new Exception(__('Received an empty response from OpenAI.', 'wp-ai-chatbot'));
        }

        return trim($reply);
    }

    /**
     * Call Groq API
     */
    private function call_groq($message, $history, $page_context = []) {
        $api_key = trim($this->settings['groq_api_key']);
        if (empty($api_key)) {
            throw new Exception(__('Groq API Key is missing. Please add it in settings.', 'wp-ai-chatbot'));
        }

        $model = !empty($this->settings['groq_model']) ? trim($this->settings['groq_model']) : 'llama-3.3-70b-versatile';
        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $messages = [];
        $full_system_prompt = $this->build_full_system_prompt($message, $page_context);
        if (!empty($full_system_prompt)) {
            $messages[] = [
                'role'    => 'system',
                'content' => $full_system_prompt
            ];
        }

        if (!empty($history) && is_array($history)) {
            foreach ($history as $item) {
                if (empty($item['content'])) continue;
                $role = (isset($item['role']) && in_array($item['role'], ['assistant', 'bot', 'model'])) ? 'assistant' : 'user';
                $messages[] = [
                    'role'    => $role,
                    'content' => (string)$item['content']
                ];
            }
        }

        $messages[] = [
            'role'    => 'user',
            'content' => (string)$message
        ];

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 1200,
        ];

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            throw new Exception(__('Could not connect to Groq API: ', 'wp-ai-chatbot') . $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status !== 200) {
            $err_msg = isset($data['error']['message']) ? $data['error']['message'] : __('HTTP Error ' . $status, 'wp-ai-chatbot');
            throw new Exception(__('Groq API Error: ', 'wp-ai-chatbot') . $err_msg);
        }

        $reply = $data['choices'][0]['message']['content'] ?? null;
        if (empty($reply)) {
            throw new Exception(__('Received an empty response from Groq.', 'wp-ai-chatbot'));
        }

        return trim($reply);
    }

    /**
     * Call n8n or custom webhook
     */
    private function call_n8n_webhook($message, $history, $page_context = []) {
        $webhook_url = trim($this->settings['n8n_webhook_url']);
        if (empty($webhook_url)) {
            throw new Exception(__('n8n Webhook URL is missing. Please add it in settings.', 'wp-ai-chatbot'));
        }

        if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            throw new Exception(__('Invalid n8n Webhook URL provided.', 'wp-ai-chatbot'));
        }

        $payload = [
            'message'       => $message,
            'history'       => $history,
            'page_title'    => $page_context['page_title'] ?? '',
            'page_url'      => $page_context['page_url'] ?? '',
            'system_prompt' => $this->build_full_system_prompt($message, $page_context),
            'site_name'     => get_bloginfo('name'),
            'site_url'      => home_url(),
        ];

        $response = wp_remote_post($webhook_url, [
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept'       => 'application/json, text/plain',
            ],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            throw new Exception(__('Could not connect to n8n webhook: ', 'wp-ai-chatbot') . $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = trim(wp_remote_retrieve_body($response));

        if ($status < 200 || $status >= 300) {
            throw new Exception(__('n8n webhook returned status code: ', 'wp-ai-chatbot') . $status);
        }

        $data = json_decode($body, true);
        if (is_array($data)) {
            foreach (['output', 'reply', 'response', 'text', 'message'] as $key) {
                if (isset($data[$key])) {
                    return is_string($data[$key]) ? trim($data[$key]) : json_encode($data[$key]);
                }
            }
            if (isset($data[0])) {
                if (is_string($data[0])) return trim($data[0]);
                if (is_array($data[0])) {
                    foreach (['output', 'reply', 'response', 'text', 'message'] as $key) {
                        if (isset($data[0][$key])) return trim($data[0][$key]);
                    }
                }
            }
        }

        if (!empty($body)) {
            return $body;
        }

        throw new Exception(__('n8n webhook responded successfully but returned an empty response.', 'wp-ai-chatbot'));
    }
}
