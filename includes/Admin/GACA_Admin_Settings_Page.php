<?php
/**
 * Admin Settings Page for the Gemini AI Chat Assistant plugin.
 *
 * This class handles the creation of the admin menu page,
 * registration of plugin settings, and the logic for saving/retrieving them.
 * It also manages the API connection test functionality.
 *
 * @package Gemini_AI_Chat_Assistant
 * @subpackage Gemini_AI_Chat_Assistant/includes/Admin
 * @author Rizwan ilyas <rizwan@rizwandevs.com>
 */
namespace GeminiAI\ChatAssistant\Admin; 
use GeminiAI\ChatAssistant\API\GACA_Gemini_API_Client;
use GeminiAI\ChatAssistant\Helpers\GACA_Logger;
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GACA_Admin_Settings_Page {

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
	 * Add the plugin's admin menu page.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {
		add_menu_page(
			esc_html__( 'Gemini AI Chat Settings', 'gemini-ai-chat-assistant' ), // Page title
			esc_html__( 'Gemini AI Chat', 'gemini-ai-chat-assistant' ),    // Menu title
			'manage_options',                                                // Capability required to access
			$this->plugin_name . '-settings',                                // Menu slug
			array( $this, 'display_settings_page' ),                        // Callback function to render page
			'dashicons-format-chat',                                         // Icon
			80                                                               // Position
		);
	}

	/**
	 * Register plugin settings and sections.
	 *
	 * @since    1.0.0
	 */
	public function settings_init() {
		// Register a setting section for API settings.
		add_settings_section(
			'gaca_api_settings_section',                                     // ID
			esc_html__( 'Gemini AI API Settings', 'gemini-ai-chat-assistant' ), // Title
			array( $this, 'api_settings_section_callback' ),                // Callback to render section description
			$this->plugin_name . '-settings'                                 // Page slug
		);

		// Register a setting field for the Gemini API Key.
		add_settings_field(
			'gaca_api_key',                                                  // ID
			esc_html__( 'Gemini API Key', 'gemini-ai-chat-assistant' ),     // Title
			array( $this, 'api_key_callback' ),                             // Callback to render field
			$this->plugin_name . '-settings',                                // Page slug
			'gaca_api_settings_section',                                     // Section ID
			array(
				'label_for' => 'gaca_api_key',
				'class'     => 'gaca-api-key-field',
			)
		);

		// Register a setting field for the Public User Character Limit.
		add_settings_field(
			'gaca_public_char_limit',                                        // ID
			esc_html__( 'Public User Token Limit', 'gemini-ai-chat-assistant' ), // Title
			array( $this, 'public_char_limit_callback' ),                   // Callback to render field
			$this->plugin_name . '-settings',                                // Page slug
			'gaca_api_settings_section',                                     // Section ID
			array(
				'label_for' => 'gaca_public_char_limit',
				'class'     => 'gaca-public-char-limit-field',
			)
		);

		// Register a setting field for the Member Users Character Limit.
		add_settings_field(
			'gaca_member_user_char_limit',                                        // ID
			esc_html__( 'Member Users Token Limit', 'gemini-ai-chat-assistant' ), // Title
			array( $this, 'member_user_char_limit_callback' ),                   // Callback to render field
			$this->plugin_name . '-settings',                                // Page slug
			'gaca_api_settings_section',                                     // Section ID
			array(
				'label_for' => 'gaca_member_user_char_limit',
				'class'     => 'gaca-member-user-char-limit-field',
			)
		);

		// Register a setting field for the chat messages Limit.
		add_settings_field(
			'gaca_user_chat_messages_limit',                                        // ID
			esc_html__( 'Users Chat Message Limit', 'gemini-ai-chat-assistant' ), // Title
			array( $this, 'user_chat_messages_limit_callback' ),                   // Callback to render field
			$this->plugin_name . '-settings',                                // Page slug
			'gaca_api_settings_section',                                     // Section ID
			array(
				'label_for' => 'gaca_user_chat_messages_limit',
				'class'     => 'gaca-user-chat-messages-limit-field',
			)
		);

		// Shortcode to embed AI Chat assitant.
		add_settings_field(
			'gaca_shortcode',                                        // ID
			esc_html__( 'AI chat assistant shortcode', 'gemini-ai-chat-assistant' ), // Title
			array( $this, 'gaca_shortcode_callback' ),                   // Callback to render field
			$this->plugin_name . '-settings',                                // Page slug
			'gaca_api_settings_section',                                     // Section ID
			array(
				'label_for' => 'gaca_shortcode',
				'class'     => 'gaca-shortcode-field',
			)
		);

		// Register the settings.
		register_setting(
			$this->plugin_name . '-settings-group', // Option group
			'gaca_api_key',                          // Option name
			array( $this, 'sanitize_api_key' )       // Sanitize callback
		);
		register_setting(
			$this->plugin_name . '-settings-group', // Option group
			'gaca_public_char_limit',                // Option name
			array( $this, 'sanitize_token_limit' ) // Sanitize callback
		);
		register_setting(
			$this->plugin_name . '-settings-group', // Option group
			'gaca_member_user_char_limit',                // Option name
			array( $this, 'sanitize_token_limit' ) // Sanitize callback
		);
		register_setting(
			$this->plugin_name . '-settings-group', // Option group
			'gaca_user_chat_messages_limit',                // Option name
			array( $this, 'sanitize_token_limit' ) // Sanitize callback
		);
	}

	/**
	 * Callback for the API settings section description.
	 *
	 * @since    1.0.0
	 */
	public function api_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure your Gemini AI API key and other general settings.', 'gemini-ai-chat-assistant' ) . '</p>';
	}

	/**
	 * Callback to render the Gemini API Key field.
	 *
	 * @since    1.0.0
	 */
	public function api_key_callback() {
		$api_key = get_option( 'gaca_api_key', '' );
		echo '<input type="password" id="gaca_api_key" name="gaca_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text" placeholder="' . esc_attr__( 'Enter your Gemini API Key', 'gemini-ai-chat-assistant' ) . '">';
		echo '<p class="description">' . esc_html__( 'Enter your Google Cloud Generative Language API Key here. Keep this secure!', 'gemini-ai-chat-assistant' ) . '</p>';
		echo '<button type="button" id="gaca-test-api-connection" class="button button-secondary">' . esc_html__( 'Test API Connection', 'gemini-ai-chat-assistant' ) . '</button>';
		echo '<span id="gaca-api-test-status" style="margin-left: 10px;"></span>';
	}

	/**
	 * Callback to render the Public User Character Limit field.
	 *
	 * @since    1.0.0
	 */
	public function public_char_limit_callback() {
		$token_limit = get_option( 'gaca_public_char_limit', 500 );
		echo '<input type="number" id="gaca_public_char_limit" name="gaca_public_char_limit" value="' . esc_attr( $token_limit ) . '" class="small-text" min="100" step="100">';
		echo '<p class="description">' . esc_html__( 'Set the maximum token limit for AI responses to public (non-logged-in) users. (Default limit is 500.)', 'gemini-ai-chat-assistant' ) . '</p>';
	}
	
	public function member_user_char_limit_callback() {
		$token_limit = get_option( 'gaca_member_user_char_limit', 2000 );
		echo '<input type="number" id="gaca_member_user_char_limit" name="gaca_member_user_char_limit" value="' . esc_attr( $token_limit ) . '" class="small-text" min="100" step="100">';
		echo '<p class="description">' . esc_html__( 'Set the maximum token limit for AI responses to member (logged-in) users. (Default limit is 2000.)', 'gemini-ai-chat-assistant' ) . '</p>';
	}
	
	public function user_chat_messages_limit_callback() {
		$chat_messages_limit = get_option( 'gaca_user_chat_messages_limit', 50 );
		echo '<input type="number" id="gaca_user_chat_messages_limit" name="gaca_user_chat_messages_limit" value="' . esc_attr( $chat_messages_limit ) . '" class="small-text" min="50" step="10">';
		echo '<p class="description">' . esc_html__( 'Configure the maximum number of past conversation messages to load in the chat screen. (Default limit is 50.)', 'gemini-ai-chat-assistant' ) . '</p>';
	}
	
	public function gaca_shortcode_callback() {
		echo '<p class="description">' . wp_kses_post( 
			sprintf(
				__( 'Use the shortcode %s anywhere on your site to embed the AI chat assistant.', 'gemini-ai-chat-assistant' ),
				'<strong>[gemini_ai_chat]</strong>'
			)
		) . '</p>';
	}

	/**
	 * Sanitize the API Key.
	 *
	 * @since    1.0.0
	 * @param    string    $input    The raw API key input.
	 * @return   string              The sanitized API key.
	 */
	public function sanitize_api_key( $input ) {
		// Remove leading/trailing whitespace.
		$sanitized_key = trim( $input );
		// Basic alphanumeric check, more robust validation can be added if format is strict.
		// For API keys, often just trimming and ensuring it's a string is sufficient.
		return sanitize_text_field( $sanitized_key );
	}

	/**
	 * Sanitize the Public User Character Limit.
	 *
	 * @since    1.0.0
	 * @param    int    $input    The raw character limit input.
	 * @return   int              The sanitized character limit.
	 */
	public function sanitize_token_limit( $input ) {
		$sanitized_limit = absint( $input ); // Ensure it's a non-negative integer.
		return $sanitized_limit;
	}

	/**
	 * Display the plugin settings page.
	 *
	 * @since    1.0.0
	 */
	public function display_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( $this->plugin_name . '-settings-group' ); // Output security fields for the registered setting.
				do_settings_sections( $this->plugin_name . '-settings' );  // Output setting sections and their fields.
				submit_button();                                           // Output save button.
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueue admin-specific stylesheets.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name . '-admin-styles',
			GACA_PLUGIN_URL . 'assets/css/admin-settings.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Enqueue admin-specific JavaScript.
	 *
	 * @since    1.0.0
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only enqueue on our specific settings page.
		if ( 'toplevel_page_' . $this->plugin_name . '-settings' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name . '-admin-scripts',
			GACA_PLUGIN_URL . 'assets/js/admin-settings.js',
			array( 'jquery' ),
			$this->version,
			true // Enqueue in the footer.
		);

		// Pass data to our JavaScript.
		wp_localize_script(
			$this->plugin_name . '-admin-scripts',
			'gacaAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'gaca_api_test_nonce' ), // Create a nonce for security.
				'testing_message' => esc_html__( 'Testing connection...', 'gemini-ai-chat-assistant' ),
				'success_message' => esc_html__( 'Connection successful!', 'gemini-ai-chat-assistant' ),
				'failure_message' => esc_html__( 'Connection failed: ', 'gemini-ai-chat-assistant' ),
			)
		);
	}

	/**
	 * AJAX handler for testing the API connection.
	 *
	 * @since    1.0.0
	 */
	public function ajax_test_api_connection() {
		// Check nonce for security.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'gaca_api_test_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'gemini-ai-chat-assistant' ) ) );
		}

		// Check user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'gemini-ai-chat-assistant' ) ) );
		}

		$api_key = get_option( 'gaca_api_key', '' );

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'API Key is not set. Please save your API key first.', 'gemini-ai-chat-assistant' ) ) );
		}

		// Instantiate the API client and attempt a test connection.
		try {
			$gemini_api_client = new GACA_Gemini_API_Client( $api_key );
			$test_result       = $gemini_api_client->test_connection();

			if ( is_wp_error( $test_result ) ) {
				GACA_Logger::error( 'API Connection Test Failed: ' . $test_result->get_error_message() );
				wp_send_json_error( array( 'message' => $test_result->get_error_message() ) );
			} else {
				GACA_Logger::info( 'API Connection Test Successful.' );
				wp_send_json_success( array( 'message' => esc_html__( 'Connection successful!', 'gemini-ai-chat-assistant' ) ) );
			}
		} catch ( Exception $e ) {
			GACA_Logger::error( 'API Connection Test Exception: ' . $e->getMessage(), array( 'code' => $e->getCode() ) );
			wp_send_json_error( array( 'message' => esc_html__( 'An unexpected error occurred: ', 'gemini-ai-chat-assistant' ) . $e->getMessage() ) );
		}
	}
}