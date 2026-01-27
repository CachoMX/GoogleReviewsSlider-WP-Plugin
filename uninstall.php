<?php
/**
 * Fired when the plugin is uninstalled.
 * Cleans up all plugin data from the database.
 */

// Exit if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('grs_settings');

// Delete transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_grs_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_grs_%'");

// Drop custom tables
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}grs_reviews");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}grs_extraction_log");
