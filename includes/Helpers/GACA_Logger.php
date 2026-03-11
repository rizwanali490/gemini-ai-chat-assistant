<?php
/**
 * Logger class for the Gemini AI Chat Assistant plugin.
 *
 * Provides a simple utility for logging messages to the WordPress debug log.
 *
 * @package Gemini_AI_Chat_Assistant
 * @subpackage Gemini_AI_Chat_Assistant/includes/Helpers
 * @author Rizwan ilyas <rizwan@rizwandevs.com>
 */
namespace GeminiAI\ChatAssistant\Helpers;
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GACA_Logger {

	/**
	 * Log a message to the WordPress debug log (debug.log).
	 *
	 * Ensure WP_DEBUG and WP_DEBUG_LOG are set to true in wp-config.php for this to work.
	 *
	 * @param mixed $message The message or data to log. Can be a string, array, or object.
	 */
	public static function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG === true ) {
			if ( is_array( $message ) || is_object( $message ) ) {
				error_log( print_r( $message, true ) );
			} else {
				error_log( $message );
			}
		}
	}

	/**
	 * Log a specific error message.
	 *
	 * @param string $error_message The error message.
	 * @param array  $context       Optional. Additional context for the error.
	 */
	public static function error( $error_message, $context = array() ) {
		$log_message = 'ERROR: ' . $error_message;
		if ( ! empty( $context ) ) {
			$log_message .= ' Context: ' . print_r( $context, true );
		}
		self::log( $log_message );
	}

	/**
	 * Log an informational message.
	 *
	 * @param string $info_message The informational message.
	 * @param array  $context      Optional. Additional context.
	 */
	public static function info( $info_message, $context = array() ) {
		$log_message = 'INFO: ' . $info_message;
		if ( ! empty( $context ) ) {
			$log_message .= ' Context: ' . print_r( $context, true );
		}
		self::log( $log_message );
	}
}