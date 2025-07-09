<?php
/**
 * Plugin Name:       Page Assistant
 * Plugin URI:        https://hembitec.com/
 * Description:       Provides AI-powered page analysis and content generation tools for the Page Assistant Chrome Extension.
 * Version:           1.0.0
 * Author:            Hembitec
 * Author URI:        https://hembitec.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       page-assistant
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'HMB_PA_VERSION', '1.0.0' );
define( 'HMB_PA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Main plugin class
require_once HMB_PA_PLUGIN_DIR . 'includes/class-hmb-pa-main.php';

// Initialize the plugin
function hmb_pa_run_plugin() {
    $plugin = new Hmb_Pa_Main();
    $plugin->run();
}
hmb_pa_run_plugin();
