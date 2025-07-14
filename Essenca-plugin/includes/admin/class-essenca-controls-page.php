<?php

class Essenca_Controls_Page {

    public static function render() {
        ?>
        <div class="wrap">
            <h1>Essenca Controls</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('essenca_controls_settings');
                do_settings_sections('essenca-controls');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function register_settings() {
        // Register the main settings group
        register_setting('essenca_controls_settings', 'essenca_controls');

        // Add settings sections
        add_settings_section(
            'essenca_token_costs_section',
            'Token Costs',
            null,
            'essenca-controls'
        );

        add_settings_section(
            'essenca_user_settings_section',
            'User Settings',
            null,
            'essenca-controls'
        );

        // Add settings fields
        $actions = [
            'summary' => 'Summary',
            'key-takeaway' => 'Key Takeaway',
            'chat' => 'Chat',
            'linkedin_comment_persona' => 'LinkedIn Comment (Persona)',
            'linkedin_comment_generic' => 'LinkedIn Comment (Generic)',
        ];

        foreach ($actions as $key => $label) {
            add_settings_field(
                'essenca_cost_' . $key,
                $label,
                array(__CLASS__, 'render_cost_field'),
                'essenca-controls',
                'essenca_token_costs_section',
                ['key' => $key]
            );
        }

        add_settings_field(
            'essenca_initial_tokens',
            'Initial Tokens for New Users',
            array(__CLASS__, 'render_initial_tokens_field'),
            'essenca-controls',
            'essenca_user_settings_section'
        );
    }

    public static function render_cost_field($args) {
        $options = get_option('essenca_controls');
        $key = $args['key'];
        $value = isset($options['costs'][$key]) ? $options['costs'][$key] : 1;
        echo "<input type='number' name='essenca_controls[costs][$key]' value='" . esc_attr($value) . "' min='0' />";
    }

    public static function render_initial_tokens_field() {
        $options = get_option('essenca_controls');
        $value = isset($options['initial_tokens']) ? $options['initial_tokens'] : 50;
        echo "<input type='number' name='essenca_controls[initial_tokens]' value='" . esc_attr($value) . "' min='0' />";
    }
}

add_action('admin_init', array('Essenca_Controls_Page', 'register_settings'));
