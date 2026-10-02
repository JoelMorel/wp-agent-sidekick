<?php
/**
 * Admin Settings Page Handler
 *
 * @package WP_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_AI_Chatbot_Admin_Settings {

    const OPTION_NAME = 'wp_ai_chatbot_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
    }

    public function add_settings_page() {
        // Main Top-Level Menu in WordPress Admin Sidebar
        add_menu_page(
            __('WP Agent Sidekick', 'wp-ai-chatbot'),
            __('Agent Sidekick', 'wp-ai-chatbot'),
            'manage_options',
            'wp-agent-sidekick',
            [$this, 'render_settings_page'],
            'dashicons-superhero-alt',
            30
        );

        // Submenu: Settings
        add_submenu_page(
            'wp-agent-sidekick',
            __('Agent Sidekick Settings', 'wp-ai-chatbot'),
            __('Settings', 'wp-ai-chatbot'),
            'manage_options',
            'wp-agent-sidekick',
            [$this, 'render_settings_page']
        );

        // Submenu: Captured Leads
        add_submenu_page(
            'wp-agent-sidekick',
            __('Captured Leads Log', 'wp-ai-chatbot'),
            __('Captured Leads', 'wp-ai-chatbot'),
            'manage_options',
            'wp-agent-sidekick-leads',
            [$this, 'render_leads_page']
        );

        // Backward compatibility under Settings
        add_options_page(
            __('WP Agent Sidekick Settings', 'wp-ai-chatbot'),
            __('Agent Sidekick', 'wp-ai-chatbot'),
            'manage_options',
            'wp-ai-chatbot',
            [$this, 'render_settings_page']
        );
    }

    public function add_dashboard_widget() {
        if (!current_user_can('manage_options')) {
            return;
        }
        wp_add_dashboard_widget(
            'wp_agent_sidekick_dashboard_widget',
            __('🤖 WP Agent Sidekick — Status & Overview', 'wp-ai-chatbot'),
            [$this, 'render_dashboard_widget']
        );
    }

    public function register_settings() {
        register_setting('wp_ai_chatbot_settings_group', self::OPTION_NAME, [
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default'           => self::get_defaults(),
        ]);
    }

    public static function get_defaults() {
        return [
            'enabled'                 => 1,
            'provider'                => 'gemini',
            'gemini_api_key'          => '',
            'gemini_model'            => 'auto',
            'openai_api_key'          => '',
            'openai_model'            => 'gpt-4o-mini',
            'groq_api_key'            => '',
            'groq_model'              => 'llama-3.3-70b-versatile',
            'n8n_webhook_url'         => '',
            'system_prompt'           => "You are a friendly, concise, and helpful customer support assistant for " . get_bloginfo('name') . ". Answer visitor questions accurately and politely using the website information provided.",
            'auto_learn_content'      => 1,
            'knowledge_base'          => "Business Hours: Monday to Friday 9:00 AM - 5:00 PM\nEmail: contact@" . sanitize_title(get_bloginfo('name')) . ".com\nSupport: Response within 24 hours.",
            'enable_lead_capture'     => 1,
            'lead_notification_email' => get_option('admin_email'),
            'enable_wc_product_search'=> 1,
            'enable_wc_order_lookup'  => 1,
            'enable_human_handoff'    => 1,
            'handoff_channel'         => 'calendly',
            'handoff_target'          => '',
            'handoff_button_label'    => '📅 Schedule a Discovery Call',
            'slack_discord_webhook'   => '',
            'bot_title'               => 'Agent Sidekick',
            'welcome_message'         => 'Hello! How can I assist you today?',
            'placeholder'             => 'Type your question...',
            'theme_color'             => '#0073aa',
            'position'                => 'right',
            'quick_suggestions'       => "What services do you offer?\nHow do I get in touch?\nWhat are your business hours?",
            'enable_rate_limit'       => 1,
            'rate_limit_count'        => 20,
            'rate_limit_window'       => 600,
        ];
    }

    public function sanitize_settings($input) {
        $clean = [];
        $clean['enabled']                  = !empty($input['enabled']) ? 1 : 0;
        $clean['provider']                 = in_array($input['provider'] ?? '', ['gemini', 'openai', 'groq', 'n8n']) ? $input['provider'] : 'gemini';
        $clean['gemini_api_key']           = sanitize_text_field($input['gemini_api_key'] ?? '');
        $clean['gemini_model']             = sanitize_text_field($input['gemini_model'] ?? 'auto');
        $clean['openai_api_key']           = sanitize_text_field($input['openai_api_key'] ?? '');
        $clean['openai_model']             = sanitize_text_field($input['openai_model'] ?? 'gpt-4o-mini');
        $clean['groq_api_key']             = sanitize_text_field($input['groq_api_key'] ?? '');
        $clean['groq_model']               = sanitize_text_field($input['groq_model'] ?? 'llama-3.3-70b-versatile');
        $clean['n8n_webhook_url']          = esc_url_raw($input['n8n_webhook_url'] ?? '');
        $clean['system_prompt']            = sanitize_textarea_field($input['system_prompt'] ?? '');
        $clean['auto_learn_content']       = !empty($input['auto_learn_content']) ? 1 : 0;
        $clean['knowledge_base']           = sanitize_textarea_field($input['knowledge_base'] ?? '');
        $clean['enable_lead_capture']      = !empty($input['enable_lead_capture']) ? 1 : 0;
        $clean['lead_notification_email']  = sanitize_email($input['lead_notification_email'] ?? get_option('admin_email'));
        $clean['enable_wc_product_search'] = !empty($input['enable_wc_product_search']) ? 1 : 0;
        $clean['enable_wc_order_lookup']   = !empty($input['enable_wc_order_lookup']) ? 1 : 0;
        $clean['enable_human_handoff']     = !empty($input['enable_human_handoff']) ? 1 : 0;
        $clean['handoff_channel']          = in_array($input['handoff_channel'] ?? '', ['calendly', 'telegram', 'whatsapp', 'sms', 'phone', 'custom', 'email']) ? $input['handoff_channel'] : 'calendly';
        $clean['handoff_target']           = sanitize_text_field($input['handoff_target'] ?? '');
        $clean['handoff_button_label']     = sanitize_text_field($input['handoff_button_label'] ?? '');
        $clean['slack_discord_webhook']    = esc_url_raw($input['slack_discord_webhook'] ?? '');
        $clean['bot_title']                = sanitize_text_field($input['bot_title'] ?? 'Agent Sidekick');
        $clean['welcome_message']          = sanitize_text_field($input['welcome_message'] ?? 'Hello! How can I assist you today?');
        $clean['placeholder']              = sanitize_text_field($input['placeholder'] ?? 'Type your question...');
        $clean['theme_color']              = sanitize_hex_color($input['theme_color'] ?? '#0073aa') ?: '#0073aa';
        $clean['position']                 = in_array($input['position'] ?? '', ['left', 'right']) ? $input['position'] : 'right';
        $clean['quick_suggestions']        = sanitize_textarea_field($input['quick_suggestions'] ?? '');
        $clean['enable_rate_limit']        = !empty($input['enable_rate_limit']) ? 1 : 0;
        $clean['rate_limit_count']         = max(1, intval($input['rate_limit_count'] ?? 20));
        $clean['rate_limit_window']        = max(60, intval($input['rate_limit_window'] ?? 600));

        return $clean;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = wp_parse_args(get_option(self::OPTION_NAME, []), self::get_defaults());
        $provider = $settings['provider'];
        $has_woocommerce = class_exists('WooCommerce');
        $leads = get_option('wp_ai_chatbot_leads', []);
        ?>
        <div class="wrap wp-ai-chat-settings-wrap">
            <h1 style="display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-superhero-alt" style="font-size: 30px; width: 30px; height: 30px;"></span>
                <?php esc_html_e('WP Agent Sidekick Settings', 'wp-ai-chatbot'); ?>
                <span style="font-size: 13px; background: #e2e8f0; color: #475569; padding: 3px 8px; border-radius: 12px; vertical-align: middle; font-weight: normal; margin-left: 6px;">v<?php echo esc_html(WP_AI_CHATBOT_VERSION); ?></span>
            </h1>
            <p class="description"><?php esc_html_e('Configure your AI Agent Sidekick. Connects directly to Google Gemini (free API), OpenAI, Groq, or your own n8n workflow.', 'wp-ai-chatbot'); ?></p>

            <?php settings_errors(); ?>

            <form method="post" action="options.php" id="wp-ai-chat-form">
                <?php
                settings_fields('wp_ai_chatbot_settings_group');
                ?>

                <!-- Section 1: Provider & Credentials -->
                <div class="card" style="max-width: 900px; margin-top: 20px; padding: 20px;">
                    <h2>1. <?php esc_html_e('AI Provider Setup', 'wp-ai-chatbot'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="wp_ai_provider"><?php esc_html_e('Provider', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <select name="<?php echo self::OPTION_NAME; ?>[provider]" id="wp_ai_provider" style="min-width: 250px;">
                                    <option value="gemini" <?php selected($provider, 'gemini'); ?>><?php esc_html_e('Google Gemini (Recommended / Fast & Free Tier)', 'wp-ai-chatbot'); ?></option>
                                    <option value="openai" <?php selected($provider, 'openai'); ?>><?php esc_html_e('OpenAI (ChatGPT / GPT-4o-mini)', 'wp-ai-chatbot'); ?></option>
                                    <option value="groq" <?php selected($provider, 'groq'); ?>><?php esc_html_e('Groq (Ultra-low latency Llama 3)', 'wp-ai-chatbot'); ?></option>
                                    <option value="n8n" <?php selected($provider, 'n8n'); ?>><?php esc_html_e('n8n / Custom Webhook', 'wp-ai-chatbot'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Choose how responses are generated. Google Gemini has a generous free tier directly from Google AI Studio.', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>

                        <!-- Gemini Fields -->
                        <tr class="provider-fields provider-gemini" style="<?php echo ($provider !== 'gemini') ? 'display:none;' : ''; ?>">
                            <th scope="row"><label for="gemini_api_key"><?php esc_html_e('Gemini API Key', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="password" name="<?php echo self::OPTION_NAME; ?>[gemini_api_key]" id="gemini_api_key" value="<?php echo esc_attr($settings['gemini_api_key']); ?>" class="regular-text" autocomplete="off" />
                                <button type="button" class="button toggle-visibility" data-target="gemini_api_key"><?php esc_html_e('Show/Hide', 'wp-ai-chatbot'); ?></button>
                                <p class="description">
                                    <?php printf(
                                        __('Get a free API key at %s.', 'wp-ai-chatbot'),
                                        '<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio</a>'
                                    ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr class="provider-fields provider-gemini" style="<?php echo ($provider !== 'gemini') ? 'display:none;' : ''; ?>">
                            <th scope="row"><label for="gemini_model"><?php esc_html_e('Gemini Model', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <select name="<?php echo self::OPTION_NAME; ?>[gemini_model]" id="gemini_model">
                                    <option value="auto" <?php selected($settings['gemini_model'], 'auto'); ?>>✨ Auto-Detect Best Available (Recommended — Future-Proof)</option>
                                    <option value="gemini-2.5-flash" <?php selected($settings['gemini_model'], 'gemini-2.5-flash'); ?>>gemini-2.5-flash</option>
                                    <option value="gemini-2.5-flash-lite" <?php selected($settings['gemini_model'], 'gemini-2.5-flash-lite'); ?>>gemini-2.5-flash-lite</option>
                                    <option value="gemini-2.5-pro" <?php selected($settings['gemini_model'], 'gemini-2.5-pro'); ?>>gemini-2.5-pro</option>
                                    <option value="gemini-1.5-flash" <?php selected($settings['gemini_model'], 'gemini-1.5-flash'); ?>>gemini-1.5-flash (Legacy)</option>
                                </select>
                                <p class="description"><?php esc_html_e('"Auto-Detect" queries your Google account to discover and use the newest supported flash model. If Google updates or deprecates models in the future, the bot automatically adapts without breaking.', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>

                        <!-- OpenAI Fields -->
                        <tr class="provider-fields provider-openai" style="<?php echo ($provider !== 'openai') ? 'display:none;' : ''; ?>">
                            <th scope="row"><label for="openai_api_key"><?php esc_html_e('OpenAI API Key', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="password" name="<?php echo self::OPTION_NAME; ?>[openai_api_key]" id="openai_api_key" value="<?php echo esc_attr($settings['openai_api_key']); ?>" class="regular-text" autocomplete="off" />
                                <button type="button" class="button toggle-visibility" data-target="openai_api_key"><?php esc_html_e('Show/Hide', 'wp-ai-chatbot'); ?></button>
                                <p class="description"><?php esc_html_e('Get your API key at platform.openai.com', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr class="provider-fields provider-openai" style="<?php echo ($provider !== 'openai') ? 'display:none;' : ''; ?>">
                            <th scope="row"><label for="openai_model"><?php esc_html_e('OpenAI Model', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <select name="<?php echo self::OPTION_NAME; ?>[openai_model]" id="openai_model">
                                    <option value="gpt-4o-mini" <?php selected($settings['openai_model'], 'gpt-4o-mini'); ?>>gpt-4o-mini (Cost-efficient)</option>
                                    <option value="gpt-4o" <?php selected($settings['openai_model'], 'gpt-4o'); ?>>gpt-4o (Flagship)</option>
                                </select>
                            </td>
                        </tr>

                        <!-- Groq Fields -->
                        <tr class="provider-fields provider-groq" style="<?php echo ($provider !== 'groq') ? 'display:none;' : ''; ?>">
                            <th scope="row"><label for="groq_api_key"><?php esc_html_e('Groq API Key', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="password" name="<?php echo self::OPTION_NAME; ?>[groq_api_key]" id="groq_api_key" value="<?php echo esc_attr($settings['groq_api_key']); ?>" class="regular-text" autocomplete="off" />
                                <button type="button" class="button toggle-visibility" data-target="groq_api_key"><?php esc_html_e('Show/Hide', 'wp-ai-chatbot'); ?></button>
                                <p class="description"><?php esc_html_e('Get your API key at console.groq.com', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr class="provider-fields provider-groq" style="<?php echo ($provider !== 'groq') ? 'display:none;' : ''; ?>">
                            <th scope="row"><label for="groq_model"><?php esc_html_e('Groq Model', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="text" name="<?php echo self::OPTION_NAME; ?>[groq_model]" id="groq_model" value="<?php echo esc_attr($settings['groq_model']); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('E.g., llama-3.3-70b-versatile or llama3-8b-8192', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>

                        <!-- n8n Fields -->
                        <tr class="provider-fields provider-n8n" style="<?php echo ($provider !== 'n8n') ? 'display:none;' : ''; ?>">
                            <th scope="row"><label for="n8n_webhook_url"><?php esc_html_e('n8n Webhook URL', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="url" name="<?php echo self::OPTION_NAME; ?>[n8n_webhook_url]" id="n8n_webhook_url" value="<?php echo esc_attr($settings['n8n_webhook_url']); ?>" class="large-text" placeholder="https://your-n8n-instance.com/webhook/chat" />
                                <p class="description"><?php esc_html_e('WordPress sends { "message": "...", "history": [...], "page_title": "...", "page_url": "..." } and expects { "output": "..." } or text in response.', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>

                        <!-- Test Connection Button -->
                        <tr>
                            <th scope="row"><?php esc_html_e('Test Connection', 'wp-ai-chatbot'); ?></th>
                            <td>
                                <button type="button" id="btn-test-connection" class="button button-secondary"><?php esc_html_e('Test AI Connection Now', 'wp-ai-chatbot'); ?></button>
                                <span id="test-connection-status" style="margin-left: 10px; font-weight: 500;"></span>
                                <p class="description"><?php esc_html_e('Tip: Save your settings first, then click here to verify that WordPress can connect successfully.', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Section 2: Site Knowledge & Training -->
                <div class="card" style="max-width: 900px; margin-top: 20px; padding: 20px;">
                    <h2>2. <?php esc_html_e('Site Knowledge & Training', 'wp-ai-chatbot'); ?></h2>
                    <p class="description"><?php esc_html_e('Give the chatbot knowledge about this specific website so it can accurately answer visitor questions.', 'wp-ai-chatbot'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Auto-Learn Site Content', 'wp-ai-chatbot'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo self::OPTION_NAME; ?>[auto_learn_content]" value="1" <?php checked($settings['auto_learn_content'], 1); ?> />
                                    <strong><?php esc_html_e('Enable Automatic Content Search (Native Mini-RAG)', 'wp-ai-chatbot'); ?></strong>
                                </label>
                                <p class="description"><?php esc_html_e('When a visitor asks a question, WordPress automatically searches your published Pages, Blog Posts, and WooCommerce Products for matching keywords and feeds relevant excerpts to the AI. Whenever you edit or add a page, the bot automatically learns the update!', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="knowledge_base"><?php esc_html_e('Business Info & FAQs', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <textarea name="<?php echo self::OPTION_NAME; ?>[knowledge_base]" id="knowledge_base" rows="5" class="large-text"><?php echo esc_textarea($settings['knowledge_base']); ?></textarea>
                                <p class="description"><?php esc_html_e('Paste any specific business guidelines, FAQs, business hours, store policies, or pricing you want the chatbot to always know.', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="system_prompt"><?php esc_html_e('System Prompt (Persona)', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <textarea name="<?php echo self::OPTION_NAME; ?>[system_prompt]" id="system_prompt" rows="3" class="large-text"><?php echo esc_textarea($settings['system_prompt']); ?></textarea>
                                <p class="description"><?php esc_html_e('Defines the bot tone and demeanor (e.g. friendly, concise, formal).', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Section 3: Agency & Lead Generation -->
                <div class="card" style="max-width: 900px; margin-top: 20px; padding: 20px; border-left: 4px solid #3b82f6;">
                    <h2>3. <?php esc_html_e('Agency & Lead Generation', 'wp-ai-chatbot'); ?></h2>
                    <p class="description"><?php esc_html_e('Ideal for agencies, consultancies, and service businesses to turn casual visitors into paying clients.', 'wp-ai-chatbot'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Lead Capture Mode', 'wp-ai-chatbot'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo self::OPTION_NAME; ?>[enable_lead_capture]" value="1" <?php checked($settings['enable_lead_capture'], 1); ?> />
                                    <strong><?php esc_html_e('Automatically collect visitor Name, Email, and Project details', 'wp-ai-chatbot'); ?></strong>
                                </label>
                                <p class="description"><?php esc_html_e('When a visitor asks for a quote, pricing, consultation, or custom service, the bot politely asks for their contact info and sends you an instant email notification.', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lead_notification_email"><?php esc_html_e('Lead Notification Email', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="email" name="<?php echo self::OPTION_NAME; ?>[lead_notification_email]" id="lead_notification_email" value="<?php echo esc_attr($settings['lead_notification_email']); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('Where to send new lead notifications. Defaults to your WordPress admin email.', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Section 4: WooCommerce & E-Commerce Store -->
                <div class="card" style="max-width: 900px; margin-top: 20px; padding: 20px; border-left: 4px solid #7c3aed;">
                    <h2>
                        4. <?php esc_html_e('WooCommerce & E-Commerce Store', 'wp-ai-chatbot'); ?>
                        <?php if ($has_woocommerce): ?>
                            <span style="font-size: 11px; background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 10px; margin-left: 8px;">✓ WooCommerce Active</span>
                        <?php else: ?>
                            <span style="font-size: 11px; background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 10px; margin-left: 8px;">WooCommerce Not Detected</span>
                        <?php endif; ?>
                    </h2>
                    <p class="description"><?php esc_html_e('Features designed for online stores and e-commerce websites to assist shoppers and reduce support tickets.', 'wp-ai-chatbot'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Product & Price Search', 'wp-ai-chatbot'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo self::OPTION_NAME; ?>[enable_wc_product_search]" value="1" <?php checked($settings['enable_wc_product_search'], 1); ?> />
                                    <strong><?php esc_html_e('Include live product prices, stock status, and direct store links', 'wp-ai-chatbot'); ?></strong>
                                </label>
                                <p class="description"><?php esc_html_e('When customers ask about products (e.g., "Do you have oversized hoodies?"), the bot returns exact live prices, inventory status, and links.', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Order Status Lookup', 'wp-ai-chatbot'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo self::OPTION_NAME; ?>[enable_wc_order_lookup]" value="1" <?php checked($settings['enable_wc_order_lookup'], 1); ?> />
                                    <strong><?php esc_html_e('Enable automated order lookup ("Where is my order #1234?")', 'wp-ai-chatbot'); ?></strong>
                                </label>
                                <p class="description"><?php esc_html_e('When a customer provides their order number, the bot checks WooCommerce in real time to report shipping and fulfillment status.', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Section 5: Human Support Handoff & Channels -->
                <div class="card" style="max-width: 900px; margin-top: 20px; padding: 20px; border-left: 4px solid #10b981;">
                    <h2>5. <?php esc_html_e('Human Support Handoff & Channels', 'wp-ai-chatbot'); ?></h2>
                    <p class="description"><?php esc_html_e('When a visitor wants to talk to a real person or the bot encounters an unresolved issue, seamlessly connect them to your human team.', 'wp-ai-chatbot'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Human Handoff', 'wp-ai-chatbot'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo self::OPTION_NAME; ?>[enable_human_handoff]" value="1" <?php checked($settings['enable_human_handoff'], 1); ?> />
                                    <strong><?php esc_html_e('Enable interactive handoff action button in chat', 'wp-ai-chatbot'); ?></strong>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="handoff_channel"><?php esc_html_e('Handoff Channel', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <select name="<?php echo self::OPTION_NAME; ?>[handoff_channel]" id="handoff_channel" style="min-width: 300px;">
                                    <option value="calendly" <?php selected($settings['handoff_channel'], 'calendly'); ?>><?php esc_html_e('📅 Calendar Booking (Calendly, Cal.com, TidyCal) — Best for Agencies', 'wp-ai-chatbot'); ?></option>
                                    <option value="telegram" <?php selected($settings['handoff_channel'], 'telegram'); ?>><?php esc_html_e('💬 Telegram Direct Message', 'wp-ai-chatbot'); ?></option>
                                    <option value="whatsapp" <?php selected($settings['handoff_channel'], 'whatsapp'); ?>><?php esc_html_e('💬 WhatsApp Support', 'wp-ai-chatbot'); ?></option>
                                    <option value="sms" <?php selected($settings['handoff_channel'], 'sms'); ?>><?php esc_html_e('📱 Direct SMS / Text Message', 'wp-ai-chatbot'); ?></option>
                                    <option value="phone" <?php selected($settings['handoff_channel'], 'phone'); ?>><?php esc_html_e('📞 Direct Phone Call', 'wp-ai-chatbot'); ?></option>
                                    <option value="custom" <?php selected($settings['handoff_channel'], 'custom'); ?>><?php esc_html_e('🔗 Custom Helpdesk / Contact Link (Help Scout, Gorgias, etc.)', 'wp-ai-chatbot'); ?></option>
                                    <option value="email" <?php selected($settings['handoff_channel'], 'email'); ?>><?php esc_html_e('📩 Email Support Team Directly', 'wp-ai-chatbot'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr id="row-handoff-target">
                            <th scope="row"><label for="handoff_target" id="label-handoff-target"><?php esc_html_e('Channel Link / Target', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="text" name="<?php echo self::OPTION_NAME; ?>[handoff_target]" id="handoff_target" value="<?php echo esc_attr($settings['handoff_target']); ?>" class="large-text" placeholder="https://calendly.com/your-agency/15min" />
                                <p class="description" id="desc-handoff-target"><?php esc_html_e('Paste your Calendly, Cal.com, or booking URL.', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="handoff_button_label"><?php esc_html_e('Action Button Text', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="text" name="<?php echo self::OPTION_NAME; ?>[handoff_button_label]" id="handoff_button_label" value="<?php echo esc_attr($settings['handoff_button_label']); ?>" class="regular-text" placeholder="Connect with a Team Member" />
                                <p class="description"><?php esc_html_e('The text displayed on the interactive button presented to the visitor in chat.', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="slack_discord_webhook"><?php esc_html_e('Team Alert Webhook (Slack / Discord)', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="url" name="<?php echo self::OPTION_NAME; ?>[slack_discord_webhook]" id="slack_discord_webhook" value="<?php echo esc_attr($settings['slack_discord_webhook']); ?>" class="large-text" placeholder="https://hooks.slack.com/services/... or https://discord.com/api/webhooks/..." />
                                <p class="description"><?php esc_html_e('Optional: Paste an incoming Slack or Discord Webhook URL. Whenever a visitor requests human help, an instant alert is sent to your team channel with their question and current page!', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Section 6: Appearance & Widget Customization -->
                <div class="card" style="max-width: 900px; margin-top: 20px; padding: 20px;">
                    <h2>6. <?php esc_html_e('Appearance & Widget Settings', 'wp-ai-chatbot'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Floating Widget', 'wp-ai-chatbot'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo self::OPTION_NAME; ?>[enabled]" value="1" <?php checked($settings['enabled'], 1); ?> />
                                    <?php esc_html_e('Enable floating chat widget on all site pages', 'wp-ai-chatbot'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('You can also embed the chatbot anywhere using shortcode: [wp_ai_chat]', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bot_title"><?php esc_html_e('Chatbot Title', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="text" name="<?php echo self::OPTION_NAME; ?>[bot_title]" id="bot_title" value="<?php echo esc_attr($settings['bot_title']); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="welcome_message"><?php esc_html_e('Welcome Greeting', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="text" name="<?php echo self::OPTION_NAME; ?>[welcome_message]" id="welcome_message" value="<?php echo esc_attr($settings['welcome_message']); ?>" class="large-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="quick_suggestions"><?php esc_html_e('Starter Suggestions', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <textarea name="<?php echo self::OPTION_NAME; ?>[quick_suggestions]" id="quick_suggestions" rows="3" class="large-text"><?php echo esc_textarea($settings['quick_suggestions']); ?></textarea>
                                <p class="description"><?php esc_html_e('One clickable suggestion per line. Shown to visitors when they first open the chat widget.', 'wp-ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="placeholder"><?php esc_html_e('Input Placeholder', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="text" name="<?php echo self::OPTION_NAME; ?>[placeholder]" id="placeholder" value="<?php echo esc_attr($settings['placeholder']); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="theme_color"><?php esc_html_e('Brand Accent Color', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="color" name="<?php echo self::OPTION_NAME; ?>[theme_color]" id="theme_color" value="<?php echo esc_attr($settings['theme_color']); ?>" />
                                <span style="margin-left: 8px;"><?php echo esc_html($settings['theme_color']); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="position"><?php esc_html_e('Widget Position', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <select name="<?php echo self::OPTION_NAME; ?>[position]" id="position">
                                    <option value="right" <?php selected($settings['position'], 'right'); ?>><?php esc_html_e('Bottom Right', 'wp-ai-chatbot'); ?></option>
                                    <option value="left" <?php selected($settings['position'], 'left'); ?>><?php esc_html_e('Bottom Left', 'wp-ai-chatbot'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Section 7: Security & Abuse Protection -->
                <div class="card" style="max-width: 900px; margin-top: 20px; padding: 20px;">
                    <h2>7. <?php esc_html_e('Rate Limiting & Protection', 'wp-ai-chatbot'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Rate Limiter', 'wp-ai-chatbot'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo self::OPTION_NAME; ?>[enable_rate_limit]" value="1" <?php checked($settings['enable_rate_limit'], 1); ?> />
                                    <?php esc_html_e('Protect API budget by limiting messages per visitor IP', 'wp-ai-chatbot'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="rate_limit_count"><?php esc_html_e('Max Messages', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="number" name="<?php echo self::OPTION_NAME; ?>[rate_limit_count]" id="rate_limit_count" value="<?php echo esc_attr($settings['rate_limit_count']); ?>" class="small-text" min="1" max="100" />
                                <?php esc_html_e('messages per visitor within window', 'wp-ai-chatbot'); ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="rate_limit_window"><?php esc_html_e('Time Window', 'wp-ai-chatbot'); ?></label></th>
                            <td>
                                <input type="number" name="<?php echo self::OPTION_NAME; ?>[rate_limit_window]" id="rate_limit_window" value="<?php echo esc_attr($settings['rate_limit_window']); ?>" class="small-text" min="60" max="86400" />
                                <?php esc_html_e('seconds (e.g. 600 = 10 minutes)', 'wp-ai-chatbot'); ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(__('Save Chatbot Settings', 'wp-ai-chatbot')); ?>
            </form>

            <!-- Section 8: Captured Leads Log (For Agencies & Store Owners) -->
            <div class="card" style="max-width: 900px; margin-top: 30px; padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <div>
                        <h2 style="margin: 0;"><?php esc_html_e('8. Captured Leads Log', 'wp-ai-chatbot'); ?></h2>
                        <p class="description" style="margin: 4px 0 0 0;"><?php esc_html_e('Prospective client inquiries captured directly from chat conversations.', 'wp-ai-chatbot'); ?></p>
                    </div>
                    <?php if (!empty($leads)): ?>
                        <button type="button" id="btn-clear-leads" class="button button-link-delete" style="color: #b32d2e;"><?php esc_html_e('Clear Leads Log', 'wp-ai-chatbot'); ?></button>
                    <?php endif; ?>
                </div>

                <?php if (empty($leads)): ?>
                    <p style="color: #64748b; font-style: italic; padding: 15px 0;">
                        <?php esc_html_e('No leads captured yet. As visitors chat and provide their contact info, inquiries will appear here automatically.', 'wp-ai-chatbot'); ?>
                    </p>
                <?php else: ?>
                    <table class="widefat striped" style="border: 1px solid #e2e8f0; border-radius: 4px;">
                        <thead>
                            <tr>
                                <th style="width: 140px;"><?php esc_html_e('Date', 'wp-ai-chatbot'); ?></th>
                                <th style="width: 130px;"><?php esc_html_e('Name', 'wp-ai-chatbot'); ?></th>
                                <th style="width: 160px;"><?php esc_html_e('Email / Phone', 'wp-ai-chatbot'); ?></th>
                                <th><?php esc_html_e('Inquiry / Notes', 'wp-ai-chatbot'); ?></th>
                                <th style="width: 150px;"><?php esc_html_e('Page Source', 'wp-ai-chatbot'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leads as $lead): ?>
                                <tr>
                                    <td><?php echo esc_html(mysql2date('M j, Y g:i a', $lead['date'] ?? '')); ?></td>
                                    <td><strong><?php echo esc_html($lead['name'] ?: 'Anonymous'); ?></strong></td>
                                    <td>
                                        <?php if (!empty($lead['email'])): ?>
                                            <a href="mailto:<?php echo esc_attr($lead['email']); ?>"><?php echo esc_html($lead['email']); ?></a><br/>
                                        <?php endif; ?>
                                        <?php if (!empty($lead['phone'])): ?>
                                            <span style="color: #64748b; font-size: 12px;"><?php echo esc_html($lead['phone']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($lead['notes'] ?: 'No notes provided'); ?></td>
                                    <td>
                                        <?php if (!empty($lead['page_title'])): ?>
                                            <a href="<?php echo esc_url($lead['page_url']); ?>" target="_blank" rel="noopener">
                                                <?php echo esc_html($lead['page_title']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Provider switcher
            const providerSelect = document.getElementById('wp_ai_provider');
            providerSelect.addEventListener('change', function() {
                document.querySelectorAll('.provider-fields').forEach(function(row) {
                    row.style.display = 'none';
                });
                const selected = providerSelect.value;
                document.querySelectorAll('.provider-' + selected).forEach(function(row) {
                    row.style.display = '';
                });
            });

            // Handoff channel switcher
            const handoffChannelSelect = document.getElementById('handoff_channel');
            const handoffTargetRow = document.getElementById('row-handoff-target');
            const handoffTargetInput = document.getElementById('handoff_target');
            const handoffTargetDesc = document.getElementById('desc-handoff-target');

            function updateHandoffFields() {
                const channel = handoffChannelSelect.value;
                if (channel === 'email') {
                    handoffTargetRow.style.display = 'none';
                } else {
                    handoffTargetRow.style.display = '';
                    if (channel === 'calendly') {
                        handoffTargetInput.placeholder = 'https://calendly.com/your-agency/15min';
                        handoffTargetDesc.textContent = 'Paste your Calendly, Cal.com, or TidyCal booking URL.';
                    } else if (channel === 'telegram') {
                        handoffTargetInput.placeholder = 'yourusername (or https://t.me/yourusername)';
                        handoffTargetDesc.textContent = 'Enter your Telegram username or link.';
                    } else if (channel === 'whatsapp') {
                        handoffTargetInput.placeholder = '+1234567890';
                        handoffTargetDesc.textContent = 'Enter your WhatsApp phone number with country code.';
                    } else if (channel === 'sms') {
                        handoffTargetInput.placeholder = '+1234567890';
                        handoffTargetDesc.textContent = 'Enter your mobile phone number for direct SMS.';
                    } else if (channel === 'phone') {
                        handoffTargetInput.placeholder = '+1234567890';
                        handoffTargetDesc.textContent = 'Enter your phone number for click-to-call.';
                    } else if (channel === 'custom') {
                        handoffTargetInput.placeholder = 'https://support.yourcompany.com or Gorgias/Help Scout URL';
                        handoffTargetDesc.textContent = 'Paste your external helpdesk or support page link.';
                    }
                }
            }

            if (handoffChannelSelect) {
                handoffChannelSelect.addEventListener('change', updateHandoffFields);
                updateHandoffFields();
            }

            // Toggle visibility for password fields
            document.querySelectorAll('.toggle-visibility').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const input = document.getElementById(targetId);
                    if (input) {
                        input.type = (input.type === 'password') ? 'text' : 'password';
                    }
                });
            });

            // Test connection AJAX
            const testBtn = document.getElementById('btn-test-connection');
            const statusEl = document.getElementById('test-connection-status');
            testBtn.addEventListener('click', function() {
                statusEl.textContent = 'Testing connection...';
                statusEl.style.color = '#555';
                testBtn.disabled = true;

                fetch('<?php echo esc_url(rest_url('wp-ai-chatbot/v1/test-connection')); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                    }
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    testBtn.disabled = false;
                    if (data.success) {
                        statusEl.textContent = '✓ ' + (data.reply || 'Success! Connection established.');
                        statusEl.style.color = '#46b450';
                    } else {
                        statusEl.textContent = '✗ ' + (data.message || 'Connection failed.');
                        statusEl.style.color = '#dc3232';
                    }
                })
                .catch(function(err) {
                    testBtn.disabled = false;
                    statusEl.textContent = '✗ Network error: ' + err.message;
                    statusEl.style.color = '#dc3232';
                });
            });

            // Clear Leads AJAX
            const clearLeadsBtn = document.getElementById('btn-clear-leads');
            if (clearLeadsBtn) {
                clearLeadsBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to clear all captured leads?')) {
                        fetch('<?php echo esc_url(rest_url('wp-ai-chatbot/v1/clear-leads')); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                            }
                        })
                        .then(function() {
                            window.location.reload();
                        });
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Render main WordPress Dashboard Glance Widget
     */
    public function render_dashboard_widget() {
        $settings = wp_parse_args(get_option(self::OPTION_NAME, []), self::get_defaults());
        $leads = get_option('wp_ai_chatbot_leads', []);
        $is_enabled = !empty($settings['enabled']);
        $provider_labels = [
            'gemini' => 'Google Gemini',
            'openai' => 'OpenAI ChatGPT',
            'groq'   => 'Groq (Llama 3)',
            'n8n'    => 'n8n Webhook',
        ];
        $provider_name = $provider_labels[$settings['provider']] ?? ucfirst($settings['provider']);
        $model_name = ($settings['provider'] === 'gemini') ? $settings['gemini_model'] : (($settings['provider'] === 'openai') ? $settings['openai_model'] : $settings['groq_model']);
        if ($model_name === 'auto') {
            $model_name = '✨ Auto-Detect Best Flash';
        }
        ?>
        <div style="font-family: inherit;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
                <div>
                    <strong style="font-size: 13px; color: #1e293b;"><?php esc_html_e('Agent Status:', 'wp-ai-chatbot'); ?></strong>
                    <?php if ($is_enabled): ?>
                        <span style="display: inline-flex; align-items: center; gap: 5px; background: #dcfce7; color: #166534; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 12px; margin-left: 6px;">
                            <span style="width: 7px; height: 7px; border-radius: 50%; background: #22c55e;"></span> <?php esc_html_e('Active & Live', 'wp-ai-chatbot'); ?>
                        </span>
                    <?php else: ?>
                        <span style="display: inline-flex; align-items: center; gap: 5px; background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 12px; margin-left: 6px;">
                            <span style="width: 7px; height: 7px; border-radius: 50%; background: #ef4444;"></span> <?php esc_html_e('Inactive', 'wp-ai-chatbot'); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <span style="font-size: 12px; color: #64748b;">v<?php echo esc_html(WP_AI_CHATBOT_VERSION); ?></span>
            </div>

            <p style="margin: 6px 0; font-size: 13px;">
                <strong><?php esc_html_e('AI Engine:', 'wp-ai-chatbot'); ?></strong> <?php echo esc_html($provider_name); ?> <span style="color: #64748b;">(<?php echo esc_html($model_name); ?>)</span>
            </p>
            <p style="margin: 6px 0; font-size: 13px;">
                <strong><?php esc_html_e('Support Handoff:', 'wp-ai-chatbot'); ?></strong>
                <?php if (!empty($settings['enable_human_handoff'])): ?>
                    <span style="color: #059669; font-weight: 500;">✓ <?php esc_html_e('Enabled', 'wp-ai-chatbot'); ?> (<?php echo esc_html(ucfirst($settings['handoff_channel'])); ?>)</span>
                <?php else: ?>
                    <span style="color: #94a3b8;"><?php esc_html_e('Disabled', 'wp-ai-chatbot'); ?></span>
                <?php endif; ?>
            </p>
            <p style="margin: 6px 0; font-size: 13px;">
                <strong><?php esc_html_e('Total Leads Logged:', 'wp-ai-chatbot'); ?></strong>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-agent-sidekick-leads')); ?>" style="font-weight: 600;">
                    <?php printf(esc_html__('%d leads captured', 'wp-ai-chatbot'), count($leads)); ?>
                </a>
            </p>

            <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #f1f5f9; display: flex; flex-wrap: wrap; gap: 8px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-agent-sidekick')); ?>" class="button button-primary" style="font-size: 12px;">
                    ⚙️ <?php esc_html_e('Configure Sidekick', 'wp-ai-chatbot'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-agent-sidekick-leads')); ?>" class="button" style="font-size: 12px;">
                    📋 <?php esc_html_e('View Leads', 'wp-ai-chatbot'); ?>
                </a>
                <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" class="button" style="font-size: 12px;">
                    💬 <?php esc_html_e('Test on Site', 'wp-ai-chatbot'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render standalone Leads Log page under Agent Sidekick menu
     */
    public function render_leads_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $leads = get_option('wp_ai_chatbot_leads', []);
        ?>
        <div class="wrap wp-ai-chat-settings-wrap">
            <h1 style="display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-superhero-alt" style="font-size: 30px; width: 30px; height: 30px;"></span>
                <?php esc_html_e('WP Agent Sidekick — Captured Leads', 'wp-ai-chatbot'); ?>
                <span style="font-size: 13px; background: #e2e8f0; color: #475569; padding: 3px 8px; border-radius: 12px; vertical-align: middle; font-weight: normal; margin-left: 6px;">v<?php echo esc_html(WP_AI_CHATBOT_VERSION); ?></span>
            </h1>
            <p class="description"><?php esc_html_e('All visitor leads captured automatically by your Agent Sidekick.', 'wp-ai-chatbot'); ?></p>

            <div class="card" style="max-width: 1000px; margin-top: 20px; padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h2 style="margin: 0;"><?php esc_html_e('Captured Visitor Leads', 'wp-ai-chatbot'); ?> (<?php echo count($leads); ?>)</h2>
                    <?php if (!empty($leads)): ?>
                        <button type="button" id="btn-clear-leads-standalone" class="button button-link-delete"><?php esc_html_e('Clear All Leads', 'wp-ai-chatbot'); ?></button>
                    <?php endif; ?>
                </div>

                <?php if (empty($leads)): ?>
                    <p style="color: #64748b; font-style: italic;"><?php esc_html_e('No leads captured yet. When visitors ask for pricing, quotes, or consultations, your Agent Sidekick will automatically collect their details and log them here.', 'wp-ai-chatbot'); ?></p>
                <?php else: ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date / Time', 'wp-ai-chatbot'); ?></th>
                                <th><?php esc_html_e('Name', 'wp-ai-chatbot'); ?></th>
                                <th><?php esc_html_e('Contact', 'wp-ai-chatbot'); ?></th>
                                <th><?php esc_html_e('Project Notes / Requirements', 'wp-ai-chatbot'); ?></th>
                                <th><?php esc_html_e('Captured From Page', 'wp-ai-chatbot'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($leads) as $lead): ?>
                                <tr>
                                    <td><?php echo esc_html(mysql2date('M j, Y g:i a', $lead['date'] ?? '')); ?></td>
                                    <td><strong><?php echo esc_html($lead['name'] ?: 'Anonymous'); ?></strong></td>
                                    <td>
                                        <?php if (!empty($lead['email'])): ?>
                                            <a href="mailto:<?php echo esc_attr($lead['email']); ?>"><?php echo esc_html($lead['email']); ?></a><br/>
                                        <?php endif; ?>
                                        <?php if (!empty($lead['phone'])): ?>
                                            <span style="color: #64748b; font-size: 12px;"><?php echo esc_html($lead['phone']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($lead['notes'] ?: 'No notes provided'); ?></td>
                                    <td>
                                        <?php if (!empty($lead['page_title'])): ?>
                                            <a href="<?php echo esc_url($lead['page_url']); ?>" target="_blank" rel="noopener">
                                                <?php echo esc_html($lead['page_title']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var clearLeadsBtn = document.getElementById('btn-clear-leads-standalone');
            if (clearLeadsBtn) {
                clearLeadsBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to clear all captured leads?')) {
                        fetch('<?php echo esc_url(rest_url('wp-ai-chatbot/v1/clear-leads')); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                            }
                        })
                        .then(function() {
                            window.location.reload();
                        });
                    }
                });
            }
        });
        </script>
        <?php
    }
}
