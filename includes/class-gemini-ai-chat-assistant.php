<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @package Gemini_AI_Chat_Assistant
 * @subpackage Gemini_AI_Chat_Assistant/includes
 * @author Rizwan ilyas <rizwan@rizwandevs.com>
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Use statements for namespaced classes.
use GeminiAI\ChatAssistant\Admin\GACA_Admin_Settings_Page;
use GeminiAI\ChatAssistant\API\GACA_Gemini_API_Client;
use GeminiAI\ChatAssistant\REST\GACA_REST_API_Controller;
use GeminiAI\ChatAssistant\Frontend\GACA_Chat_UI_Renderer;
use GeminiAI\ChatAssistant\Helpers\GACA_Logger;

class Gemini_AI_Chat_Assistant {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Gemini_AI_Chat_Assistant_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->plugin_name = 'gemini-ai-chat-assistant';
		$this->version     = GACA_VERSION; // Using the constant defined in the main plugin file.

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		//Register activation hook to create database table.
		register_activation_hook( GACA_PLUGIN_FILE, array( $this, 'activate_gaca' ) );
	}
	public function activate_gaca() {
		require_once GACA_PLUGIN_DIR . 'includes/Models/GACA_Conversation_Model.php';
		\GeminiAI\ChatAssistant\Models\GACA_Conversation_Model::create_table();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		/**
		 * The class responsible for orchestrating the hooks of the plugin.
		 */
		require_once GACA_PLUGIN_DIR . 'includes/class-gemini-ai-chat-assistant-loader.php';

		$this->loader = new Gemini_AI_Chat_Assistant_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$this->loader->add_action( 'plugins_loaded', $this, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$admin_settings_page = new GACA_Admin_Settings_Page( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_menu', $admin_settings_page, 'add_plugin_admin_menu' );
		$this->loader->add_action( 'admin_init', $admin_settings_page, 'settings_init' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin_settings_page, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin_settings_page, 'enqueue_scripts' );

		// Register AJAX handler for API connection test
		$this->loader->add_action( 'wp_ajax_gaca_test_api_connection', $admin_settings_page, 'ajax_test_api_connection' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$chat_ui_renderer = new GACA_Chat_UI_Renderer( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'wp_enqueue_scripts', $chat_ui_renderer, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $chat_ui_renderer, 'enqueue_scripts' );
		//$this->loader->add_action( 'wp_footer', $chat_ui_renderer, 'render_chat_interface_container' ); // Hook to render the main chat container
		
		// Initialize REST API endpoints
		$rest_controller = new GACA_REST_API_Controller();
		$this->loader->add_action( 'rest_api_init', $rest_controller, 'register_routes' );

		// Register the shortcode for the full-screen chat interface.
		$this->loader->add_filter( 'the_content', $chat_ui_renderer, 'maybe_add_chat_shortcode_styles_scripts', 10, 1 );
		add_shortcode( 'gemini_ai_chat', array( $chat_ui_renderer, 'render_chat_shortcode' ) );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of WordPress and
	 * to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Gemini_AI_Chat_Assistant_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Load the plugin text domain for internationalization.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			$this->plugin_name,
			false,
			dirname( GACA_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Fired during plugin activation.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Perform activation tasks here, e.g., create database tables, set default options.
		// For now, we'll just log.
		GACA_Logger::log( 'Gemini AI Chat Assistant activated.' );
		// Example: Add a default option if it doesn't exist
		add_option( 'gaca_api_key', '' );
		add_option( 'gaca_public_char_limit', 500 ); // Default char limit for public users
	}

	/**
	 * Fired during plugin deactivation.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Perform deactivation tasks here, e.g., clean up transient data.
		GACA_Logger::log( 'Gemini AI Chat Assistant deactivated.' );
		// No need to delete options on deactivation unless absolutely necessary,
		// as user might reactivate. Typically done on uninstall.
	}
}