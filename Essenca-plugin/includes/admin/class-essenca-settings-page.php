<?php

class Essenca_Settings_Page {

    public static function render() {
        if (isset($_POST['essenca_save_settings'])) {
            check_admin_referer('essenca_save_settings_nonce');
            update_option('essenca_jwt_secret_key', sanitize_text_field($_POST['essenca_jwt_secret_key']));
            update_option('essenca_gemini_api_key', sanitize_text_field($_POST['essenca_gemini_api_key']));
            update_option('essenca_gemini_model', sanitize_text_field($_POST['essenca_gemini_model']));
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }

        $jwt_key = get_option('essenca_jwt_secret_key', '');
        $gemini_key = get_option('essenca_gemini_api_key', '');
        $gemini_model = get_option('essenca_gemini_model', 'gemini-2.5-flash');
        ?>
        <div class="wrap">
            <h1>API Settings</h1>
            <form method="post" action="">
                <?php wp_nonce_field('essenca_save_settings_nonce'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="essenca_jwt_secret_key">JWT Secret Key</label></th>
                        <td>
                            <input type="text" id="essenca_jwt_secret_key" name="essenca_jwt_secret_key" value="<?php echo esc_attr($jwt_key); ?>" class="regular-text" />
                            <p class="description">A strong, unique key for signing authentication tokens. <a href="https://api.wordpress.org/secret-key/1.1/salt/" target="_blank">Generate one here</a> and paste it.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="essenca_gemini_api_key">Gemini API Key</label></th>
                        <td>
                            <input type="password" id="essenca_gemini_api_key" name="essenca_gemini_api_key" value="<?php echo esc_attr($gemini_key); ?>" class="regular-text" />
                            <p class="description">Your Google Gemini API key for processing AI requests.</p>
                        </td>
                    </tr>
                </table>
                <tr valign="top">
                        <th scope="row"><label for="essenca_gemini_model">Gemini Model</label></th>
                        <td>
                            <select id="essenca_gemini_model" name="essenca_gemini_model">
                                <option value="gemini-2.5-flash" <?php selected($gemini_model, 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash (Default)</option>
                                <option value="gemini-1.5-flash" <?php selected($gemini_model, 'gemini-1.5-flash'); ?>>Gemini 1.5 Flash</option>
                                <option value="gemini-1.5-pro" <?php selected($gemini_model, 'gemini-1.5-pro'); ?>>Gemini 1.5 Pro</option>
                                <option value="gemini-2.0" <?php selected($gemini_model, 'gemini-2.0'); ?>>Gemini 2.0</option>
                                <option value="gemini-2.5-pro" <?php selected($gemini_model, 'gemini-2.5-pro'); ?>>Gemini 2.5 Pro</option>
                                <option value="gemini-2.0-flash" <?php selected($gemini_model, 'gemini-2.0-flash'); ?>>Gemini 2.0 Flash</option>
                                <option value="gemini-2.0-flash-lite" <?php selected($gemini_model, 'gemini-2.0-flash-lite'); ?>>Gemini 2.0 Flash Lite</option>
                            </select>
                            <p class="description">Select the Gemini model to use for AI requests. Flash is faster and more cost-effective, while Pro is more powerful.</p>
                        </td>
                    </tr>
                <?php submit_button('Save Settings', 'primary', 'essenca_save_settings'); ?>
            </form>

            <hr>

            <h2>Extension Configuration</h2>
            <p>Use the following URL in the Chrome Extension's settings for the Essenca API.</p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="essenca_api_url">Your Site's API URL</label></th>
                    <td>
                        <input type="text" id="essenca_api_url" value="<?php echo esc_url(rest_url('essenca/v1')); ?>" class="regular-text" readonly />
                        <p class="description">Copy this URL and paste it into the extension's settings page.</p>
                    </td>
                </tr>
            </table>

            <hr>

            <h2>Test API Connection</h2>
            <button type="button" id="essenca-test-api-btn" class="button">Test Gemini API Key</button>
            <div id="essenca-test-result" style="margin-top: 10px; padding: 10px; border: 1px solid #ccc; background: #f9f9f9; display: none;"></div>
            
            <hr>

            <h2>API Endpoint Reference</h2>
            <p>The base URL for all API endpoints is: <code><?php echo esc_url(rest_url('essenca/v1')); ?></code></p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 25%;">Endpoint</th>
                        <th style="width: 10%;">Method</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>/register</code></td>
                        <td>POST</td>
                        <td>Registers a new user. Requires <code>username</code>, <code>email</code>, and <code>password</code>.</td>
                    </tr>
                    <tr>
                        <td><code>/token</code></td>
                        <td>POST</td>
                        <td>Authenticates a user and returns a JWT. Requires <code>username</code> and <code>password</code>.</td>
                    </tr>
                    <tr>
                        <td><code>/process</code></td>
                        <td>POST</td>
                        <td>Processes a generic AI request. Requires a valid JWT and an <code>action</code> parameter.</td>
                    </tr>
                    <tr>
                        <td><code>/balance</code></td>
                        <td>GET</td>
                        <td>Retrieves the current token balance for the authenticated user. Requires a valid JWT.</td>
                    </tr>
                    <tr>
                        <td><code>/user/me</code></td>
                        <td>GET</td>
                        <td>Retrieves dashboard data (ID, username, email, token balance) for the authenticated user. Requires a valid JWT.</td>
                    </tr>
                    <tr>
                        <td><code>/user/activity</code></td>
                        <td>GET</td>
                        <td>Retrieves the last 50 activity logs for the authenticated user. Requires a valid JWT.</td>
                    </tr>
                    <tr>
                        <td><code>/user/change-password</code></td>
                        <td>POST</td>
                        <td>Allows a logged-in user to change their password. Requires <code>current_password</code> and <code>new_password</code>.</td>
                    </tr>
                    <tr>
                        <td><code>/user/change-username</code></td>
                        <td>POST</td>
                        <td>Allows a logged-in user to change their username. Requires <code>password</code> and <code>new_username</code>.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <script>
        document.getElementById('essenca-test-api-btn').addEventListener('click', function() {
            const resultDiv = document.getElementById('essenca-test-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = 'Testing...';

            fetch('<?php echo esc_url_raw(rest_url("essenca/v1/test")); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<div style="color: green;">✅ Success: ' + data.message + '</div>';
                } else {
                    resultDiv.innerHTML = '<div style="color: red;">❌ Error: ' + (data.message || 'Unknown error occurred.') + '</div>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div style="color: red;">❌ Connection failed: ' + error.message + '</div>';
            });
        });
        </script>
        <?php
    }
}
