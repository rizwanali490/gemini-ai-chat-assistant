<?php
/**
 * Gemini AI API Client for the Gemini AI Chat Assistant plugin.
 *
 * This class handles all direct interactions with the Google Generative Language API (Gemini 2.5).
 * It encapsulates API requests, responses, and error handling.
 *
 * @package Gemini_AI_Chat_Assistant
 * @subpackage Gemini_AI_Chat_Assistant/includes/API
 * @author Rizwan ilyas <rizwan@rizwandevs.com>
 */

namespace GeminiAI\ChatAssistant\API;

use GeminiAI\ChatAssistant\Helpers\GACA_Logger;
use \WP_Error;
use Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient;
use Google\Cloud\AIPlatform\V1\PredictRequest;
use Google\Protobuf\Value;
use Google\Protobuf\Struct;
use Google\ApiCore\ApiException;
use Google\ApiCore\ValidationException;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GACA_Gemini_API_Client {

	/**
	 * The Gemini AI API Key.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $api_key    The API key for Gemini AI.
	 */
	private $api_key;

	/**
	 * The Google Cloud project ID.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $project_id    Your Google Cloud Project ID.
	 */
	private $project_id;

	/**
	 * The Google Cloud location (region) for the API.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $location    The Google Cloud location (e.g., 'us-central1').
	 */
	private $location;
	private $service_account_key_path;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    string    $api_key      The Gemini AI API Key.
	 * @param    string    $project_id   Optional. Google Cloud Project ID.
	 * @param    string    $location     Optional. Google Cloud Location.
	 */
	public function __construct( $api_key, $project_id = '', $location = 'us-central1', $service_account_key_path = '' ) {
		$this->api_key    = $api_key;
		$this->project_id = $project_id;
		$this->location   = $location;
		$this->service_account_key_path = $service_account_key_path;

		if ( ! class_exists( 'Google\Cloud\AIPlatform\V1\PredictionServiceClient' ) ) {
			if ( file_exists( GACA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
				require_once GACA_PLUGIN_DIR . 'vendor/autoload.php';
			} else {
				GACA_Logger::error( 'Google Cloud AI Platform client library not found. Run composer install.' );
			}
		}
	}

	/**
	 * Tests the connection to the Gemini AI API by listing available models.
	 *
	 * @since    1.0.0
	 * @return   true|\WP_Error   True on successful connection, WP_Error on failure.
	 */
	public function test_connection() {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'gaca_api_key_missing', esc_html__( 'API Key is empty.', 'gemini-ai-chat-assistant' ) );
		}

		// Use the models.list endpoint for a simple connection test.
		// This endpoint typically does not require a specific model.
		$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $this->api_key;

		$args = array(
			'method'      => 'GET', // Changed to GET for listing models.
			'timeout'     => 15,
			'sslverify'   => false, // Adjust for production.
		);

		GACA_Logger::info( 'Attempting Gemini API connection test by listing models...' );

		$response = wp_remote_get( $url, $args ); // Changed to wp_remote_get.

		if ( is_wp_error( $response ) ) {
			GACA_Logger::error( 'wp_remote_get error during API test: ' . $response->get_error_message() );
			return new WP_Error( 'gaca_api_test_http_error', sprintf( esc_html__( 'HTTP Error: %s', 'gemini-ai-chat-assistant' ), $response->get_error_message() ) );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );

		if ( 200 === $http_code ) {
			// Check if the response contains a 'models' array.
			if ( isset( $data['models'] ) && is_array( $data['models'] ) ) {
				GACA_Logger::info( 'Gemini API test successful. Found ' . count( $data['models'] ) . ' models.' );
				return true;
			} else {
				GACA_Logger::error( 'Gemini API test: Unexpected successful response structure for ListModels.', array( 'response_body' => $body ) );
				return new WP_Error( 'gaca_api_test_invalid_response', esc_html__( 'API returned success, but ListModels response structure was unexpected.', 'gemini-ai-chat-assistant' ) );
			}
		} else {
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : esc_html__( 'Unknown API error.', 'gemini-ai-chat-assistant' );
			GACA_Logger::error( 'Gemini API test failed. HTTP Code: ' . $http_code . ', Message: ' . $error_message );
			return new WP_Error( 'gaca_api_test_failed', sprintf( esc_html__( 'API Error (%d): %s', 'gemini-ai-chat-assistant' ), $http_code, $error_message ) );
		}
	}

    /**
     * Calls the Vertex AI Gemini API to generate content.
     *
     * @since    1.0.0
     * @param    array        $prompt_parts   Array of parts for the prompt.
     * @param    int          $user_id        The ID of the current user.
     * @return   string|\WP_Error             The generated text, or WP_Error on failure.
     */
	
	// private function generate_vertex_content( $prompt_parts, $user_id ) {
	// 	if ( empty( $this->project_id ) || empty( $this->location ) || ! file_exists( $this->service_account_key_path ) ) {
	// 		return new \WP_Error( 'gaca_vertex_config_missing', esc_html__( 'Vertex AI configuration is incomplete.', 'gemini-ai-chat-assistant' ) );
	// 	}
	
	// 	try {
	// 		// Google Vertex AI client
	// 		$client_config = [
	// 			'credentials' => $this->service_account_key_path,
	// 			'endpoint'    => "{$this->location}-aiplatform.googleapis.com"
	// 		];
	// 		$client = new \Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient($client_config);
	
	// 		$model_name = 'gemini-1.5-pro-001';
	// 		$formatted_model = "projects/{$this->project_id}/locations/{$this->location}/publishers/google/models/{$model_name}";
	
	// 		// Build prompt parts
	// 		$prompt_parts_values = [];
	// 		foreach ($prompt_parts as $part) {
	// 			$partValue = new \Google\Protobuf\Value();
	// 			$partStruct = new \Google\Protobuf\Struct();
	
	// 			if (isset($part['text'])) {
	// 				$partStruct->setFields([
	// 					'text' => (new \Google\Protobuf\Value())->setStringValue($part['text'])
	// 				]);
	// 			} elseif (isset($part['inlineData'])) {
	// 				$partStruct->setFields([
	// 					'inline_data' => (new \Google\Protobuf\Value())->setStructValue(
	// 						(new \Google\Protobuf\Struct())->setFields([
	// 							'mime_type' => (new \Google\Protobuf\Value())->setStringValue($part['inlineData']['mimeType']),
	// 							'data'      => (new \Google\Protobuf\Value())->setStringValue($part['inlineData']['data'])
	// 						])
	// 					)
	// 				]);
	// 			}
	
	// 			$partValue->setStructValue($partStruct);
	// 			$prompt_parts_values[] = $partValue;
	// 		}
	
	// 		// Build instance
	// 		$contentsStruct = new \Google\Protobuf\Struct();
	// 		$contentsStruct->setFields([
	// 			'role'  => (new \Google\Protobuf\Value())->setStringValue('user'),
	// 			'parts' => (new \Google\Protobuf\Value())->setListValue(
	// 				(new \Google\Protobuf\ListValue())->setValues($prompt_parts_values)
	// 			)
	// 		]);
	
	// 		$instanceStruct = new \Google\Protobuf\Struct();
	// 		$instanceStruct->setFields([
	// 			'contents' => (new \Google\Protobuf\Value())->setStructValue($contentsStruct)
	// 		]);
	
	// 		$prompt_instances = [
	// 			(new \Google\Protobuf\Value())->setStructValue($instanceStruct)
	// 		];
			
	// 		// ----------------------------
	// 		// Create the PredictRequest object
	// 		// ----------------------------
	// 		$predict_request = new \Google\Cloud\AIPlatform\V1\PredictRequest();
	// 		$predict_request->setEndpoint($formatted_model);
	// 		$predict_request->setInstances($prompt_instances);
	
	// 		// ----------------------------
	// 		// Make the predict request with the PredictRequest object
	// 		// ----------------------------
	// 		$response = $client->predict($predict_request);
	
	// 		$predictions = $response->getPredictions();
	// 		if (empty($predictions)) {
	// 			return new \WP_Error( 'gaca_vertex_no_prediction', esc_html__( 'Vertex AI returned no predictions.', 'gemini-ai-chat-assistant' ) );
	// 		}
	
	// 		// Extract response text
	// 		$text_response = $predictions[0]
	// 			->getStructValue()
	// 			->getFields()['candidates']
	// 			->getListValue()
	// 			->getValues()[0]
	// 			->getStructValue()
	// 			->getFields()['content']
	// 			->getStructValue()
	// 			->getFields()['parts']
	// 			->getListValue()
	// 			->getValues()[0]
	// 			->getStructValue()
	// 			->getFields()['text']
	// 			->getStringValue();
	
	// 		$client->close();
	// 		return $text_response;
	
	// 	} catch (\Google\ApiCore\ApiException $e) {
	// 		GACA_Logger::error( 'Vertex AI API Error: ' . $e->getMessage() );
	// 		return new \WP_Error( 'gaca_vertex_api_error', sprintf( esc_html__( 'Vertex AI API Error: %s', 'gemini-ai-chat-assistant' ), $e->getMessage() ) );
	// 	} catch (\Google\ApiCore\ValidationException $e) {
	// 		GACA_Logger::error( 'Vertex AI Validation Error: ' . $e->getMessage() );
	// 		return new \WP_Error( 'gaca_vertex_validation_error', sprintf( esc_html__( 'Vertex AI Validation Error: %s', 'gemini-ai-chat-assistant' ), $e->getMessage() ) );
	// 	} catch (\Exception $e) {
	// 		GACA_Logger::error( 'Vertex AI Client Error: ' . $e->getMessage() );
	// 		return new \WP_Error( 'gaca_vertex_client_error', sprintf( esc_html__( 'Vertex AI Client Error: %s', 'gemini-ai-chat-assistant' ), $e->getMessage() ) );
	// 	}
	// }

	// Logic for a direct REST call to Vertex AI’s generateContent endpoint
	private function generate_vertex_content($prompt_parts, $user_id) {
		if (empty($this->project_id) || empty($this->location) || !file_exists($this->service_account_key_path)) {
			return new WP_Error('gaca_vertex_config_missing', esc_html__('Vertex AI configuration is incomplete.', 'gemini-ai-chat-assistant'));
		}
	
		// 1. Get Access Token via JWT
		$access_token = $this->get_google_access_token();
		if (!$access_token) {
			return new WP_Error('gaca_vertex_auth_error', esc_html__('Failed to obtain Google OAuth access token.', 'gemini-ai-chat-assistant'));
		}
	
		// 2. Build request body for Generative Models API
		$parts = [];
		foreach ($prompt_parts as $part) {
			if (isset($part['text'])) {
				$parts[] = ['text' => $part['text']];
			} elseif (isset($part['inlineData'])) {
				$parts[] = [
					'inlineData' => [
						'mimeType' => $part['inlineData']['mimeType'],
						'data' => $part['inlineData']['data']
					]
				];
			}
		}
	
		$payload = [
			'contents' => [
				[
					'role'  => 'user',
					'parts' => $parts
				]
			]
		];
	
		// 3. Choose model (Gemini 2.5 Flash in your case)
		$model_name = 'gemini-2.5-flash';
		$url = "https://{$this->location}-aiplatform.googleapis.com/v1/projects/{$this->project_id}/locations/{$this->location}/publishers/google/models/{$model_name}:generateContent";
	
		// 4. Send request to Vertex AI Generative Models REST API
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $access_token,
			'Content-Type: application/json'
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
	
		if ($http_code !== 200) {
			return new WP_Error(
				'gaca_vertex_api_error',
				sprintf(__('Vertex AI API Error (%d): %s', 'gemini-ai-chat-assistant'), $http_code, $response)
			);
		}
	
		$data = json_decode($response, true);
		if (empty($data['candidates'][0]['content']['parts'][0]['text'])) {
			return new WP_Error('gaca_vertex_no_prediction', esc_html__('Vertex AI returned no predictions.', 'gemini-ai-chat-assistant'));
		}
	
		return $data['candidates'][0]['content']['parts'][0]['text'];
	}
	
	/**
	 * Manual JWT signing to get Google OAuth Access Token
	 */
	private function get_google_access_token() {
		$service_account = json_decode(file_get_contents($this->service_account_key_path), true);
		$now = time();
		$jwt_header = ['alg' => 'RS256', 'typ' => 'JWT'];
		$jwt_claim = [
			'iss'   => $service_account['client_email'],
			'scope' => 'https://www.googleapis.com/auth/cloud-platform',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600
		];
	
		$segments = [
			rtrim(strtr(base64_encode(json_encode($jwt_header)), '+/', '-_'), '='),
			rtrim(strtr(base64_encode(json_encode($jwt_claim)), '+/', '-_'), '=')
		];
		$input = implode('.', $segments);
	
		openssl_sign($input, $signature, $service_account['private_key'], 'SHA256');
		$segments[] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
		$jwt = implode('.', $segments);
	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
			'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'assertion'  => $jwt
		]));
		$response = curl_exec($ch);
		curl_close($ch);
	
		$token_data = json_decode($response, true);
		return $token_data['access_token'] ?? null;
	}

	/**
	 * Calls the Gemini AI Generative Language API to generate content.
	 *
	 * @since    1.0.0
	 * @param    array      $prompt_parts   Array of parts for the prompt (e.g., text, inlineData for images).
	 * @param    int        $user_id        The ID of the current user (0 for public).
	 * @return   string|\WP_Error           The generated text response, or WP_Error on failure.
	 */
	public function generate_content( $prompt_parts, $user_id ) {

		if ( ! empty( $this->project_id ) && ! empty( $this->service_account_key_path ) && method_exists($this, 'generate_vertex_content') ) {
            return $this->generate_vertex_content( $prompt_parts, $user_id );
        }

		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'gaca_api_key_missing', esc_html__( 'API Key is empty for content generation.', 'gemini-ai-chat-assistant' ) );
		}

		// Determine the model based on whether image data is present.
		// For Gemini 2.5, we'll use 'gemini-2.5-flash' as the primary model.
		// For vision, we'd need to ensure the model supports it.
		// The `gemini-pro-vision` and `gemini-pro` are older.
		// For Gemini 2.5, the `generateContent` method is typically used with `flash` or `pro` models.
		$model_name = 'gemini-2.5-flash'; // Default to Gemini 2.5 Flash for general chat.

		// The endpoint for generateContent is typically /v1beta/models/{model_id}:generateContent
		$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model_name}:generateContent?key=" . $this->api_key;
		$public_tokens = get_option( 'gaca_public_char_limit', 500 );
		$member_tokens = get_option( 'gaca_member_user_char_limit', 2000 );

		$body = array(
			'contents' => array(
				array(
					'parts' => $prompt_parts,
				),
			),
			// You can add generationConfig here for temperature, max output tokens, etc.
			'generationConfig' => array(
			    'temperature' => 0.7,
			    'maxOutputTokens' => ( $user_id ? $member_tokens : $public_tokens ), // Dynamic limit.
			    'topP' => 0.95,
			    'topK' => 40,
			),
			'safetySettings' => array( // Basic safety settings to avoid blocked content.
				array(
					'category' => 'HARM_CATEGORY_HATE_SPEECH',
					'threshold' => 'BLOCK_NONE',
				),
				array(
					'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
					'threshold' => 'BLOCK_NONE',
				),
				array(
					'category' => 'HARM_CATEGORY_HARASSMENT',
					'threshold' => 'BLOCK_NONE',
				),
				array(
					'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
					'threshold' => 'BLOCK_NONE',
				),
			),
		);

		$args = array(
			'body'        => wp_json_encode( $body ),
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'method'      => 'POST',
			'timeout'     => 60, // Increased timeout for AI responses.
			'sslverify'   => false, // Adjust for production.
		);

		GACA_Logger::info( "Calling Gemini AI model: {$model_name} for content generation..." );
		GACA_Logger::info( "Request Body: " . wp_json_encode( $body ) ); // Log request body for debugging.

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			GACA_Logger::error( 'wp_remote_post error during Gemini content generation: ' . $response->get_error_message() );
			return new WP_Error( 'gaca_gemini_http_error', sprintf( esc_html__( 'AI API HTTP Error: %s', 'gemini-ai-chat-assistant' ), $response->get_error_message() ) );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data      = json_decode( $response_body, true );
		
		if ( 200 === $http_code ) {
			// Check if the response contains candidates
			if ( isset( $data['candidates'][0] ) ) {
				$candidate = $data['candidates'][0];
				$finish_reason = $candidate['finishReason'] ?? 'UNSPECIFIED'; // Get the finish reason.
		
				// Log the finish reason for debugging.
				GACA_Logger::info( 'Gemini AI response finish reason: ' . $finish_reason, array( 'response_body' => $response_body ) );
		
				// Handle the case where the response was successful but truncated.
				if ( $finish_reason === 'MAX_TOKENS' || $finish_reason === 'LENGTH' ) {
					// You can also return the available text, if any.
					$message = $candidate['content']['parts'][0]['text'] ?? '';
					$message .= '<br>';
					
					// Check if the user is not logged in ($user_id is 0 or false).
					if ( ! $user_id ) {
						$message .= '<p><em>' . esc_html__( 'As a public user, your response is limited. Please log in for full access.', 'gemini-ai-chat-assistant' ) . '</em><p>';
					} else {
						$message .= '<p><em>' . esc_html__( 'The response was truncated due to token limits. Please contact site administrator to increase your token limits.', 'gemini-ai-chat-assistant' ) . '</em><p>';
					}

					GACA_Logger::info( 'AI response was truncated due to token limit.' );
					return $message;
				}
		
				// Handle the case where the content was blocked.
				if ( $finish_reason === 'SAFETY' ) {
					$block_reason = $data['promptFeedback']['safetyRatings'][0]['blockReason'] ?? 'Unknown safety reason.';
					GACA_Logger::warning( 'Gemini AI content blocked by safety settings: ' . $block_reason, array( 'response_body' => $response_body ) );
					return new WP_Error( 'gaca_gemini_blocked', sprintf( esc_html__( 'AI response blocked due to safety settings: %s', 'gemini-ai-chat-assistant' ), $block_reason ) );
				}
				
				// Handle a successful, non-truncated response.
				if ( isset( $candidate['content']['parts'][0]['text'] ) && $finish_reason === 'STOP' ) {
					GACA_Logger::info( 'Gemini AI content generation successful.' );
					return $candidate['content']['parts'][0]['text'];
				}
		
				// Catch-all for unexpected valid responses.
				GACA_Logger::error( 'Gemini AI content generation: Unexpected valid response structure.', array( 'response_body' => $response_body ) );
				return new WP_Error( 'gaca_gemini_invalid_response', esc_html__( 'AI API responded with an unexpected structure.', 'gemini-ai-chat-assistant' ) );
		
			} elseif ( isset( $data['promptFeedback']['blockReason'] ) ) {
				// Fallback for older API versions or different response structures.
				$block_reason = $data['promptFeedback']['blockReason'];
				GACA_Logger::warning( 'Gemini AI content blocked by safety settings: ' . $block_reason, array( 'response_body' => $response_body ) );
				return new WP_Error( 'gaca_gemini_blocked', sprintf( esc_html__( 'AI response blocked due to safety settings: %s', 'gemini-ai-chat-assistant' ), $block_reason ) );
			} else {
				// General error for empty or malformed responses.
				$error_detail = 'Unexpected response structure.';
				if ( isset( $data['error']['message'] ) ) {
					$error_detail = $data['error']['message'];
				}
				GACA_Logger::error( 'Gemini AI content generation: ' . $error_detail, array( 'response_body' => $response_body ) );
				return new WP_Error( 'gaca_gemini_invalid_response', sprintf( esc_html__( 'AI API responded with an unexpected structure: %s', 'gemini-ai-chat-assistant' ), $error_detail ) );
			}
		
		} else {
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : esc_html__( 'Unknown API error.', 'gemini-ai-chat-assistant' );
			GACA_Logger::error( 'Gemini AI content generation failed. HTTP Code: ' . $http_code . ', Message: ' . $error_message, array( 'response_body' => $response_body ) );
			return new WP_Error( 'gaca_gemini_failed', sprintf( esc_html__( 'AI API Error (%d): %s', 'gemini-ai-chat-assistant' ), $http_code, $error_message ) );
		}
	}
}