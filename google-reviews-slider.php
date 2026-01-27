<?php
/**
 * Plugin Name: Google Reviews Slider
 * Description: Displays Google Reviews in a slider format with enhanced features and improved features.
 * Version: 2.7.8
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
define('GRS_VERSION', '2.7.8');
define('GRS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GRS_PLUGIN_PATH', plugin_dir_path(__FILE__));

// GitHub repository info for automatic updates
define('GRS_GITHUB_USERNAME', 'CachoMX');
define('GRS_GITHUB_REPOSITORY', 'GoogleReviewsSlider-WP-Plugin');

// Plugin activation hook
register_activation_hook(__FILE__, 'grs_activation_hook');
function grs_activation_hook() {
    // Set default options on activation
    $default_options = array(
        'grs_api_key' => '',
        'grs_place_id' => '',
        'grs_min_rating' => '1',
        // SerpAPI key for reviews extraction
        'grs_serpapi_key' => 'e16931eb218fa770300a195b81ae0a6e6a879f3fadd02a3b626d19668386677c',
        // SerpAPI Data ID - empty by default, gets populated from Place ID
        'grs_data_id' => '',
    );

    $existing_options = get_option('grs_settings', array());
    $options = wp_parse_args($existing_options, $default_options);
    update_option('grs_settings', $options);
    
    // Update version
    update_option('grs_version', GRS_VERSION);
    
    // Clear any cached reviews on activation
    delete_transient('grs_reviews');
    delete_transient('grs_total_review_count');

    // Initialize database tables
    require_once(GRS_PLUGIN_PATH . 'includes/database-handler.php');
    GRS_Database::init();

    // Schedule cron job for auto-refresh every 30 days
    if (!wp_next_scheduled('grs_auto_refresh_reviews')) {
        wp_schedule_event(time(), 'monthly', 'grs_auto_refresh_reviews');
    }
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'grs_deactivation_hook');
function grs_deactivation_hook() {
    // Clear cached reviews on deactivation
    delete_transient('grs_reviews');
    delete_transient('grs_total_review_count');

    // Remove scheduled cron
    wp_clear_scheduled_hook('grs_auto_refresh_reviews');
}

// Add custom cron schedule for monthly (30 days)
add_filter('cron_schedules', 'grs_add_cron_schedules');
function grs_add_cron_schedules($schedules) {
    $schedules['monthly'] = array(
        'interval' => 30 * DAY_IN_SECONDS,
        'display' => __('Every 30 Days')
    );
    return $schedules;
}

// Cron handler - auto refresh reviews
add_action('grs_auto_refresh_reviews', 'grs_cron_refresh_reviews');
function grs_cron_refresh_reviews() {
    $options = get_option('grs_settings');
    $place_id = isset($options['grs_place_id']) ? $options['grs_place_id'] : '';
    $data_id = isset($options['grs_data_id']) ? $options['grs_data_id'] : '';

    if (empty($place_id) && empty($data_id)) {
        return;
    }

    require_once(GRS_PLUGIN_PATH . 'includes/serpapi-handler.php');
    require_once(GRS_PLUGIN_PATH . 'includes/database-handler.php');

    $api = new GRS_SerpAPI();

    // Get data_id if not set
    if (empty($data_id) && !empty($place_id)) {
        $data_id = $api->get_data_id_from_place_id($place_id);
        if (is_wp_error($data_id)) {
            error_log('GRS Cron: Failed to get data_id - ' . $data_id->get_error_message());
            return;
        }
    }

    // Delete old reviews first
    GRS_Database::delete_all_reviews($place_id);

    // Extract fresh reviews (only 5 stars, max 15)
    $response = $api->extract_all_reviews($data_id, 15);

    if (!is_wp_error($response)) {
        $api->process_reviews_response($response, $place_id);
        error_log('GRS Cron: Successfully refreshed reviews for ' . $place_id);
    } else {
        error_log('GRS Cron: Failed to extract reviews - ' . $response->get_error_message());
    }

    // Clear transients
    delete_transient('grs_reviews');
    delete_transient('grs_total_review_count');
}

// Enqueue styles and scripts
function grs_enqueue_assets() {
    wp_enqueue_style('dashicons');
    // Slick removed — using Swiper only (loaded in shortcode.php)

    // Localize script for AJAX
    wp_enqueue_script('grs-dummy', '', array(), GRS_VERSION, true);
    wp_localize_script('grs-dummy', 'grs_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('grs_nonce'),
        'version' => GRS_VERSION
    ));
}
add_action('wp_enqueue_scripts', 'grs_enqueue_assets');

// All styles handled by grs-direct.css — no inline overrides needed

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
include(GRS_PLUGIN_PATH . 'includes/database-handler.php');
include(GRS_PLUGIN_PATH . 'includes/serpapi-handler.php');
include(GRS_PLUGIN_PATH . 'includes/admin-page.php');
include(GRS_PLUGIN_PATH . 'includes/shortcode.php');
include(GRS_PLUGIN_PATH . 'includes/api-handler.php');
include(GRS_PLUGIN_PATH . 'includes/reviews-manager.php');


// Initialize GitHub-based auto-updater
require_once(GRS_PLUGIN_PATH . 'includes/plugin-updater.php');

if (is_admin()) {
    new GRS_Plugin_Updater(
        GRS_GITHUB_USERNAME,
        GRS_GITHUB_REPOSITORY,
        __FILE__,
        GRS_VERSION
    );
}

// Add AJAX endpoint for clearing cache
add_action('wp_ajax_grs_clear_cache', 'grs_clear_cache_callback');
function grs_clear_cache_callback() {
    // Check nonce
    if (!check_ajax_referer('grs_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    delete_transient('grs_reviews');
    delete_transient('grs_total_review_count');
    
    wp_send_json_success('Cache cleared successfully');
}


// Simple test AJAX handler
add_action('wp_ajax_grs_test_ajax', 'grs_test_ajax_handler');
function grs_test_ajax_handler() {
    wp_send_json_success(array('message' => 'AJAX is working!'));
}

// AJAX handler for checking API usage
add_action('wp_ajax_grs_check_api_usage', 'grs_check_api_usage_handler');
function grs_check_api_usage_handler() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        return;
    }
    
    // Verify nonce
    if (!check_ajax_referer('grs_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    // Get API usage from SerpAPI
    $api = new GRS_SerpAPI();
    $usage = $api->get_account_info();

    if (is_wp_error($usage)) {
        wp_send_json_error('Unable to retrieve usage information');
        return;
    }

    // Format the usage data for SerpAPI
    $formatted = array(
        'Plan' => $usage['plan_name'] ?? 'N/A',
        'Searches Left (Monthly)' => $usage['plan_searches_left'] ?? 'N/A',
        'Total Searches Left' => $usage['total_searches_left'] ?? 'N/A',
        'Account' => $usage['account_email'] ?? 'N/A'
    );

    wp_send_json_success($formatted);
}

// AJAX handler for checking plugin updates
add_action('wp_ajax_grs_check_for_updates', 'grs_check_for_updates_handler');
function grs_check_for_updates_handler() {
    // Check permissions
    if (!current_user_can('update_plugins')) {
        wp_send_json_error('Unauthorized access');
        return;
    }

    // Verify nonce
    if (!check_ajax_referer('grs_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }

    // Clear update cache to force fresh check
    delete_site_transient('update_plugins');
    delete_transient('grs_github_release_' . md5('https://api.github.com/repos/CachoMX/GoogleReviewsSlider-WP-Plugin/releases/latest'));

    // Check GitHub for latest version
    $github_api_url = 'https://api.github.com/repos/CachoMX/GoogleReviewsSlider-WP-Plugin/releases/latest';
    $response = wp_remote_get($github_api_url, array(
        'timeout' => 15,
        'headers' => array('Accept' => 'application/vnd.github.v3+json')
    ));

    if (is_wp_error($response)) {
        wp_send_json_error('Could not connect to GitHub: ' . $response->get_error_message());
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($response_code !== 200 || empty($data['tag_name'])) {
        wp_send_json_error('Invalid response from GitHub');
        return;
    }

    $latest_version = ltrim($data['tag_name'], 'v');
    $current_version = GRS_VERSION;
    $update_available = version_compare($current_version, $latest_version, '<');

    // Trigger WordPress to check for updates
    wp_update_plugins();

    wp_send_json_success(array(
        'update_available' => $update_available,
        'current_version' => $current_version,
        'latest_version' => $latest_version,
        'plugins_url' => admin_url('plugins.php')
    ));
}