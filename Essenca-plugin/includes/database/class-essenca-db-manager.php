<?php

class Essenca_Db_Manager {

    public function install() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'essenca_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            user_id bigint(20) NOT NULL,
            request_action varchar(55) DEFAULT '' NOT NULL,
            tokens_used smallint(5) DEFAULT 1 NOT NULL,
            balance_after smallint(5) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function get_token_balance($user_id) {
        return (int) get_user_meta($user_id, 'essenca_tokens', true);
    }

    public static function decrement_token_balance($user_id, $amount = 1) {
        $balance = self::get_token_balance($user_id);
        $amount = (int) $amount;
        if ($balance >= $amount) {
            update_user_meta($user_id, 'essenca_tokens', $balance - $amount);
            return true;
        }
        return false;
    }

    public static function log_transaction($user_id, $action, $tokens_used = 1) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'essenca_logs';
        
        $balance_to_log = 0;
        if (user_can($user_id, 'manage_options')) {
            $balance_to_log = 9999; // Use a numerical indicator for 'Unlimited'
        } else {
            $balance_to_log = self::get_token_balance($user_id);
        }

        $wpdb->insert(
            $table_name,
            [
                'time'           => current_time('mysql'),
                'user_id'        => $user_id,
                'request_action' => $action,
                'tokens_used'    => $tokens_used,
                'balance_after'  => $balance_to_log,
            ]
        );
    }

    public static function allocate_initial_tokens($user_id) {
        if (get_user_meta($user_id, 'essenca_tokens', true) === '') {
            $options = get_option('essenca_controls');
            $initial_tokens = isset($options['initial_tokens']) ? (int) $options['initial_tokens'] : 50;
            update_user_meta($user_id, 'essenca_tokens', $initial_tokens);
        }
    }

    public static function set_token_balance($user_id, $amount) {
        update_user_meta($user_id, 'essenca_tokens', (int) $amount);
    }

    // --- Analytics Methods ---

    public static function get_total_tokens_used($start_date = null, $end_date = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'essenca_logs';
        $query = "SELECT SUM(tokens_used) FROM $table_name";
        $where = [];
        if ($start_date) {
            $where[] = $wpdb->prepare("time >= %s", $start_date);
        }
        if ($end_date) {
            $where[] = $wpdb->prepare("time <= %s", $end_date);
        }
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        return (int) $wpdb->get_var($query);
    }

    public static function get_usage_by_action($start_date = null, $end_date = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'essenca_logs';
        $query = "SELECT request_action, SUM(tokens_used) as total_tokens, COUNT(id) as total_requests FROM $table_name";
        $where = [];
        if ($start_date) {
            $where[] = $wpdb->prepare("time >= %s", $start_date);
        }
        if ($end_date) {
            $where[] = $wpdb->prepare("time <= %s", $end_date);
        }
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        $query .= " GROUP BY request_action ORDER BY total_tokens DESC";
        return $wpdb->get_results($query, ARRAY_A);
    }

    public static function get_top_users($limit = 10, $start_date = null, $end_date = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'essenca_logs';
        $query = "SELECT user_id, SUM(tokens_used) as total_tokens FROM $table_name";
        $where = [];
        if ($start_date) {
            $where[] = $wpdb->prepare("time >= %s", $start_date);
        }
        if ($end_date) {
            $where[] = $wpdb->prepare("time <= %s", $end_date);
        }
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        $query .= " GROUP BY user_id ORDER BY total_tokens DESC LIMIT %d";
        return $wpdb->get_results($wpdb->prepare($query, $limit), ARRAY_A);
    }

    public static function get_daily_usage($start_date, $end_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'essenca_logs';
        
        // Ensure dates are in the correct format
        $start = date('Y-m-d', strtotime($start_date));
        $end = date('Y-m-d', strtotime($end_date));

        $query = $wpdb->prepare(
            "SELECT DATE(time) as date, SUM(tokens_used) as total_tokens 
             FROM $table_name 
             WHERE time >= %s AND time < DATE_ADD(%s, INTERVAL 1 DAY)
             GROUP BY DATE(time) 
             ORDER BY DATE(time) ASC",
            $start,
            $end
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);

        // Fill in missing dates with 0 tokens
        $usage_data = [];
        $current_date = new DateTime($start);
        $end_date_obj = new DateTime($end);

        while ($current_date <= $end_date_obj) {
            $date_str = $current_date->format('Y-m-d');
            $usage_data[$date_str] = 0;
            $current_date->modify('+1 day');
        }

        foreach ($results as $row) {
            $usage_data[$row['date']] = (int) $row['total_tokens'];
        }

        return $usage_data;
    }
}

add_action('user_register', array('Essenca_Db_Manager', 'allocate_initial_tokens'));
