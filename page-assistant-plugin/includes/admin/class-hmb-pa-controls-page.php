<?php

class Hmb_Pa_Controls_Page {

    public static function render() {
        ?>
        <div class="wrap">
            <h1>Page Assistant Controls</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('hmb_pa_controls_settings');
                do_settings_sections('hmb-page-assistant-controls');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function register_settings() {
        // Register the main settings group
        register_setting('hmb_pa_controls_settings', 'hmb_pa_controls');

        // Add settings sections
        add_settings_section(
            'hmb_pa_token_costs_section',
            'Token Costs',
            null,
            'hmb-page-assistant-controls'
        );

        add_settings_section(
            'hmb_pa_user_settings_section',
            'User Settings',
            null,
            'hmb-page-assistant-controls'
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
                'hmb_pa_cost_' . $key,
                $label,
                array(__CLASS__, 'render_cost_field'),
                'hmb-page-assistant-controls',
                'hmb_pa_token_costs_section',
                ['key' => $key]
            );
        }

        add_settings_field(
            'hmb_pa_initial_tokens',
            'Initial Tokens for New Users',
            array(__CLASS__, 'render_initial_tokens_field'),
            'hmb-page-assistant-controls',
            'hmb_pa_user_settings_section'
        );
    }

    public static function render_cost_field($args) {
        $options = get_option('hmb_pa_controls');
        $key = $args['key'];
        $value = isset($options['costs'][$key]) ? $options['costs'][$key] : 1;
        echo "<input type='number' name='hmb_pa_controls[costs][$key]' value='" . esc_attr($value) . "' min='0' />";
    }

    public static function render_initial_tokens_field() {
        $options = get_option('hmb_pa_controls');
        $value = isset($options['initial_tokens']) ? $options['initial_tokens'] : 50;
        echo "<input type='number' name='hmb_pa_controls[initial_tokens]' value='" . esc_attr($value) . "' min='0' />";
    }
}

add_action('admin_init', array('Hmb_Pa_Controls_Page', 'register_settings'));
