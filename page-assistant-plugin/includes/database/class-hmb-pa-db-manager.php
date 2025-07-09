<?php

class Hmb_Pa_Db_Manager {

    public function install() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hmb_pa_logs';
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
        return (int) get_user_meta($user_id, 'hmb_pa_tokens', true);
    }

    public static function decrement_token_balance($user_id) {
        $balance = self::get_token_balance($user_id);
        if ($balance > 0) {
            update_user_meta($user_id, 'hmb_pa_tokens', $balance - 1);
            return true;
        }
        return false;
    }

    public static function log_transaction($user_id, $action) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hmb_pa_logs';
        
        $balance_to_log = 0;
        if (user_can($user_id, 'manage_options')) {
            $balance_to_log = 9999; // Use a numerical indicator for 'Unlimited'
        } else {
            // The balance has already been decremented in the API class, so we just get the current value.
            $balance_to_log = self::get_token_balance($user_id);
        }

        $wpdb->insert(
            $table_name,
            [
                'time'           => current_time('mysql'),
                'user_id'        => $user_id,
                'request_action' => $action,
                'tokens_used'    => 1,
                'balance_after'  => $balance_to_log,
            ]
        );
    }

    public static function allocate_initial_tokens($user_id) {
        if (get_user_meta($user_id, 'hmb_pa_tokens', true) === '') {
            update_user_meta($user_id, 'hmb_pa_tokens', 50);
        }
    }

    public static function set_token_balance($user_id, $amount) {
        update_user_meta($user_id, 'hmb_pa_tokens', (int) $amount);
    }
}

add_action('user_register', array('Hmb_Pa_Db_Manager', 'allocate_initial_tokens'));
