<?php
/**
 * REST API Controller for the Gemini AI Chat Assistant plugin.
 *
 * This class registers custom REST API routes for handling chat messages,
 * conversation history, and file uploads. It acts as the bridge between
 * the frontend JavaScript and the backend PHP logic (including the Gemini API).
 *
 * @package Gemini_AI_Chat_Assistant
 * @subpackage Gemini_AI_Chat_Assistant/includes/REST
 * @author Rizwan ilyas <rizwan@rizwandevs.com>
 */

namespace GeminiAI\ChatAssistant\REST;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use GeminiAI\ChatAssistant\API\GACA_Gemini_API_Client;
use GeminiAI\ChatAssistant\Helpers\GACA_Logger;

// We will need these later for history and user management.
use GeminiAI\ChatAssistant\Models\GACA_Conversation_Model; 
// use GeminiAI\ChatAssistant\Models\Conversation;
// use GeminiAI\ChatAssistant\Admin\GACA_User_Roles_Manager;

class GACA_REST_API_Controller {

	/**
	 * REST API Namespace.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $namespace    The namespace for the REST API routes.
	 */
	private $namespace;

	/**
	 * REST API Version.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version      The version for the REST API routes.
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->namespace = 'gemini-ai/v1';
		$this->version   = '1';
	}

	/**
	 * Register the REST API routes.
	 *
	 * @since    1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/chat',
			array(
				'methods'             => \WP_REST_Server::CREATABLE, // POST request.
				'callback'            => array( $this, 'handle_chat_message' ),
				'permission_callback' => array( $this, 'chat_permissions_check' ),
				'args'                => array(
					'message' => array(
						'sanitize_callback' => 'sanitize_text_field', // Basic sanitization.
						'validate_callback' => function( $value ) {
							if ( empty( $value ) && empty( $_FILES['file'] ) ) {
								return false; // Message is required if no file uploaded
							}
							return is_string( $value );
						},
						'required'          => true,
						'description'       => esc_html__( 'The user\'s chat message.', 'gemini-ai-chat-assistant' ),
					),
					'topic'   => array(
						'sanitize_callback' => 'sanitize_key', // Sanitize topic slug.
						'validate_callback' => function( $value ) {
							$allowed_topics = array( 'tax', 'marketing', 'cashflow', 'management_consulting' );
							return in_array( $value, $allowed_topics, true );
						},
						'required'          => true,
						'description'       => esc_html__( 'The selected chat topic.', 'gemini-ai-chat-assistant' ),
					),
					// File argument will be handled separately due to multipart/form-data.
					// We'll validate and process it in handle_chat_message.
				),
			)
		);

		//Route for getting conversation history.
		register_rest_route(
			$this->namespace,
			'/history',
			array(
				'methods'             => \WP_REST_Server::READABLE, // GET request.
				'callback'            => array( $this, 'get_conversation_history' ),
				'permission_callback' => array( $this, 'chat_permissions_check' ), // Same permission as chat for now.
				'args'                => array(
					'topic' => array(
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => function( $value ) {
							// Allow empty string for initial load to get all topics, or specific topics.
							return is_string( $value );
						},
						'required'          => false,
						'description'       => esc_html__( 'The chat topic to retrieve history for.', 'gemini-ai-chat-assistant' ),
					),
				),
			)
		);

		// We will add routes for history later.
		/*
		register_rest_route(
			$this->namespace,
			'/history',
			array(
				'methods'             => \WP_REST_Server::READABLE, // GET request.
				'callback'            => array( $this, 'get_conversation_history' ),
				'permission_callback' => array( $this, 'history_permissions_check' ),
			)
		);
		*/
	}

	/**
	 * Permission callback for the chat endpoint.
	 *
	 * Ensures the request is authenticated with a valid nonce.
	 * Further checks for logged-in status vs. public can be done in the handler.
	 *
	 * @since    1.0.0
	 * @param    \WP_REST_Request    $request    The request object.
	 * @return   true|\WP_Error                  True if permission is granted, WP_Error otherwise.
	 */
		// NEW: Add the get_conversation_history method.
	public function get_conversation_history( \WP_REST_Request $request ) {
		$topic = $request->get_param( 'topic' );
		$user_id = get_current_user_id();

		$session_id_cookie_name = 'gaca_session_id';
		$session_id = '';

		if ( $user_id ) {
			$session_id = 'user_' . $user_id;
		} else {
			if ( isset( $_COOKIE[ $session_id_cookie_name ] ) ) {
				$session_id = sanitize_key( $_COOKIE[ $session_id_cookie_name ] );
			} else {
				// No session ID for guest, no history to load.
				return new \WP_REST_Response( array( 'success' => true, 'data' => array( 'history' => array() ) ), 200 );
			}
		}

		if ( empty( $topic ) ) {
			// If topic is empty, fetch all unique topics for the user/session.
			$topics_found = GACA_Conversation_Model::get_user_topics( $user_id, $session_id );
			return new \WP_REST_Response(
				array(
					'success' => true,
					'data'    => array( 'topics' => $topics_found ),
				),
				200
			);
		} else {
			// Fetch history for a specific topic.
			$chat_messages_limit = get_option( 'gaca_user_chat_messages_limit', 50 );

			$history = GACA_Conversation_Model::get_conversation_history( $user_id, $session_id, $topic, $chat_messages_limit);
			return new \WP_REST_Response(
				array(
					'success' => true,
					'data'    => array( 'history' => $history ),
				),
				200
			);
		}
	}
	public function chat_permissions_check( \WP_REST_Request $request ) {
		// Nonce for REST API is typically sent in the 'X-WP-Nonce' header.
		// For frontend AJAX, it might also be sent in the request body.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			// Fallback: check if nonce is in POST body (as our JS currently sends it, for simplicity).
			// Ideally, for REST API, use the header. We'll modify JS next.
			$nonce = $request->get_param( '_wpnonce' ); // Standard REST API nonce param name
		}

		// Use 'wp_rest' as the action for nonce verification for REST API endpoints.
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) { // <--- CHANGED NONCE ACTION HERE
			GACA_Logger::error( 'Chat permission check failed: Invalid nonce.', array( 'nonce_received' => $nonce ) );
			return new \WP_Error(
				'rest_forbidden',
				esc_html__( 'Invalid nonce. Please refresh the page and try again.', 'gemini-ai-chat-assistant' ),
				array( 'status' => 403 )
			);
		}

		// Basic check: anyone can send a chat message if nonce is valid.
		// Further logic for character limits, file uploads, etc., will be in handle_chat_message.
		return true;
	}

	/**
	 * Handles the incoming chat message request.
	 *
	 * This is where the core logic for interacting with Gemini AI will reside.
	 *
	 * @since    1.0.0
	 * @param    \WP_REST_Request    $request    The request object.
	 * @return   \WP_REST_Response|\WP_Error     The response object or WP_Error on failure.
	 */
	public function handle_chat_message( \WP_REST_Request $request ) {

		if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }
        if ( ! function_exists( 'wp_get_image_editor' ) ) { // Function from image.php
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
        }
        if ( ! function_exists( 'media_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
        }
        if ( ! function_exists( 'wp_insert_attachment' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/post.php' ); // For wp_insert_attachment and related functions
        }

		$message    = sanitize_textarea_field( $request->get_param( 'message' ) );
		$topic      = sanitize_text_field( $request->get_param( 'topic' ) );
		$user_id    = get_current_user_id();

		// For logged-in users, their session ID can be derived from their user ID.
		// For simplicity for now, we'll use a cookie for guests.
		$session_id_cookie_name = 'gaca_session_id';
		$session_id = '';

		if ( $user_id ) {
			$session_id = 'user_' . $user_id; // Unique session ID for logged-in users.
		} else {
			if ( isset( $_COOKIE[ $session_id_cookie_name ] ) ) {
				$session_id = sanitize_key( $_COOKIE[ $session_id_cookie_name ] );
			} else {
				$session_id = uniqid( 'guest_', true ); // Generate a unique ID for new guests.
				// Set a cookie for 30 days to persist the guest session.
				setcookie( $session_id_cookie_name, $session_id, time() + ( DAY_IN_SECONDS * 30 ), COOKIEPATH, COOKIE_DOMAIN );
			}
		}

		if ( empty( $message ) && empty( $request->get_file_params() ) ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'Message or file required.' ), 400 );
		}

		GACA_Logger::info( "Received chat message. User ID: {$user_id}, Topic: {$topic}, Message: {$message}" );

		// --- File Upload Handling (for logged-in users) ---
		$file_name = null;
		$image_file_data = null;
		$uploaded_file_data = null;
		$attachment_id = 0;

		// Check if file upload is allowed for this user.
		// For now, assume logged-in users can upload.
		if ( $user_id && isset( $_FILES['file'] ) && ! empty( $_FILES['file']['name'] ) ) {

			$file_name = sanitize_file_name( $_FILES['file']['name'] );
			$file_type = wp_check_filetype( $file_name );

			if ( ! $file_type['ext'] || ! in_array( $file_type['ext'], array( 'jpg', 'jpeg', 'png'), true ) ) {
				GACA_Logger::error( 'File upload failed: Invalid file type.', array( 'file_name' => $file_name, 'file_type' => $file_type['ext'] ) );
				return new \WP_REST_Response(
					array(
						'success' => false,
						'data'    => array( 'message' => esc_html__( 'Invalid file type. Only images (JPG, JPEG, PNG) are allowed.', 'gemini-ai-chat-assistant' ) ),
					),
					400
				);
			}

			$image_file_data = file_get_contents( $_FILES['file']['tmp_name'] );
			$image_file_mime_type = $file_type['type'];

			if ( $image_file_data === false ) {
				GACA_Logger::error( 'Failed to read uploaded image file.', array( 'image_name' => $file_name ) );
				return new \WP_REST_Response( array( 'success' => false, 'message' => 'Failed to process the uploaded image file.' ), 500 );
			}
			
			// Use WordPress's media_handle_upload to move the file to the uploads directory and create an attachment post.
            // Set up the $_POST array expected by media_handle_upload.
            $upload_overrides = array( 'test_form' => false );
            $uploaded_file_info = wp_handle_upload( $_FILES['file'], $upload_overrides );

            if ( isset( $uploaded_file_info['error'] ) ) {
                 GACA_Logger::error( 'wp_handle_upload error: ' . $uploaded_file_info['error'], array( 'file_name' => $file_name ) );
                 return new \WP_REST_Response(
                    array(
                        'success' => false,
                        'data'    => array( 'message' => esc_html__( 'File upload failed during processing: ', 'gemini-ai-chat-assistant' ) . $uploaded_file_info['error'] ),
                    ),
                    500
                );
            }

            // If wp_handle_upload was successful, insert the file as an attachment.
            $attachment = array(
                'guid'           => $uploaded_file_info['url'],
                'post_mime_type' => $uploaded_file_info['type'],
                'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $uploaded_file_info['file'] ) ),
                'post_content'   => '',
                'post_status'    => 'inherit',
            );

            // Insert the attachment into the database.
            $attachment_id = wp_insert_attachment( $attachment, $uploaded_file_info['file'], 0 ); // 0 means no parent post.

            if ( is_wp_error( $attachment_id ) ) {
                GACA_Logger::error( 'wp_insert_attachment error: ' . $attachment_id->get_error_message(), array( 'file_name' => $file_name ) );
                return new \WP_REST_Response(
                    array(
                        'success' => false,
                        'data'    => array( 'message' => esc_html__( 'File attachment failed: ', 'gemini-ai-chat-assistant' ) . $attachment_id->get_error_message() ),
                    ),
                    500
                );
            }

			// Generate attachment metadata and attach it to the attachment post.
            require_once( ABSPATH . 'wp-admin/includes/image.php' ); // Ensure image.php is loaded for wp_generate_attachment_metadata
            $attachment_data = wp_generate_attachment_metadata( $attachment_id, $uploaded_file_info['file'] );
            wp_update_attachment_metadata( $attachment_id, $attachment_data );

			$uploaded_file_data = array(
				'id'   => $attachment_id,
				'url'  => wp_get_attachment_url( $attachment_id ),
				'type' => get_post_mime_type( $attachment_id ),
				'name' => get_the_title( $attachment_id ),
			);
			GACA_Logger::info( 'File uploaded successfully.', array( 'file_id' => $attachment_id, 'file_url' => $uploaded_file_data['url'] ) );
		} elseif ( ! $user_id && isset( $_FILES['file'] ) && ! empty( $_FILES['file']['name'] ) ) {
			// Public user trying to upload a file.
			GACA_Logger::warning( 'Public user attempted file upload.', array( 'user_ip' => $_SERVER['REMOTE_ADDR'] ) );
			return new \WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => esc_html__( 'File uploads are only available for logged-in members.', 'gemini-ai-chat-assistant' ) ),
				),
				403
			);
		}

		// Save user message to database.
		GACA_Conversation_Model::save_message( $user_id, $session_id, $topic, 'user', $message, $uploaded_file_data );

		
		// --- Get API Key ---
		$api_key = get_option( 'gaca_api_key', '' );
		if ( empty( $api_key ) ) {
			GACA_Logger::error( 'Gemini API Key is not set in plugin settings.' );
			return new \WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => esc_html__( 'Gemini AI is not configured. Please contact the site administrator.', 'gemini-ai-chat-assistant' ) ),
				),
				500
			);
		}
		
		// --- Get Vertex AI Keys ---
		$project_id = 'websiteaichat';
		$location 	= 'us-central1';
		$service_account_key_path = GACA_PLUGIN_DIR . 'config/websiteaichat-a7d2f563f2bd.json';
		
		// --- Call Gemini API ---
		try {
			$gemini_api_client = new GACA_Gemini_API_Client( $api_key, $project_id, $location, $service_account_key_path);

			// Prepare content for Gemini.
			$prompt_parts = array(
				array( 'text' => "Topic: {$topic}\nUser: {$message}" ),
			);

			// Add image data if uploaded.
			if ( $image_file_data ) {
				// For Gemini Vision, we need base64 encoded image data.
				// This is a simplified example. In a real scenario, you'd read the file content
				// and base64 encode it. For now, let's assume we'll pass the URL and let the client
				// handle it, or we'll fetch it here.
				// For direct API call, you'd need the base64 data.
				$image_data_base64 = base64_encode( $image_file_data );
				array_push($prompt_parts, array( 'inlineData' => array( 'mimeType' => $image_file_mime_type, 'data' => $image_data_base64 ) ));
				GACA_Logger::info( 'Image upload detected. Placeholder for image processing for Gemini Vision.', array( 'image_name' => $file_name, 'image_mime_type' => $image_file_mime_type ) );
				// $prompt_parts[0]['text'] .= "\nUser also provided an image:";
			}

			// Call the generateContent method on the Gemini API client.
			$ai_response_text = $gemini_api_client->generate_content( $prompt_parts, $user_id );

			if ( is_wp_error( $ai_response_text ) ) {
				GACA_Logger::error( 'Gemini API call failed: ' . $ai_response_text->get_error_message() );
				return new \WP_REST_Response(
					array(
						'success' => false,
						'data'    => array( 'message' => esc_html__( 'AI response error: ', 'gemini-ai-chat-assistant' ) . $ai_response_text->get_error_message() ),
					),
					500
				);
			}
			
			// --- Apply Character Limit for Public Users ---
			// if ( ! $user_id ) { // If user is not logged in.
			// 	$char_limit = (int) get_option( 'gaca_public_char_limit', 500 );
			// 	if ( mb_strlen( $ai_response_text ) > $char_limit ) {
			// 		// Correct way to handle the character limit message.
			// 		$ai_response_text = mb_substr( $ai_response_text, 0, $char_limit );
			// 		$ai_response_text .= '<p><em>' . sprintf( esc_html__( 'As a public user, your response is limited to %d characters. Please log in for full access.', 'gemini-ai-chat-assistant' ), $char_limit ) . '</em></p>';
			// 	}
			// }

			// Add a log entry for the raw response from Gemini:
			GACA_Logger::info( 'Gemini AI raw response text: ' . $ai_response_text );

			//Convert Markdown to HTML for proper display
            $parsedown = new \Parsedown();
            $formatted_ai_response_text = $parsedown->text( $ai_response_text );
			
			// Sanitize the AI response text using wp_kses_post.
			$sanitized_ai_response_text = wp_kses_post( $formatted_ai_response_text );
			
			// --- Save Conversation History (for logged-in users) ---
			if ( $user_id ) {
				// Implement history saving here. This will involve a database interaction.
				// For now, just a log.
				GACA_Logger::info( 'Saving conversation history.' );
			}

			GACA_Conversation_Model::save_message( $user_id, $session_id, $topic, 'ai', $sanitized_ai_response_text );

			return new \WP_REST_Response(
				array(
					'success' => true,
					'data'    => array( 'response' => $sanitized_ai_response_text ), // Use $sanitized_ai_response_text for frontend
				),
				200
			);

		} catch ( \Exception $e ) {
			GACA_Logger::error( 'Unhandled exception in chat handler: ' . $e->getMessage(), array( 'code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine() ) );
			return new \WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'message' => esc_html__( 'An unexpected server error occurred.', 'gemini-ai-chat-assistant' ) . ' ' . $e->getMessage() ),
				),
				500
			);
		}
	}

	/**
	 * Placeholder for history permissions check.
	 *
	 * @since    1.0.0
	 * @param    \WP_REST_Request    $request    The request object.
	 * @return   true|\WP_Error                  True if permission is granted, WP_Error otherwise.
	 */
	public function history_permissions_check( \WP_REST_Request $request ) {
		// Only logged-in users can access history.
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_forbidden',
				esc_html__( 'You must be logged in to view conversation history.', 'gemini-ai-chat-assistant' ),
				array( 'status' => 401 )
			);
		}
		return true;
	}
}