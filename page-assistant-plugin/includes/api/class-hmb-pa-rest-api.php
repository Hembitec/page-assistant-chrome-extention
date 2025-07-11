<?php

class Hmb_Pa_Rest_Api {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('hmb-page-assistant/v1', '/register', [
            'methods' => 'POST',
            'callback' => array($this, 'register_user'),
            'permission_callback' => '__return_true'
        ]);
        register_rest_route('hmb-page-assistant/v1', '/token', [
            'methods' => 'POST',
            'callback' => array($this, 'generate_token'),
            'permission_callback' => '__return_true'
        ]);
        register_rest_route('hmb-page-assistant/v1', '/process', [
            'methods' => 'POST',
            'callback' => array($this, 'process_request'),
            'permission_callback' => array($this, 'check_permission')
        ]);
        register_rest_route('hmb-page-assistant/v1', '/balance', [
            'methods' => 'GET',
            'callback' => array($this, 'get_balance_endpoint'),
            'permission_callback' => array($this, 'check_permission')
        ]);
        register_rest_route('hmb-page-assistant/v1', '/test', [
            'methods' => 'POST',
            'callback' => array($this, 'test_api_key'),
            'permission_callback' => '__return_true' // Or check for admin nonce
        ]);
    }

    public function register_user($request) {
        $username = sanitize_text_field($request['username']);
        $email = sanitize_email($request['email']);
        $password = $request['password'];

        if (empty($username) || empty($email) || empty($password)) {
            return new WP_Error('missing_fields', 'Username, email, and password are required.', ['status' => 400]);
        }
        if (username_exists($username)) {
            return new WP_Error('username_exists', 'Username already exists.', ['status' => 400]);
        }
        if (email_exists($email)) {
            return new WP_Error('email_exists', 'Email address already in use.', ['status' => 400]);
        }

        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        return new WP_REST_Response(['success' => true, 'message' => 'User registered successfully.'], 200);
    }

    public function generate_token($request) {
        $user = wp_authenticate($request['username'], $request['password']);
        if (is_wp_error($user)) {
            return new WP_Error('authentication_failed', 'Invalid username or password.', ['status' => 403]);
        }
        $token = Hmb_Pa_Jwt_Handler::generate($user->ID);
        return new WP_REST_Response(['token' => $token, 'user_email' => $user->user_email, 'user_display_name' => $user->display_name], 200);
    }

    public function check_permission($request) {
        $auth_header = $request->get_header('authorization');
        if (!$auth_header) {
            return new WP_Error('no_auth_header', 'Authorization header not found.', ['status' => 401]);
        }
        list($token) = sscanf($auth_header, 'Bearer %s');
        if (!$token) {
            return new WP_Error('bad_auth_header', 'Malformed Authorization header.', ['status' => 401]);
        }
        $payload = Hmb_Pa_Jwt_Handler::validate($token);
        if (!$payload) {
            return new WP_Error('invalid_token', 'The provided token is invalid or has expired.', ['status' => 403]);
        }
        $request->set_param('user_id', $payload->user_id);
        return true;
    }

    public function get_balance_endpoint($request) {
        $user_id = $request->get_param('user_id');
        if (user_can($user_id, 'manage_options')) {
            $balance = 'Unlimited';
        } else {
            $balance = Hmb_Pa_Db_Manager::get_token_balance($user_id);
        }
        return new WP_REST_Response(['success' => true, 'balance' => $balance], 200);
    }

    public function process_request($request) {
        $user_id = $request->get_param('user_id');
        $is_admin = user_can($user_id, 'manage_options');
        $action = $request->get_param('action');
        $user_profile = $request->get_param('user_profile');

        // Determine the specific action for logging and cost lookup
        $log_action = $action;
        if ($action === 'generate_linkedin_comment') {
            $log_action = !empty($user_profile) ? 'linkedin_comment_persona' : 'linkedin_comment_generic';
        }

        // Get token costs from settings
        $options = get_option('hmb_pa_controls');
        $token_costs = isset($options['costs']) ? $options['costs'] : [];
        $cost = isset($token_costs[$log_action]) ? (int) $token_costs[$log_action] : 1;

        // Only check token balance for non-admins
        if (!$is_admin && Hmb_Pa_Db_Manager::get_token_balance($user_id) < $cost) {
            return new WP_Error('no_tokens', 'You do not have enough tokens for this action.', ['status' => 402]);
        }

        try {
            $result = Hmb_Pa_Gemini_Api::make_request(
                $action,
                $request->get_param('content'),
                $request->get_param('message'),
                $request->get_param('history'),
                $user_profile
            );

            // Only decrement tokens for non-admins after a successful API call
            if (!$is_admin) {
                Hmb_Pa_Db_Manager::decrement_token_balance($user_id, $cost);
            }

            // Log the transaction with the correct cost
            Hmb_Pa_Db_Manager::log_transaction($user_id, $log_action, $cost);

            return new WP_REST_Response([
                'success' => true,
                'result' => $result,
                'tokens_remaining' => Hmb_Pa_Db_Manager::get_token_balance($user_id)
            ], 200);
        } catch (Exception $e) {
            // If the API call fails, the token is not deducted, so no refund is necessary.
            return new WP_Error('ai_request_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function test_api_key() {
        try {
            $result = Hmb_Pa_Gemini_Api::test_connection();
            return new WP_REST_Response(['success' => true, 'message' => $result], 200);
        } catch (Exception $e) {
            return new WP_Error('test_failed', 'API Error: ' . $e->getMessage(), ['status' => 500]);
        }
    }
}
