<?php
/**
 * Plugin Name: Google Reviews Slider
 * Description: Displays Google Reviews in a slider format with enhanced features and improved features.
 * Version: 2.8.0
 * Author: Carlos Aragon
 * Author URI: https://carlosaragon.online
 * Text Domain: google-reviews-slider
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://carlosaragon.online/plugins/google-reviews-slider/
 * Requires at least: 5.3
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('GRS_VERSION', '2.8.0');
define('GRS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GRS_PLUGIN_PATH', plugin_dir_path(__FILE__));

// GitHub repository info for automatic updates
define('GRS_GITHUB_USERNAME', 'CachoMX');
define('GRS_GITHUB_REPOSITORY', 'GoogleReviewsSlider-WP-Plugin');

register_activation_hook(__FILE__, 'grs_activation_hook');
function grs_activation_hook() {
    $default_options = array(
        'grs_api_key' => '',
        'grs_place_id' => '',
        'grs_min_rating' => '1',
        // Set per-site in wp-admin or via the GRS_SERPAPI_KEY constant; never hardcode a key here.
        'grs_serpapi_key' => '',
        'grs_data_id' => '',
    );

    $existing_options = get_option('grs_settings', array());
    $options = wp_parse_args($existing_options, $default_options);
    update_option('grs_settings', $options);

    update_option('grs_version', GRS_VERSION);

    require_once(GRS_PLUGIN_PATH . 'includes/database-handler.php');
    GRS_Database::init();

    if (!wp_next_scheduled('grs_auto_refresh_reviews')) {
        wp_schedule_event(time() + DAY_IN_SECONDS, 'monthly', 'grs_auto_refresh_reviews');
    }
}

register_deactivation_hook(__FILE__, 'grs_deactivation_hook');
function grs_deactivation_hook() {
    wp_clear_scheduled_hook('grs_auto_refresh_reviews');
    delete_option('grs_sync_lock');
}

add_filter('cron_schedules', 'grs_add_cron_schedules');
function grs_add_cron_schedules($schedules) {
    // Another plugin may already define 'monthly'; overriding it would
    // silently change that plugin's cadence.
    if (!isset($schedules['monthly'])) {
        $schedules['monthly'] = array(
            'interval' => 30 * DAY_IN_SECONDS,
            'display' => __('Every 30 Days', 'google-reviews-slider'),
        );
    }
    return $schedules;
}

add_action('grs_auto_refresh_reviews', 'grs_cron_refresh_reviews');
function grs_cron_refresh_reviews() {
    require_once(GRS_PLUGIN_PATH . 'includes/sync-handler.php');
    $result = GRS_Sync::run('cron');

    if ($result['status'] !== 'ok' && $result['status'] !== 'skipped') {
        error_log('GRS Cron: sync ended with status ' . $result['status'] . ' - ' . $result['message']);
    }
}

/**
 * Version-gated upgrade routine. Runs once per version bump, before any
 * admin page or cron handler touches the new schema.
 */
add_action('plugins_loaded', 'grs_maybe_upgrade');
function grs_maybe_upgrade() {
    $installed = get_option('grs_version', '0');

    if (version_compare($installed, GRS_VERSION, '>=')) {
        return;
    }

    require_once(GRS_PLUGIN_PATH . 'includes/database-handler.php');
    GRS_Database::init();

    if (version_compare($installed, '2.8.0', '<')) {
        grs_upgrade_to_280();
    }

    if (!wp_next_scheduled('grs_auto_refresh_reviews')) {
        wp_schedule_event(time() + DAY_IN_SECONDS, 'monthly', 'grs_auto_refresh_reviews');
    }

    update_option('grs_version', GRS_VERSION);
}

/**
 * 2.8.0 migration: business info moves out of grs_settings into its own
 * option, the legacy Google Places transients die with their pipeline,
 * and stored reviews get pruned to the new per-place cap.
 */
function grs_upgrade_to_280() {
    $options = get_option('grs_settings', array());

    $info = array();
    if (!empty($options['grs_business_name'])) {
        $info['name'] = $options['grs_business_name'];
    }
    if (!empty($options['grs_business_rating'])) {
        $info['rating'] = floatval($options['grs_business_rating']);
    }
    if (!empty($options['grs_total_reviews'])) {
        $info['total_reviews'] = intval($options['grs_total_reviews']);
    }
    if (!empty($info)) {
        $info['updated_at'] = time();
        update_option('grs_business_info', array_merge(get_option('grs_business_info', array()), $info));
    }

    unset($options['grs_business_name'], $options['grs_business_rating'], $options['grs_total_reviews']);
    update_option('grs_settings', $options);

    delete_transient('grs_reviews');
    delete_transient('grs_total_review_count');
    delete_transient('grs_google_total_reviews');

    if (!empty($options['grs_place_id'])) {
        GRS_Database::prune_reviews($options['grs_place_id']);
    }
}

add_action('admin_notices', 'grs_update_notice');
function grs_update_notice() {
    if (get_option('grs_notice_seen_version') === GRS_VERSION) {
        return;
    }
    update_option('grs_notice_seen_version', GRS_VERSION, false);

    echo '<div class="notice notice-info is-dismissible">';
    echo '<p><strong>Google Reviews Slider</strong> ' . esc_html__('has been updated to version', 'google-reviews-slider') . ' ' . esc_html(GRS_VERSION) . '! ';
    echo '<a href="' . esc_url(admin_url('admin.php?page=google_reviews_slider')) . '">' . esc_html__('View what\'s new', 'google-reviews-slider') . '</a></p>';
    echo '</div>';
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'grs_add_settings_link');
function grs_add_settings_link($links) {
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=google_reviews_slider')) . '">' . esc_html__('Settings', 'google-reviews-slider') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

include(GRS_PLUGIN_PATH . 'includes/database-handler.php');
include(GRS_PLUGIN_PATH . 'includes/serpapi-handler.php');
include(GRS_PLUGIN_PATH . 'includes/sync-handler.php');
include(GRS_PLUGIN_PATH . 'includes/admin-page.php');
include(GRS_PLUGIN_PATH . 'includes/shortcode.php');
include(GRS_PLUGIN_PATH . 'includes/reviews-manager.php');

require_once(GRS_PLUGIN_PATH . 'includes/plugin-updater.php');

if (is_admin()) {
    new GRS_Plugin_Updater(
        GRS_GITHUB_USERNAME,
        GRS_GITHUB_REPOSITORY,
        __FILE__,
        GRS_VERSION
    );
}

// AJAX handler for checking API usage
add_action('wp_ajax_grs_check_api_usage', 'grs_check_api_usage_handler');
function grs_check_api_usage_handler() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        return;
    }

    if (!check_ajax_referer('grs_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }

    $api = new GRS_SerpAPI();
    $usage = $api->get_account_info();

    if (is_wp_error($usage)) {
        wp_send_json_error('Unable to retrieve usage information');
        return;
    }

    $formatted = array(
        'Plan' => isset($usage['plan_name']) ? $usage['plan_name'] : 'N/A',
        'Searches Left (Monthly)' => isset($usage['plan_searches_left']) ? $usage['plan_searches_left'] : 'N/A',
        'Total Searches Left' => isset($usage['total_searches_left']) ? $usage['total_searches_left'] : 'N/A',
        'Account' => isset($usage['account_email']) ? $usage['account_email'] : 'N/A',
    );

    wp_send_json_success($formatted);
}

// AJAX handler for checking plugin updates
add_action('wp_ajax_grs_check_for_updates', 'grs_check_for_updates_handler');
function grs_check_for_updates_handler() {
    if (!current_user_can('update_plugins')) {
        wp_send_json_error('Unauthorized access');
        return;
    }

    if (!check_ajax_referer('grs_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }

    delete_site_transient('update_plugins');
    delete_transient('grs_github_release_' . md5('https://api.github.com/repos/CachoMX/GoogleReviewsSlider-WP-Plugin/releases/latest'));

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

    wp_update_plugins();

    wp_send_json_success(array(
        'update_available' => $update_available,
        'current_version' => $current_version,
        'latest_version' => $latest_version,
        'plugins_url' => admin_url('plugins.php')
    ));
}
