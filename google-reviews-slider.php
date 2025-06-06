<?php
/**
 * Plugin Name: Google Reviews Slider
 * Description: Displays Google Reviews in a slider format.
 * Version: 1.0
 * Author: Carlos Aragon
 * Author URI: https://carlosaragon.online
 * Text Domain: google-reviews-slider
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Enqueue styles and scripts
function grs_enqueue_assets() {
    wp_enqueue_style('dashicons');
    wp_enqueue_style('grs-slick-css', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.css');
    wp_enqueue_style('grs-slick-theme-css', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.css');
    wp_enqueue_style('grs-style', plugins_url('css/style.css', __FILE__));
    
    wp_enqueue_script('grs-slick-js', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js', array('jquery'), null, true);
    wp_enqueue_script('grs-script', plugins_url('js/script.js', __FILE__), array('jquery', 'grs-slick-js'), null, true);
}
add_action('wp_enqueue_scripts', 'grs_enqueue_assets');

// Include necessary files
include(plugin_dir_path(__FILE__) . 'includes/admin-page.php');
include(plugin_dir_path(__FILE__) . 'includes/shortcode.php');
include(plugin_dir_path(__FILE__) . 'includes/api-handler.php');