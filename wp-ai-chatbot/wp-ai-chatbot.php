<?php
/**
 * Plugin Name:       WP Agent Sidekick
 * Plugin URI:        https://morelmedia.org
 * Description:       Lightweight, standalone AI Agent & Assistant for WordPress. Connects directly to Google Gemini, OpenAI, Groq, or an n8n webhook—no external server required.
 * Version:           1.5.0
 * Author:            Morel Media
 * Author URI:        https://morelmedia.org
 * License:           GPL-2.0+
 * Text Domain:       wp-ai-chatbot
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_AI_CHATBOT_VERSION', '1.5.0');
define('WP_AI_CHATBOT_FILE', __FILE__);
define('WP_AI_CHATBOT_PATH', plugin_dir_path(__FILE__));
define('WP_AI_CHATBOT_URL', plugin_dir_url(__FILE__));

// Require plugin classes
require_once WP_AI_CHATBOT_PATH . 'includes/class-llm-service.php';
require_once WP_AI_CHATBOT_PATH . 'includes/class-api-handler.php';
require_once WP_AI_CHATBOT_PATH . 'includes/class-admin-settings.php';

/**
 * Main Plugin Class
 */
class WP_AI_Chatbot {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook(WP_AI_CHATBOT_FILE, [$this, 'activate']);

        // Initialize admin settings
        if (is_admin()) {
            new WP_AI_Chatbot_Admin_Settings();
            add_filter('plugin_action_links_' . plugin_basename(WP_AI_CHATBOT_FILE), [$this, 'add_plugin_action_links']);
        }

        // Initialize REST API handler & lead listener
        $api_handler = new WP_AI_Chatbot_API_Handler();
        add_action('rest_api_init', [$api_handler, 'register_routes']);

        // Enqueue frontend scripts & styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Register Shortcode
        add_shortcode('wp_ai_chat', [$this, 'render_shortcode']);
    }

    /**
     * Set default options on activation
     */
    public function activate() {
        if (!get_option('wp_ai_chatbot_settings')) {
            update_option('wp_ai_chatbot_settings', WP_AI_Chatbot_Admin_Settings::get_defaults());
        }
    }

    /**
     * Add "Settings" shortcut link on Plugins screen
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=wp-agent-sidekick')) . '">' . __('Settings', 'wp-ai-chatbot') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }


    /**
     * Enqueue assets on frontend if widget is enabled
     */
    public function enqueue_assets() {
        $settings = wp_parse_args(get_option('wp_ai_chatbot_settings', []), WP_AI_Chatbot_Admin_Settings::get_defaults());

        // Check if floating widget is enabled
        if (empty($settings['enabled'])) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'wp-ai-chatbot-css',
            WP_AI_CHATBOT_URL . 'assets/css/chatbot.css',
            [],
            WP_AI_CHATBOT_VERSION
        );

        // Enqueue JS
        wp_enqueue_script(
            'wp-ai-chatbot-js',
            WP_AI_CHATBOT_URL . 'assets/js/chatbot.js',
            [],
            WP_AI_CHATBOT_VERSION,
            true // in footer
        );

        // Parse quick suggestions into array
        $suggestions_raw = !empty($settings['quick_suggestions']) ? explode("\n", $settings['quick_suggestions']) : [];
        $clean_suggestions = array_values(array_filter(array_map('trim', $suggestions_raw)));

        // Localize script with backend configuration
        wp_localize_script('wp-ai-chatbot-js', 'wpAiChatbotConfig', [
            'apiUrl'         => esc_url_raw(rest_url('wp-ai-chatbot/v1/chat')),
            'nonce'          => wp_create_nonce('wp_rest'),
            'botTitle'       => esc_html($settings['bot_title']),
            'welcomeMessage' => esc_html($settings['welcome_message']),
            'placeholder'    => esc_html($settings['placeholder']),
            'themeColor'     => esc_attr($settings['theme_color']),
            'position'       => esc_attr($settings['position']),
            'suggestions'    => $clean_suggestions,
        ]);
    }

    /**
     * Shortcode [wp_ai_chat] for inline placement
     */
    public function render_shortcode($atts) {
        $settings = wp_parse_args(get_option('wp_ai_chatbot_settings', []), WP_AI_Chatbot_Admin_Settings::get_defaults());

        // Make sure assets are enqueued even if floating widget is disabled
        wp_enqueue_style('wp-ai-chatbot-css', WP_AI_CHATBOT_URL . 'assets/css/chatbot.css', [], WP_AI_CHATBOT_VERSION);
        wp_enqueue_script('wp-ai-chatbot-js', WP_AI_CHATBOT_URL . 'assets/js/chatbot.js', [], WP_AI_CHATBOT_VERSION, true);

        $suggestions_raw = !empty($settings['quick_suggestions']) ? explode("\n", $settings['quick_suggestions']) : [];
        $clean_suggestions = array_values(array_filter(array_map('trim', $suggestions_raw)));

        wp_localize_script('wp-ai-chatbot-js', 'wpAiChatbotConfig', [
            'apiUrl'         => esc_url_raw(rest_url('wp-ai-chatbot/v1/chat')),
            'nonce'          => wp_create_nonce('wp_rest'),
            'botTitle'       => esc_html($settings['bot_title']),
            'welcomeMessage' => esc_html($settings['welcome_message']),
            'placeholder'    => esc_html($settings['placeholder']),
            'themeColor'     => esc_attr($settings['theme_color']),
            'position'       => esc_attr($settings['position']),
            'suggestions'    => $clean_suggestions,
        ]);

        return '<div class="wp-ai-chat-inline-placeholder" data-wp-ai-chat="true"></div>';
    }
}

// Boot plugin
add_action('plugins_loaded', ['WP_AI_Chatbot', 'instance']);
