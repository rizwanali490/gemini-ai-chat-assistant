<?php
/**
 * Plugin Name: Gemini AI 2.5 Chat Assistant
 * Plugin URI:  https://rizwandevs.com/
 * Description: Integrates Gemini AI 2.5 via Google Cloud Generative Language API to provide a conversational AI assistant.
 * Version:     1.0.0
 * Author:      Rizwan ilyas
 * Author URI:  https://rizwandevs.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: gemini-ai-chat-assistant
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define constants.
 */
define( 'GACA_VERSION', '1.0.0' );
define( 'GACA_PLUGIN_FILE', __FILE__ );
define( 'GACA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GACA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GACA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoload Composer dependencies.
 * This will also autoload our own classes via PSR-4.
 */
if ( file_exists( GACA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once GACA_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    add_action( 'admin_notices', function() {
        $message = sprintf(
            esc_html__( 'Gemini AI Chat Assistant requires Composer dependencies to be installed. Please run %s in the plugin directory.', 'gemini-ai-chat-assistant' ),
            '<code>composer install</code>'
        );
        echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
    });
    return; // Stop plugin execution if dependencies are missing.
}

// Use statements for namespaced classes.
// These allow us to refer to the classes by their short names later.
use GeminiAI\ChatAssistant\Admin\GACA_Admin_Settings_Page;
use GeminiAI\ChatAssistant\API\GACA_Gemini_API_Client;
use GeminiAI\ChatAssistant\REST\GACA_REST_API_Controller; 
use GeminiAI\ChatAssistant\Frontend\GACA_Chat_UI_Renderer;
use GeminiAI\ChatAssistant\Helpers\GACA_Logger;


/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing hooks.
 */
// The main plugin class itself does not need a namespace if it's the global entry point.
// However, its file (class-gemini-ai-chat-assistant.php) is not autoloaded by PSR-4 in this setup,
// so it still needs an explicit require_once.
require_once GACA_PLUGIN_DIR . 'includes/class-gemini-ai-chat-assistant-loader.php'; // This loader class is also not namespaced/autoloaded.
require_once GACA_PLUGIN_DIR . 'includes/class-gemini-ai-chat-assistant.php'; // Explicitly require the main plugin class.
// Include the Parsedown library for Markdown to HTML conversion.
require_once GACA_PLUGIN_DIR . 'includes/lib/Parsedown.php';

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
function run_gemini_ai_chat_assistant() {
	$plugin = new \Gemini_AI_Chat_Assistant(); // Note the backslash for global namespace
	$plugin->run();
}
run_gemini_ai_chat_assistant();

/**
 * The code that runs during plugin activation.
 *
 * @since    1.0.0
 */
function activate_gemini_ai_chat_assistant() {
	\Gemini_AI_Chat_Assistant::activate();
}
register_activation_hook( __FILE__, 'activate_gemini_ai_chat_assistant' );

/**
 * The code that runs during plugin deactivation.
 *
 * @since    1.0.0
 */
function deactivate_gemini_ai_chat_assistant() {
	\Gemini_AI_Chat_Assistant::deactivate();
}
register_deactivation_hook( __FILE__, 'deactivate_gemini_ai_chat_assistant' );