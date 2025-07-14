<?php

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete options
delete_option('essenca_jwt_secret_key');
delete_option('essenca_gemini_api_key');

// Drop custom database table
$table_name = $wpdb->prefix . 'essenca_logs';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Note: This does not delete user meta (token balances) to avoid data loss.
// This can be added if complete data removal is desired.
