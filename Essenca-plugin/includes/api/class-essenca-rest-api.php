<?php

class Essenca_Rest_Api {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('essenca/v1', '/register', [
            'methods' => 'POST',
            'callback' => array($this, 'register_user'),
            'permission_callback' => '__return_true'
        ]);
        register_rest_route('essenca/v1', '/token', [
            'methods' => 'POST',
            'callback' => array($this, 'generate_token'),
            'permission_callback' => '__return_true'
        ]);
        register_rest_route('essenca/v1', '/process', [
            'methods' => 'POST',
            'callback' => array($this, 'process_request'),
            'permission_callback' => array($this, 'check_permission')
        ]);
        register_rest_route('essenca/v1', '/balance', [
            'methods' => 'GET',
            'callback' => array($this, 'get_balance_endpoint'),
            'permission_callback' => array($this, 'check_permission')
        ]);
        register_rest_route('essenca/v1', '/test', [
            'methods' => 'POST',
            'callback' => array($this, 'test_api_key'),
            'permission_callback' => '__return_true' // Or check for admin nonce
        ]);

        // --- User Dashboard Endpoints ---
        register_rest_route('essenca/v1', '/user/me', [
            'methods' => 'GET',
            'callback' => array($this, 'get_current_user_data'),
            'permission_callback' => array($this, 'check_permission')
        ]);

        register_rest_route('essenca/v1', '/user/activity', [
            'methods' => 'GET',
            'callback' => array($this, 'get_current_user_activity'),
            'permission_callback' => array($this, 'check_permission')
        ]);

        // --- Account Management Endpoints ---
        register_rest_route('essenca/v1', '/user/change-password', [
            'methods' => 'POST',
            'callback' => array($this, 'change_user_password'),
            'permission_callback' => array($this, 'check_permission')
        ]);

        register_rest_route('essenca/v1', '/user/change-username', [
            'methods' => 'POST',
            'callback' => array($this, 'change_username'),
            'permission_callback' => array($this, 'check_permission')
        ]);
    }

    public function get_current_user_data($request) {
        $user_id = $request->get_param('user_id');
        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_Error('user_not_found', 'User not found.', ['status' => 404]);
        }

        $balance = user_can($user_id, 'manage_options') ? 'Unlimited' : Essenca_Db_Manager::get_token_balance($user_id);

        $response_data = [
            'id' => get_user_meta($user_id, 'essenca_id', true),
            'username' => $user->user_login,
            'email' => $user->user_email,
            'token_balance' => $balance,
        ];

        return new WP_REST_Response($response_data, 200);
    }

    public function get_current_user_activity($request) {
        global $wpdb;
        $user_id = $request->get_param('user_id');
        $table_name = $wpdb->prefix . 'essenca_logs';

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT time, request_action, tokens_used FROM $table_name WHERE user_id = %d ORDER BY time DESC LIMIT 50",
            $user_id
        ), ARRAY_A);

        return new WP_REST_Response($logs, 200);
    }

    public function change_user_password($request) {
        $user_id = $request->get_param('user_id');
        $user = get_userdata($user_id);
        
        $current_password = $request->get_param('current_password');
        $new_password = $request->get_param('new_password');

        if (empty($current_password) || empty($new_password)) {
            return new WP_Error('missing_fields', 'Current and new passwords are required.', ['status' => 400]);
        }

        if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
            return new WP_Error('wrong_password', 'The current password you entered is incorrect.', ['status' => 403]);
        }

        wp_set_password($new_password, $user_id);
        return new WP_REST_Response(['success' => true, 'message' => 'Password changed successfully.'], 200);
    }

    public function change_username($request) {
        $user_id = $request->get_param('user_id');
        $user = get_userdata($user_id);

        $password = $request->get_param('password');
        $new_username = $request->get_param('new_username');

        if (empty($password) || empty($new_username)) {
            return new WP_Error('missing_fields', 'Password and new username are required.', ['status' => 400]);
        }

        if (!wp_check_password($password, $user->user_pass, $user_id)) {
            return new WP_Error('wrong_password', 'The password you entered is incorrect.', ['status' => 403]);
        }

        if (username_exists($new_username)) {
            return new WP_Error('username_exists', 'That username is already taken.', ['status' => 409]);
        }

        $result = wp_update_user(['ID' => $user_id, 'user_login' => $new_username]);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['success' => true, 'message' => 'Username changed successfully.'], 200);
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
        $token = Essenca_Jwt_Handler::generate($user->ID);
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
        $payload = Essenca_Jwt_Handler::validate($token);
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
            $balance = Essenca_Db_Manager::get_token_balance($user_id);
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
        $options = get_option('essenca_controls');
        $token_costs = isset($options['costs']) ? $options['costs'] : [];
        $cost = isset($token_costs[$log_action]) ? (int) $token_costs[$log_action] : 1;

        // Only check token balance for non-admins
        if (!$is_admin && Essenca_Db_Manager::get_token_balance($user_id) < $cost) {
            return new WP_Error('no_tokens', 'You do not have enough tokens for this action.', ['status' => 402]);
        }

        try {
            $result = Essenca_Gemini_Api::make_request(
                $action,
                $request->get_param('content'),
                $request->get_param('message'),
                $request->get_param('history'),
                $user_profile
            );

            // Only decrement tokens for non-admins after a successful API call
            if (!$is_admin) {
                Essenca_Db_Manager::decrement_token_balance($user_id, $cost);
            }

            // Log the transaction with the correct cost
            Essenca_Db_Manager::log_transaction($user_id, $log_action, $cost);

            return new WP_REST_Response([
                'success' => true,
                'result' => $result,
                'tokens_remaining' => Essenca_Db_Manager::get_token_balance($user_id)
            ], 200);
        } catch (Exception $e) {
            // If the API call fails, the token is not deducted, so no refund is necessary.
            return new WP_Error('ai_request_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function test_api_key() {
        try {
            $result = Essenca_Gemini_Api::test_connection();
            return new WP_REST_Response(['success' => true, 'message' => $result], 200);
        } catch (Exception $e) {
            return new WP_Error('test_failed', 'API Error: ' . $e->getMessage(), ['status' => 500]);
        }
    }
}
