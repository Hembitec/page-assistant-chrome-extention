<?php

class Hmb_Pa_Settings_Page {

    public static function render() {
        if (isset($_POST['hmb_pa_save_settings'])) {
            check_admin_referer('hmb_pa_save_settings_nonce');
            update_option('hmb_pa_jwt_secret_key', sanitize_text_field($_POST['hmb_pa_jwt_secret_key']));
            update_option('hmb_pa_gemini_api_key', sanitize_text_field($_POST['hmb_pa_gemini_api_key']));
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }

        $jwt_key = get_option('hmb_pa_jwt_secret_key', '');
        $gemini_key = get_option('hmb_pa_gemini_api_key', '');
        ?>
        <div class="wrap">
            <h1>API Settings</h1>
            <form method="post" action="">
                <?php wp_nonce_field('hmb_pa_save_settings_nonce'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="hmb_pa_jwt_secret_key">JWT Secret Key</label></th>
                        <td>
                            <input type="text" id="hmb_pa_jwt_secret_key" name="hmb_pa_jwt_secret_key" value="<?php echo esc_attr($jwt_key); ?>" class="regular-text" />
                            <p class="description">A strong, unique key for signing authentication tokens. <a href="https://api.wordpress.org/secret-key/1.1/salt/" target="_blank">Generate one here</a> and paste it.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="hmb_pa_gemini_api_key">Gemini API Key</label></th>
                        <td>
                            <input type="password" id="hmb_pa_gemini_api_key" name="hmb_pa_gemini_api_key" value="<?php echo esc_attr($gemini_key); ?>" class="regular-text" />
                            <p class="description">Your Google Gemini API key for processing AI requests.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings', 'primary', 'hmb_pa_save_settings'); ?>
            </form>

            <hr>

            <h2>Extension Configuration</h2>
            <p>Use the following URL in the Chrome Extension's settings for the Page Assistant API.</p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="hmb_pa_api_url">Your Site's API URL</label></th>
                    <td>
                        <input type="text" id="hmb_pa_api_url" value="<?php echo esc_url(rest_url('hmb-page-assistant/v1')); ?>" class="regular-text" readonly />
                        <p class="description">Copy this URL and paste it into the extension's settings page.</p>
                    </td>
                </tr>
            </table>

            <hr>

            <h2>Test API Connection</h2>
            <button type="button" id="hmb-pa-test-api-btn" class="button">Test Gemini API Key</button>
            <div id="hmb-pa-test-result" style="margin-top: 10px; padding: 10px; border: 1px solid #ccc; background: #f9f9f9; display: none;"></div>
        </div>
        <script>
        document.getElementById('hmb-pa-test-api-btn').addEventListener('click', function() {
            const resultDiv = document.getElementById('hmb-pa-test-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = 'Testing...';

            fetch('<?php echo esc_url_raw(rest_url("hmb-page-assistant/v1/test")); ?>', {
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
