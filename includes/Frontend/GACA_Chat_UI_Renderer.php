<?php
/**
 * Frontend Chat UI Renderer for the Gemini AI Chat Assistant plugin.
 *
 * This class handles enqueuing public-facing stylesheets and scripts,
 * and rendering the main container for the chat interface.
 *
 * @package Gemini_AI_Chat_Assistant
 * @subpackage Gemini_AI_Chat_Assistant/includes/Frontend
 * @author Rizwan ilyas <rizwan@rizwandevs.com>
 */

namespace GeminiAI\ChatAssistant\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GACA_Chat_UI_Renderer {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of the plugin.
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    string    $plugin_name    The name of the plugin.
	 * @param    string    $version        The version of the plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Enqueue public-facing stylesheets.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name . '-chat-ui-styles',
			GACA_PLUGIN_URL . 'assets/css/chat-ui.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Enqueue public-facing JavaScript.
	 *
	 * @since    1.0.0
    */
    public function enqueue_scripts() {
            wp_enqueue_script(
                $this->plugin_name . '-chat-ui-scripts',
                GACA_PLUGIN_URL . 'assets/js/chat-ui.js',
                array( 'jquery' ), // Depends on jQuery for now
                $this->version,
                true // Enqueue in the footer
            );
    
            // Localize script with necessary data for the frontend.
            wp_localize_script(
                $this->plugin_name . '-chat-ui-scripts',
                'gacaChat',
                array(
                    'rest_url' => rest_url( 'gemini-ai/v1/chat' ), // Correct REST API endpoint for chat.
					'history_rest_url' => rest_url( 'gemini-ai/v1/history' ), // History REST API endpoint.
                    'nonce'    => wp_create_nonce( 'wp_rest' ),    // Use 'wp_rest' nonce for REST API.
                    'is_user_logged_in' => is_user_logged_in(),
                    'public_char_limit' => (int) get_option( 'gaca_public_char_limit', 500 ),
                    'topics' => array( // Predefined topics
                        'tax'                 => esc_html__( 'Tax', 'gemini-ai-chat-assistant' ),
                        'marketing'           => esc_html__( 'Marketing', 'gemini-ai-chat-assistant' ),
                        'cashflow'            => esc_html__( 'Cashflow', 'gemini-ai-chat-assistant' ),
                        'management_consulting' => esc_html__( 'Management Consulting', 'gemini-ai-chat-assistant' ),
                    ),
                    'messages' => array( // Frontend messages for localization
                        'loading' => esc_html__( 'Thinking...', 'gemini-ai-chat-assistant' ),
                        'error'   => esc_html__( 'An error occurred. Please try again.', 'gemini-ai-chat-assistant' ),
                        'character_limit_reached' => sprintf( esc_html__( 'As a public user, your response is limited to %d characters. Please log in for full access.', 'gemini-ai-chat-assistant' ), (int) get_option( 'gaca_public_char_limit', 500 ) ),
                        'upload_not_allowed' => esc_html__( 'File uploads are only available for logged-in members.', 'gemini-ai-chat-assistant' ),
						'initial_greeting' => esc_html__( 'Hello! How can I assist you today?', 'gemini-ai-chat-assistant' ),
						'switched_topic' => esc_html__( 'Switched topic to:', 'gemini-ai-chat-assistant' ),
						'no_history' => esc_html__( 'No history for this topic. Start a new conversation!', 'gemini-ai-chat-assistant' ),
                    ),
                )
            );
        }

	/**
     * Conditionally enqueues styles and scripts only if the shortcode is present on the page.
     * This method is added as a filter to 'the_content' to detect the shortcode.
     *
     * @since 1.0.0
     * @param string $content The post content.
     * @return string The post content.
     */
    public function maybe_add_chat_shortcode_styles_scripts( $content ) {
        // Check if the shortcode exists in the content.
        if ( has_shortcode( $content, 'gemini_ai_chat' ) ) {
            // Enqueue styles and scripts only if the shortcode is found.
            $this->enqueue_styles();
            $this->enqueue_scripts();
        }
        return $content;
    }

    /**
     * Renders the chat interface HTML for the shortcode.
     *
     * @since    1.0.0
     * @return   string   The HTML output of the chat interface.
     */
    public function render_chat_shortcode() {
        ob_start(); // Start output buffering.
        include GACA_PLUGIN_DIR . 'templates/chat-interface.php'; // Include the HTML template.
        return ob_get_clean(); // Return the buffered content.
    }
}