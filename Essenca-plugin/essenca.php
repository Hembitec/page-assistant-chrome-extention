<?php
/**
 * Plugin Name:       Essenca
 * Plugin URI:        https://hembitec.com/
 * Description:       Provides AI-powered page analysis and content generation tools for the Essenca Chrome Extension.
 * Version:           1.1.0
 * Author:            Hembitec
 * Author URI:        https://hembitec.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       essenca
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'ESSENCA_VERSION', '1.1.0' );
define( 'ESSENCA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Main plugin class
require_once ESSENCA_PLUGIN_DIR . 'includes/class-essenca-main.php';

// Initialize the plugin
function essenca_run_plugin() {
    $plugin = new Essenca_Main();
    $plugin->run();
}
essenca_run_plugin();
