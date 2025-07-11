<?php

class Hmb_Pa_Main {

    public function __construct() {
        $this->load_dependencies();
    }

    private function load_dependencies() {
        // API Handlers
        require_once HMB_PA_PLUGIN_DIR . 'includes/api/class-hmb-pa-jwt-handler.php';
        require_once HMB_PA_PLUGIN_DIR . 'includes/api/class-hmb-pa-gemini-api.php';
        require_once HMB_PA_PLUGIN_DIR . 'includes/api/class-hmb-pa-rest-api.php';

        // Database Manager
        require_once HMB_PA_PLUGIN_DIR . 'includes/database/class-hmb-pa-db-manager.php';

        // Admin Area
        require_once HMB_PA_PLUGIN_DIR . 'includes/admin/class-hmb-pa-admin-menu.php';
        require_once HMB_PA_PLUGIN_DIR . 'includes/admin/class-hmb-pa-settings-page.php';
        require_once HMB_PA_PLUGIN_DIR . 'includes/admin/class-hmb-pa-controls-page.php';
        require_once HMB_PA_PLUGIN_DIR . 'includes/admin/class-hmb-pa-analytics-page.php';
    }

    public function run() {
        // Initialize classes
        $db_manager = new Hmb_Pa_Db_Manager();
        new Hmb_Pa_Rest_Api();
        new Hmb_Pa_Admin_Menu();

        // Activation and upgrade hooks
        register_activation_hook(HMB_PA_PLUGIN_DIR . 'page-assistant.php', array($db_manager, 'install'));
        add_action('admin_init', array($this, 'upgrade_check'));
    }

    public function upgrade_check() {
        $current_version = get_option('hmb_pa_version', '0.0.0');

        if (version_compare($current_version, HMB_PA_VERSION, '<')) {
            // Run migration if we are upgrading from a pre-1.0.0 version
            if ($current_version === '0.0.0') {
                $this->perform_migration();
            }
            update_option('hmb_pa_version', HMB_PA_VERSION);
        }
    }

    private function perform_migration() {
        global $wpdb;

        // 1. Migrate Options
        $old_jwt_key = get_option('pa_api_jwt_secret_key');
        if ($old_jwt_key) {
            update_option('hmb_pa_jwt_secret_key', $old_jwt_key);
            delete_option('pa_api_jwt_secret_key');
        }

        $old_gemini_key = get_option('pa_api_gemini_api_key');
        if ($old_gemini_key) {
            update_option('hmb_pa_gemini_api_key', $old_gemini_key);
            delete_option('pa_api_gemini_api_key');
        }

        // 2. Migrate User Meta (Token Balances)
        $users = get_users(['fields' => 'ID']);
        foreach ($users as $user_id) {
            $old_tokens = get_user_meta($user_id, 'page_assistant_tokens', true);
            if ($old_tokens !== '') {
                update_user_meta($user_id, 'hmb_pa_tokens', $old_tokens);
                delete_user_meta($user_id, 'page_assistant_tokens');
            }
        }

        // 3. Migrate Database Table
        $old_table_name = $wpdb->prefix . 'page_assistant_logs';
        $new_table_name = $wpdb->prefix . 'hmb_pa_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$old_table_name'") == $old_table_name) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$new_table_name'") != $new_table_name) {
                 $wpdb->query("RENAME TABLE `$old_table_name` TO `$new_table_name`;");
            }
        }
    }
}
