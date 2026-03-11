<?php
require_once 'vendor/autoload.php';

use Google\Auth\OAuth2;
use Google\Auth\ApplicationDefaultCredentials;

$scopes = ['https://www.googleapis.com/auth/cloud-platform'];

$credentials = new Google\Auth\Credentials\ServiceAccountCredentials(
    null,
    json_decode(file_get_contents('/path/to/service-account.json'), true)
);

$auth_token = $credentials->fetchAuthToken();
$access_token = $auth_token['access_token'];

$model_name = 'gemini-1.5-flash-preview-0514'; // Or your preferred model
$project_id = 'your-gcp-project-id';
$url = "https://us-central1-aiplatform.googleapis.com/v1/projects/{$project_id}/locations/us-central1/publishers/google/models/{$model_name}:generateContent";

// Construct body same as before
$body = [
    'contents' => [[ 'parts' => $prompt_parts ]],
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => $user_id ? $member_tokens : $public_tokens,
        'topP' => 0.95,
        'topK' => 40,
    ],
    'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
    ],
];

// Get Bearer Token (see previous step)
$access_token = 'your-generated-access-token'; // You’ll generate this with Google Auth library

$args = [
    'body' => wp_json_encode($body),
    'headers' => [
        'Content-Type'  => 'application/json',
        'Authorization' => 'Bearer ' . $access_token,
    ],
    'method'    => 'POST',
    'timeout'   => 60,
    'sslverify' => false,
];

$response = wp_remote_post($url, $args);
