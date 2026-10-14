<?php
/**
 * AI Webpage Chat Widget - Secure Backend for WordPress code snippet
 * v2.8-Lite - Enhanced Security, Email Transcripts Only (No Database Storage)
 * 
 * by Igor Lipkanou (igor@magicquickwins.com) and Claude Sonet 4.5
 * 
 * Creates two REST API endpoints:
 * 1. /my-chat-assistant/v1/chat - Handles chat requests with Google Gemini API
 * 2. /my-chat-assistant/v1/lead - Handles transcript email delivery
 * 
 * Security Features:
 * - Security tokens on all requests (prevents fake submissions)
 * - Dual rate limiting:
 *   - Chat endpoint: 20 requests per minute
 *   - Transcript endpoint: 10 submissions per hour (NEW in v2.8)
 * - Conversation validation: Requires real chat before email submission (NEW in v2.8)
 * - Bot trap: Blocks automated bots (NEW in v2.8)
 * - Input cleaning
 * - Domain restrictions
 */

add_action('rest_api_init', function () {

    // =================================================================
    // CORS HEADERS - Restrict access to your domain only
    // FIXED: Only apply to chat widget endpoints, not all REST requests
    // =================================================================
    add_filter('rest_pre_serve_request', function ($value) {
        // Only apply CORS headers to our chat widget endpoints
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($request_uri, '/my-chat-assistant/v1/') === false) {
            return $value; // Not our endpoint, don't interfere
        }

        header('Access-Control-Allow-Origin: ' . get_home_url());
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-WP-Nonce');
        header('Access-Control-Allow-Credentials: true');

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            status_header(200);
            exit();
        }
        return $value;
    });

    // =================================================================
    // SECURITY CHECK #1 - For Chat Endpoint
    // Standard rate limiting: 20 requests per minute
    // =================================================================
    $permission_callback_chat = function (WP_REST_Request $request) {
        // Verify security token
        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('rest_forbidden', 'Invalid security token.', ['status' => 403]);
        }

        // Detect real IP behind proxy/CDN (Cloudflare, etc.)
        $ip_address = sanitize_text_field(
            $_SERVER['HTTP_CF_CONNECTING_IP'] ??      // Cloudflare
            $_SERVER['HTTP_X_FORWARDED_FOR'] ??       // Standard proxy header
            $_SERVER['HTTP_X_REAL_IP'] ??             // Nginx proxy
            $_SERVER['REMOTE_ADDR']                   // Fallback
        );
        // If multiple IPs in X-Forwarded-For, take the first one
        if (strpos($ip_address, ',') !== false) {
            $ip_address = trim(explode(',', $ip_address)[0]);
        }

        $transient_key = 'chat_limit_' . md5($ip_address);
        $request_count = get_transient($transient_key);

        // Rate limiting: 20 requests per minute per visitor
        if ($request_count === false) {
            set_transient($transient_key, 1, 60); // 60 seconds = 1 minute
        } else {
            if ($request_count > 20) {
                return new WP_Error('too_many_requests', 'Too many requests. Please wait.', ['status' => 429]);
            }
            set_transient($transient_key, $request_count + 1, 60);
        }

        return true;
    };

    // =================================================================
    // SECURITY CHECK #2 - For Transcript Endpoint (NEW in v2.8)
    // Stricter rate limiting: 10 submissions per hour
    // Prevents spam while allowing visitors to use it on multiple pages
    // =================================================================
    $permission_callback_lead = function (WP_REST_Request $request) {
        // Verify security token
        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('rest_forbidden', 'Invalid security token.', ['status' => 403]);
        }

        // Stricter rate limiting for transcript submission: 10 per hour
        // This prevents abuse while allowing visitors to submit on multiple pages
        // You can adjust this number based on your needs:
        // - 5 per hour = very secure, may limit power users
        // - 10 per hour = balanced (recommended)
        // - 20 per hour = more lenient, easier for testing

        // Detect real IP behind proxy/CDN (Cloudflare, etc.)
        $ip_address = sanitize_text_field(
            $_SERVER['HTTP_CF_CONNECTING_IP'] ??      // Cloudflare
            $_SERVER['HTTP_X_FORWARDED_FOR'] ??       // Standard proxy header
            $_SERVER['HTTP_X_REAL_IP'] ??             // Nginx proxy
            $_SERVER['REMOTE_ADDR']                   // Fallback
        );
        // If multiple IPs in X-Forwarded-For, take the first one
        if (strpos($ip_address, ',') !== false) {
            $ip_address = trim(explode(',', $ip_address)[0]);
        }

        $transient_key = 'chat_lead_limit_' . md5($ip_address);
        $request_count = get_transient($transient_key);

        if ($request_count === false) {
            set_transient($transient_key, 1, 3600); // 3600 seconds = 1 hour
        } else {
            if ($request_count > 10) { // CHANGE THIS NUMBER to adjust rate limit
                return new WP_Error('too_many_requests', 'Too many submissions. Please wait before trying again.', ['status' => 429]);
            }
            set_transient($transient_key, $request_count + 1, 3600);
        }

        return true;
    };

    // =================================================================
    // REGISTER ENDPOINTS
    // =================================================================

    // Endpoint 1: Chat proxy to Gemini API
    // Uses standard rate limiting (20/minute)
    register_rest_route('my-chat-assistant/v1', '/chat', [
        'methods' => 'POST',
        'callback' => 'handle_ai_chat_proxy',
        'permission_callback' => $permission_callback_chat
    ]);

    // Endpoint 2: Transcript email delivery
    // Uses stricter rate limiting (10/hour) - NEW in v2.8
    register_rest_route('my-chat-assistant/v1', '/lead', [
        'methods' => 'POST',
        'callback' => 'handle_ai_chat_submission',
        'permission_callback' => $permission_callback_lead  // Uses stricter callback
    ]);
});

/**
 * Handle chat requests and communicate with Google Gemini API
 * Supports three actions: extractNameAndGreet, continueChat, summarizeConversation
 */
function handle_ai_chat_proxy(WP_REST_Request $request)
{
    // Verify API key is configured
    if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
        error_log('AI Chat Widget: API key not configured');
        return new WP_Error('no_api_key', 'API key not configured.', ['status' => 500]);
    }

    $api_key = GEMINI_API_KEY;
    $params = $request->get_json_params();
    $action = isset($params['action']) ? $params['action'] : '';
    $payload = isset($params['payload']) ? $params['payload'] : [];

    // Validate request
    if (empty($action) || empty($payload)) {
        return new WP_Error('bad_request', 'Invalid parameters.', ['status' => 400]);
    }

    // Get model (default to gemini-2.5-flash-lite)
    $model = isset($payload['model']) ? $payload['model'] : 'gemini-2.5-flash-lite';
    $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

    // Build Gemini API payload based on action
    $gemini_payload = [];

    // ACTION 1: Extract first name and create greeting
    if ($action === 'extractNameAndGreet') {
        $gemini_payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $payload['userInput']]]]
            ],
            'systemInstruction' => [
                'parts' => [
                    [
                        'text' => "You are an expert at extracting first names from casual messages. Extract ONLY the person's first name from their message. Output valid JSON with exactly two keys: 'name' and 'greeting'.

Rules for name extraction:
- If they say 'My name is John Smith', extract only 'John'
- If they say 'I'm Jane', extract 'Jane'
- If they say 'Call me Mike', extract 'Mike'
- If they just say 'Sarah', extract 'Sarah'
- If they say 'Hi, I am Dr. Robert Johnson', extract 'Robert'
- Remove titles (Mr., Mrs., Dr., etc.)
- Extract ONLY the first given name, never last names
- If no name is found, use 'friend'

Create a warm, friendly greeting using their first name.

Example outputs:
{\"name\": \"John\", \"greeting\": \"Hi John! Nice to meet you. How can I help you today?\"}
{\"name\": \"Sarah\", \"greeting\": \"Hello Sarah! Great to connect with you. What brings you here?\"}
{\"name\": \"friend\", \"greeting\": \"Hi there! Nice to meet you. How can I assist you today?\"}"
                    ]
                ]
            ],
            'generationConfig' => ['responseMimeType' => 'application/json']
        ];
    }

    // ACTION 2: Continue conversation with context
    elseif ($action === 'continueChat') {
        $gemini_payload = [
            'contents' => array_map(function ($msg) {
                return ['role' => $msg['role'], 'parts' => [['text' => $msg['content']]]];
            }, $payload['history']),
            'systemInstruction' => [
                'parts' => [
                    [
                        'text' => "You are Maggie, a knowledgeable and friendly sales consultant helping " . sanitize_text_field($payload['userName']) . " understand this page. Your goal is to help them make the best decision for their needs.

COMMUNICATION STYLE:
- Be warm, confident, and genuinely helpful (consultant, not salesperson)
- Focus on benefits and outcomes, not just features
- Use their name occasionally to build rapport
- Address concerns with empathy, then provide solutions
- Be enthusiastic about the value, but never pushy
- Keep responses concise but complete (2-4 sentences usually)

ANSWER STRATEGY:
- Answer questions based ONLY on the provided context
- Transform features into benefits when explaining (e.g., '50 pages' → 'comprehensive guide covering everything you need')
- If asked about price/value, emphasize ROI and transformation
- For objections, validate their concern first, then address it
- If info isn't in context, say: 'I don't see that specific detail here, but I can tell you about [related info]'
- Use specific details from the context (numbers, names, guarantees)

CONVERSATION FLOW:
- When they seem satisfied or run out of questions, naturally wrap up
- End with [END_OF_CONVERSATION] when appropriate
- Start every sentence from a new line. For clarity, insert the new line symbol after each sentence ('\n') for better readability
- Always use simple markdown (**bold** for emphasis, * for lists) in your responses
- End responses with a relevant emoji that matches the mood
- After each response ask what else they would like to know or you can do for them

Context:\n---\n" . $payload['context'] . "\n---"
                    ]
                ]
            ]
        ];
    }

    // ACTION 3: Summarize conversation
    elseif ($action === 'summarizeConversation') {
        $transcript = implode("\n", array_map(function ($msg) {
            return ($msg['role'] === 'user' ? 'User' : 'Maggie') . ': ' . $msg['content'];
        }, $payload['history']));

        $gemini_payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => "Summarize this chat conversation in one concise paragraph:\n\n" . $transcript]]]
            ],
            'generationConfig' => ['maxOutputTokens' => 200, 'temperature' => 0.3]
        ];
    }

    // Send request to Gemini API
    $response = wp_remote_post($api_url, [
        'method' => 'POST',
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($gemini_payload),
        'timeout' => 20,
    ]);

    // Handle errors
    if (is_wp_error($response)) {
        error_log('AI Chat Widget: Failed to connect to Gemini API - ' . $response->get_error_message());
        return new WP_Error('api_error', 'Failed to connect to AI service.', ['status' => 502]);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['error'])) {
        error_log('AI Chat Widget: Gemini API error - ' . $data['error']['message']);
        return new WP_Error('gemini_api_error', $data['error']['message'], ['status' => 500]);
    }

    // Extract and return response
    $text_response = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, could not process that.';
    return new WP_REST_Response(['text' => $text_response], 200);
}

/**
 * Handle transcript email delivery
 * 
 * NEW in v2.8 - Enhanced security with three validation layers:
 * 1. Conversation validation: Requires minimum 4 messages with both user and assistant
 * 2. Bot trap: Rejects submissions with filled 'website' field
 * 3. Stricter rate limiting: Maximum 10 submissions per hour (handled in permission callback)
 */
function handle_ai_chat_submission(WP_REST_Request $request)
{

    // =================================================================
    // CONFIGURATION - Update these values
    // =================================================================
    $admin_email = 'your-email@example.com'; // CHANGE THIS!
    $email_subject = 'Chat Transcript from Your Website';
    // =================================================================

    // Get and clean input
    $params = $request->get_json_params();
    $name = isset($params['name']) ? sanitize_text_field($params['name']) : '';
    $email = isset($params['email']) ? sanitize_email($params['email']) : '';
    $transcript_data = isset($params['transcript']) && is_array($params['transcript']) ? $params['transcript'] : [];
    $summary = isset($params['summary']) ? sanitize_textarea_field($params['summary']) : '';
    $website = isset($params['website']) ? sanitize_text_field($params['website']) : null; // NEW in v2.8: Bot trap field

    // =================================================================
    // SECURITY VALIDATION #1: Basic Required Data
    // =================================================================
    if (empty($name) || !is_email($email) || empty($transcript_data)) {
        return new WP_Error('bad_request', 'Invalid data.', ['status' => 400]);
    }

    // =================================================================
    // SECURITY VALIDATION #2: Bot Trap (NEW in v2.8)
    // Automated bots often fill all form fields. Real visitors never see this field.
    // If the 'website' field has content, it's likely a bot.
    // =================================================================
    if ($website !== null && !empty($website)) {
        error_log('AI Chat Widget: Bot trap triggered - potential bot detected from IP: ' . sanitize_text_field($_SERVER['REMOTE_ADDR']));
        return new WP_Error('spam_detected', 'Spam detected.', ['status' => 400]);
    }

    // =================================================================
    // SECURITY VALIDATION #3: Conversation Validation (NEW in v2.8)
    // Ensures a real conversation happened before email submission.
    // Prevents someone from submitting fake data without actually chatting.
    // =================================================================

    // Rule 3a: Minimum conversation length
    // A real conversation has at least 4 messages:
    // 1. Assistant's greeting
    // 2. User's name
    // 3. Assistant's response
    // 4. User's question OR Assistant's transcript reminder
    // You can adjust this number based on your needs:
    // - 4 messages = standard (recommended, catches quick spam)
    // - 6 messages = stricter (ensures at least one real Q&A)
    // - 8+ messages = very strict (may frustrate visitors with short questions)
    if (count($transcript_data) < 4) { // CHANGE THIS NUMBER to adjust minimum conversation length
        error_log('AI Chat Widget: Conversation too short - ' . count($transcript_data) . ' messages from IP: ' . sanitize_text_field($_SERVER['REMOTE_ADDR']));
        return new WP_Error('invalid_conversation', 'Conversation too short. Please have a real conversation before requesting a transcript.', ['status' => 400]);
    }

    // Rule 3b: Validate conversation structure
    // A real conversation must have messages from BOTH the user and assistant.
    // This prevents someone from submitting fake message arrays.
    $has_user_message = false;
    $has_model_message = false;

    foreach ($transcript_data as $message) {
        if (!isset($message['role']) || !isset($message['content'])) {
            error_log('AI Chat Widget: Invalid message structure in transcript from IP: ' . sanitize_text_field($_SERVER['REMOTE_ADDR']));
            return new WP_Error('invalid_conversation', 'Invalid conversation structure.', ['status' => 400]);
        }

        if ($message['role'] === 'user') {
            $has_user_message = true;
        }
        if ($message['role'] === 'model') {
            $has_model_message = true;
        }
    }

    if (!$has_user_message || !$has_model_message) {
        error_log('AI Chat Widget: Missing user or assistant messages in transcript from IP: ' . sanitize_text_field($_SERVER['REMOTE_ADDR']));
        return new WP_Error('invalid_conversation', 'Invalid conversation - missing messages from user or assistant.', ['status' => 400]);
    }

    // =================================================================
    // All validations passed - send emails
    // =================================================================

    // Build email transcript text
    $transcript_text = "";
    foreach ($transcript_data as $message) {
        $role = ($message['role'] === 'user') ? $name : 'Maggie (AI)';
        $transcript_text .= $role . ": " . $message['content'] . "\n\n";
    }

    // Build HTML email with chat-style bubbles
    $transcript_html = "<!DOCTYPE html><html><body style='font-family: Arial, sans-serif;'>" .
        "<h1>Chat Transcript</h1><p><strong>Visitor:</strong> " . esc_html($name) .
        " (&lt;" . esc_html($email) . "&gt;)</p>" .
        "<p><strong>Summary:</strong> " . esc_html($summary) . "</p><hr>" .
        "<div style='background-color:#E5DDD5; padding: 20px; border-radius: 8px;'>";

    // Add chat messages as styled bubbles
    foreach ($transcript_data as $message) {
        $role = ($message['role'] === 'user') ? $name : 'Maggie (AI)';
        $content = nl2br(esc_html($message['content']));
        $bubble_style = ($message['role'] === 'user')
            ? "background-color: #DCF8C6; float: right; clear: both;"
            : "background-color: #FFFFFF; float: left; clear: both;";
        $transcript_html .= "<div style='max-width: 75%; padding: 10px 15px; border-radius: 12px; margin-bottom: 10px; {$bubble_style}'>" .
            "<strong>" . esc_html($role) . ":</strong><br>" . $content . "</div>";
    }

    $transcript_html .= "</div></body></html>";

    // Send emails to admin and user
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $admin_sent = wp_mail($admin_email, $email_subject, $transcript_html, $headers);
    $user_sent = wp_mail($email, $email_subject, $transcript_html, $headers);

    // Always log failures with details
    if (!$admin_sent || !$user_sent) {
        error_log('AI Chat Widget: Email failed - Admin: ' . ($admin_sent ? 'OK' : 'FAILED') . ', User: ' . ($user_sent ? 'OK' : 'FAILED') . ' - Email: ' . $email);
    }

    // Log successes for monitoring (comment out these 3 lines if logs get too large)
    if ($admin_sent && $user_sent) {
        error_log('AI Chat Widget: Transcript sent successfully to ' . $email);
    }

    return new WP_REST_Response(['success' => true], 200);
}