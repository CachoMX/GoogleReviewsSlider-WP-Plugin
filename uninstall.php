<?php
/**
 * Fired when the plugin is uninstalled.
 * Cleans up all plugin data from the database.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('grs_settings');
delete_option('grs_version');
delete_option('grs_db_version');
delete_option('grs_business_info');
delete_option('grs_sync_status');
delete_option('grs_sync_lock');
delete_option('grs_resync_pending');
delete_option('grs_was_active_before_update');

global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'grs_notice_seen_version'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_grs_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_grs_%'");

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}grs_reviews");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}grs_extraction_history");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}grs_api_log");

wp_clear_scheduled_hook('grs_auto_refresh_reviews');
