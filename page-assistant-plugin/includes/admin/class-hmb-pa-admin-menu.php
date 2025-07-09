<?php

class Hmb_Pa_Admin_Menu {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
    }

    public function add_menu() {
        add_menu_page(
            'Page Assistant',
            'Page Assistant',
            'manage_options',
            'hmb-page-assistant',
            array($this, 'users_page_html'),
            'dashicons-groups',
            20
        );

        add_submenu_page(
            'hmb-page-assistant',
            'Users',
            'Users',
            'manage_options',
            'hmb-page-assistant',
            array($this, 'users_page_html')
        );

        add_submenu_page(
            'hmb-page-assistant',
            'Usage Logs',
            'Usage Logs',
            'manage_options',
            'hmb-page-assistant-logs',
            array($this, 'logs_page_html')
        );

        add_submenu_page(
            'hmb-page-assistant',
            'API Settings',
            'API Settings',
            'manage_options',
            'hmb-page-assistant-settings',
            array('Hmb_Pa_Settings_Page', 'render')
        );
    }

    public function users_page_html() {
        // Handle form submission
        if (isset($_POST['hmb_pa_update_tokens_nonce'], $_POST['hmb_pa_user_id'], $_POST['hmb_pa_token_balance']) &&
            wp_verify_nonce($_POST['hmb_pa_update_tokens_nonce'], 'hmb_pa_update_tokens_action')) {
            
            if (current_user_can('manage_options')) {
                $user_id = intval($_POST['hmb_pa_user_id']);
                $token_balance = intval($_POST['hmb_pa_token_balance']);
                Hmb_Pa_Db_Manager::set_token_balance($user_id, $token_balance);
                echo '<div class="notice notice-success is-dismissible"><p>User tokens updated successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>You do not have permission to perform this action.</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1>Page Assistant Users</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 5%;">User ID</th>
                        <th style="width: 20%;">Username</th>
                        <th style="width: 25%;">Email</th>
                        <th style="width: 15%;">Registration Date</th>
                        <th style="width: 15%;">Token Balance</th>
                        <th style="width: 20%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = get_users(['orderby' => 'ID']);
                    foreach ($users as $user) {
                        $token_balance = get_user_meta($user->ID, 'hmb_pa_tokens', true);
                        echo '<tr>';
                        echo '<td>' . esc_html($user->ID) . '</td>';
                        echo '<td>' . esc_html($user->user_login) . '</td>';
                        echo '<td>' . esc_html($user->user_email) . '</td>';
                        echo '<td>' . date('Y-m-d', strtotime($user->user_registered)) . '</td>';
                        echo '<td>' . ($token_balance !== '' ? esc_html($token_balance) : 'N/A') . '</td>';
                        echo '<td>';
                        ?>
                        <form method="post" style="display: inline-flex; align-items: center; gap: 5px;">
                            <?php wp_nonce_field('hmb_pa_update_tokens_action', 'hmb_pa_update_tokens_nonce'); ?>
                            <input type="hidden" name="hmb_pa_user_id" value="<?php echo esc_attr($user->ID); ?>">
                            <input type="number" name="hmb_pa_token_balance" value="<?php echo esc_attr($token_balance); ?>" style="width: 80px;">
                            <button type="submit" class="button button-primary">Set</button>
                        </form>
                        <?php
                        echo '</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function logs_page_html() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hmb_pa_logs';
        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC LIMIT 1000");
        ?>
        <div class="wrap">
            <h1>API Usage Logs</h1>
            <p>Showing the last 1000 entries.</p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 20%;">Timestamp</th>
                        <th style="width: 15%;">User (ID)</th>
                        <th style="width: 35%;">Action</th>
                        <th style="width: 15%;">Tokens Used</th>
                        <th style="width: 15%;">Balance After</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($logs) {
                        foreach ($logs as $log) {
                            $user = get_user_by('id', $log->user_id);
                            $user_display = $user ? $user->user_login . ' (' . $user->ID . ')' : 'Unknown (' . $log->user_id . ')';
                            echo '<tr>';
                            echo '<td>' . esc_html($log->time) . '</td>';
                            echo '<td>' . esc_html($user_display) . '</td>';
                            echo '<td>' . esc_html($log->request_action) . '</td>';
                            echo '<td>' . esc_html($log->tokens_used) . '</td>';
                            echo '<td>' . esc_html($log->balance_after) . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="5">No logs found.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
