<?php
/**
 * Plugin Name: Google Reviews Slider
 * Description: Displays Google Reviews in a slider format with enhanced features and improved performance.
 * Version: 1.2
 * Author: Carlos Aragon
 * Author URI: https://carlosaragon.online
 * Text Domain: google-reviews-slider
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://carlosaragon.online/plugins/google-reviews-slider/
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('GRS_VERSION', '1.2');
define('GRS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GRS_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Plugin activation hook
register_activation_hook(__FILE__, 'grs_activation_hook');
function grs_activation_hook() {
    // Set default options on activation
    $default_options = array(
        'grs_api_key' => '',
        'grs_place_id' => '',
        'grs_min_rating' => '1'
    );
    
    $existing_options = get_option('grs_settings', array());
    $options = wp_parse_args($existing_options, $default_options);
    update_option('grs_settings', $options);
    
    // Update version
    update_option('grs_version', GRS_VERSION);
    
    // Clear any cached reviews on activation
    delete_transient('grs_reviews');
    delete_transient('grs_total_review_count');
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'grs_deactivation_hook');
function grs_deactivation_hook() {
    // Clear cached reviews on deactivation
    delete_transient('grs_reviews');
    delete_transient('grs_total_review_count');
}

// Enqueue styles and scripts
function grs_enqueue_assets() {
    wp_enqueue_style('dashicons');
    wp_enqueue_style('grs-slick-css', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.css', array(), GRS_VERSION);
    wp_enqueue_style('grs-slick-theme-css', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.css', array(), GRS_VERSION);
    wp_enqueue_style('grs-style', GRS_PLUGIN_URL . 'css/style.css', array(), GRS_VERSION);
    
    wp_enqueue_script('grs-slick-js', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js', array('jquery'), GRS_VERSION, true);
    wp_enqueue_script('grs-script', GRS_PLUGIN_URL . 'js/script.js', array('jquery', 'grs-slick-js'), GRS_VERSION, true);
    
    // Localize script for AJAX and other data
    wp_localize_script('grs-script', 'grs_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('grs_nonce'),
        'version' => GRS_VERSION
    ));
}
add_action('wp_enqueue_scripts', 'grs_enqueue_assets');

// Add version check and update notice
add_action('admin_notices', 'grs_update_notice');
function grs_update_notice() {
    $current_version = get_option('grs_version', '1.0');
    
    if (version_compare($current_version, GRS_VERSION, '<')) {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>Google Reviews Slider</strong> has been updated to version ' . GRS_VERSION . '! ';
        echo '<a href="' . admin_url('admin.php?page=google_reviews_slider') . '">View what\'s new</a></p>';
        echo '</div>';
        
        // Update the stored version
        update_option('grs_version', GRS_VERSION);
        
        // Clear cached reviews after update
        delete_transient('grs_reviews');
        delete_transient('grs_total_review_count');
    }
}

// Add settings link on plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'grs_add_settings_link');
function grs_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=google_reviews_slider') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Include necessary files
include(GRS_PLUGIN_PATH . 'includes/admin-page.php');
include(GRS_PLUGIN_PATH . 'includes/shortcode.php');
include(GRS_PLUGIN_PATH . 'includes/api-handler.php');

// Add AJAX endpoint for clearing cache
add_action('wp_ajax_grs_clear_cache', 'grs_clear_cache_callback');
function grs_clear_cache_callback() {
    check_ajax_referer('grs_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    delete_transient('grs_reviews');
    delete_transient('grs_total_review_count');
    
    wp_send_json_success('Cache cleared successfully');
}