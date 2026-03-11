<?php
/**
 * Conversation Model for the Gemini AI Chat Assistant plugin.
 *
 * This class handles database operations for storing and retrieving
 * chat conversation messages.
 *
 * @package Gemini_AI_Chat_Assistant
 * @subpackage Gemini_AI_Chat_Assistant/includes/Models
 * @author Rizwan ilyas <rizwan@rizwandevs.com>
 */

namespace GeminiAI\ChatAssistant\Models;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use GeminiAI\ChatAssistant\Helpers\GACA_Logger; // Use our logger for database errors.

class GACA_Conversation_Model {

	/**
	 * The name of the database table for conversations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $table_name    The name of the database table.
	 */
	private static $table_name;

	/**
	 * Constructor. Sets the table name.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		global $wpdb;
		self::$table_name = $wpdb->prefix . 'gaca_conversations';
	}

	/**
	 * Static method to initialize the table name for static method calls.
	 *
	 * @since    1.0.0
	 */
	private static function init_table_name() {
		if ( ! isset( self::$table_name ) ) {
			global $wpdb;
			self::$table_name = $wpdb->prefix . 'gaca_conversations';
		}
	}

	/**
	 * Creates the database table for storing conversations during plugin activation.
	 *
	 * @since    1.0.0
	 */
	public static function create_table() {
		global $wpdb;
		self::init_table_name();

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE " . self::$table_name . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT 0 NOT NULL,
			session_id varchar(255) DEFAULT '' NOT NULL,
			topic varchar(255) DEFAULT '' NOT NULL,
			message_type varchar(50) DEFAULT 'user' NOT NULL, -- 'user' or 'ai'
			message_content longtext NOT NULL,
			file_url varchar(2048) DEFAULT '' NOT NULL, -- To store URL of uploaded file
			file_name varchar(255) DEFAULT '' NOT NULL,
			file_type varchar(255) DEFAULT '' NOT NULL,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY session_id (session_id),
			KEY topic (topic)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Check if the table was created successfully.
		if ( $wpdb->last_error ) {
			GACA_Logger::error( 'Failed to create database table: ' . $wpdb->last_error );
		} else {
			GACA_Logger::info( 'Database table ' . self::$table_name . ' created/updated successfully.' );
		}
	}

	/**
	 * Saves a chat message to the database.
	 *
	 * @since    1.0.0
	 * @param    int    $user_id         The ID of the user (0 for guests).
	 * @param    string $session_id      Unique session ID for guest users, or 'user_{id}' for logged-in.
	 * @param    string $topic           The chat topic.
	 * @param    string $message_type    'user' or 'ai'.
	 * @param    string $message_content The content of the message.
	 * @param    array  $file_data       Optional. Array with 'url', 'name' and 'type' for uploaded files.
	 * @return   int|false               The ID of the inserted row on success, false on failure.
	 */
	public static function save_message( $user_id, $session_id, $topic, $message_type, $message_content, $file_data = array() ) {
		global $wpdb;
		self::init_table_name();

		$data = array(
			'user_id'         => $user_id,
			'session_id'      => $session_id,
			'topic'           => $topic,
			'message_type'    => $message_type,
			'message_content' => $message_content,
			'timestamp'       => current_time( 'mysql', true ), // UTC time.
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s' );

		if ( ! empty( $file_data ) && is_array( $file_data ) ) {
			$data['file_url']  = isset( $file_data['url'] ) ? esc_url_raw( $file_data['url'] ) : '';
			$data['file_type'] = isset( $file_data['type'] ) ? sanitize_mime_type( $file_data['type'] ) : '';
			$data['file_name'] = isset( $file_data['name'] ) ? sanitize_text_field( $file_data['name'] ) : '';
			array_push( $format, '%s', '%s', '%s' );
		}

		$inserted = $wpdb->insert(
			self::$table_name,
			$data,
			$format
		);

		if ( false === $inserted ) {
			GACA_Logger::error( 'Failed to save chat message to database: ' . $wpdb->last_error, $data );
		} else {
			GACA_Logger::info( 'Chat message saved to database. ID: ' . $wpdb->insert_id );
		}

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Retrieves conversation history for a given user/session and topic.
	 *
	 * @since    1.0.0
	 * @param    int    $user_id     The ID of the user (0 for guests).
	 * @param    string $session_id  Unique session ID for guest users, or 'user_{id}' for logged-in.
	 * @param    string $topic       The chat topic.
	 * @param    int    $limit       Optional. Max number of messages to retrieve. Default 50.
	 * @return   array               An array of conversation messages, empty array if none found.
	 */
	public static function get_conversation_history( $user_id, $session_id, $topic, $limit = 50 ) {
		global $wpdb;
		self::init_table_name();

		$sql_base = "SELECT message_type, message_content, file_url, file_type, file_name, timestamp FROM " . self::$table_name;
		$sql_where = " WHERE topic = %s"; // topic is always part of the where clause
		$sql_order_limit = " ORDER BY timestamp DESC LIMIT %d";

		$prepare_values = array();

		if ( $user_id > 0 ) {
			$sql_where = " WHERE user_id = %d AND topic = %s";
			$prepare_values[] = $user_id;
		} else {
			$sql_where = " WHERE session_id = %s AND topic = %s";
			$prepare_values[] = $session_id;
		}

		$prepare_values[] = $topic; // Add topic value
		$prepare_values[] = $limit; // Add limit value

		// Now combine all parts and prepare
		$sql = $wpdb->prepare(
			$sql_base . $sql_where . $sql_order_limit,
			...$prepare_values // This will now correctly unpack all values
		);

		$results = $wpdb->get_results( $sql, ARRAY_A );

		if ( $wpdb->last_error ) {
			GACA_Logger::error( 'Failed to retrieve conversation history: ' . $wpdb->last_error );
			return array();
		}

		GACA_Logger::info( 'Retrieved ' . count( $results ) . ' messages for user/session: ' . ( $user_id > 0 ? $user_id : $session_id ) . ', topic: ' . $topic );
		return array_reverse($results);
	}

	/**
	 * Retrieves all unique topics for a given user or session.
	 *
	 * @since    1.0.0
	 * @param    int    $user_id     The ID of the user (0 for guests).
	 * @param    string $session_id  Unique session ID for guest users, or 'user_{id}' for logged-in.
	 * @return   array               An array of unique topic strings.
	 */
	public static function get_user_topics( $user_id, $session_id ) {
		global $wpdb;
		self::init_table_name();

		$where_clause = '';
		$query_args   = array();
		$query_formats = array();

		if ( $user_id > 0 ) {
			$where_clause .= 'user_id = %d';
			$query_args[] = $user_id;
			$query_formats[] = '%d';
		} else {
			$where_clause .= 'session_id = %s';
			$query_args[] = $session_id;
			$query_formats[] = '%s';
		}

		$query_args_formatted = array_merge( $query_formats, $query_args );

		$sql = $wpdb->prepare(
			"SELECT DISTINCT topic
			FROM " . self::$table_name . "
			WHERE {$where_clause}
			ORDER BY topic ASC",
			...$query_args_formatted
		);

		$results = $wpdb->get_col( $sql );

		if ( $wpdb->last_error ) {
			GACA_Logger::error( 'Failed to retrieve user topics: ' . $wpdb->last_error );
			return array();
		}

		return $results;
	}
}

// Instantiate the model to ensure static properties are initialized if needed,
// though direct static calls typically handle this.
new GACA_Conversation_Model();