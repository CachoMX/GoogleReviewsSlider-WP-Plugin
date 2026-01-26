<?php
/**
 * Plugin Name: Google Reviews Slider
 * Description: Displays Google Reviews in a slider format with enhanced features and improved features.
 * Version: 2.5.1
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
define('GRS_VERSION', '2.5.1');
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
        // Pre-populate Outscraper token so API requests work out of the box
        'grs_outscraper_token' => 'ODJhYTBmZjFkMmY5NGQ1Nzk0MGYwZmI0Y2JhMWZhYWZ8ODhmZDYxYmI3Yg',
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

    // Enable mobile debug mode with ?grs_debug=1 or if WP_DEBUG is true
    $enable_debug = (defined('WP_DEBUG') && WP_DEBUG) || isset($_GET['grs_debug']);
    if ($enable_debug) {
        wp_enqueue_script('grs-mobile-debug', GRS_PLUGIN_URL . 'js/mobile-debug.js', array('jquery'), GRS_VERSION, true);
    }

    wp_enqueue_script('grs-script', GRS_PLUGIN_URL . 'js/script.js', array('jquery', 'grs-slick-js'), GRS_VERSION, true);
    
    // Localize script for AJAX and other data
    wp_localize_script('grs-script', 'grs_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('grs_nonce'),
        'version' => GRS_VERSION
    ));
}
add_action('wp_enqueue_scripts', 'grs_enqueue_assets');

// Add mobile-specific inline styles
add_action('wp_head', 'grs_mobile_inline_styles', 999);
function grs_mobile_inline_styles() {
    // Only add on pages with the shortcode
    global $post;
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'google_reviews_slider')) {
        return;
    }
    ?>
    <style id="grs-mobile-fixes">
    /* Critical mobile fixes for Google Reviews Slider */
    @media screen and (max-width: 768px) {
        .grs-direct-wrapper {
            display: block !important;
            width: 100% !important;
            padding: 0 15px !important;
        }
        
        .grs-direct-slider {
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
            min-height: 300px !important;
        }
        
        .grs-direct-slider .slick-slide {
            opacity: 1 !important;
            visibility: visible !important;
            height: auto !important;
        }
        
        .grs-direct-review {
            opacity: 1 !important;
            visibility: visible !important;
            display: flex !important;
            min-height: 200px !important;
            background: white !important;
            border: 1px solid #e8e8e8 !important;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1) !important;
        }
        
        .grs-direct-slider .slick-track {
            display: flex !important;
        }
        
        .grs-direct-slider .slick-initialized .slick-slide {
            display: block !important;
        }
        
        /* Force review content to show */
        .grs-direct-header,
        .grs-direct-profile-img,
        .grs-direct-profile-details,
        .grs-direct-name,
        .grs-direct-date,
        .grs-direct-stars,
        .grs-direct-content,
        .grs-direct-text {
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
        }
        
        .grs-direct-header {
            display: flex !important;
        }
        
        .grs-direct-stars {
            display: flex !important;
        }
        
        .grs-direct-stars .dashicons-star-filled {
            color: #FFC107 !important;
            font-size: 16px !important;
            width: 16px !important;
            height: 16px !important;
        }
        
        /* Ensure text is readable */
        .grs-direct-text {
            color: #333 !important;
            font-size: 13px !important;
            line-height: 1.5 !important;
        }
        
        .grs-direct-name {
            color: #000 !important;
            font-weight: 700 !important;
        }
        
        .grs-direct-date {
            color: #666 !important;
        }
        
        /* Fix truncation on mobile */
        .grs-direct-text.truncated {
            display: -webkit-box !important;
            -webkit-line-clamp: 4 !important;
            -webkit-box-orient: vertical !important;
            overflow: hidden !important;
        }
        
        /* Summary box mobile */
        .grs-direct-summary {
            background: #ffffff !important;
            padding: 20px !important;
            margin: 0 auto 20px auto !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
            text-align: center !important;
        }
        
        /* Profile images */
        .grs-direct-profile-img img {
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            display: block !important;
        }
    }
    
    /* Additional fix for iOS Safari */
    @supports (-webkit-touch-callout: none) {
        .grs-direct-slider,
        .grs-direct-slider * {
            -webkit-transform: translate3d(0, 0, 0);
        }
    }
    </style>
    
    <script>
    // Mobile-specific JavaScript fixes
    (function() {
        if (window.innerWidth <= 768) {
            document.addEventListener('DOMContentLoaded', function() {
                // Force display of reviews on mobile
                setTimeout(function() {
                    var reviews = document.querySelectorAll('.grs-direct-review');
                    reviews.forEach(function(review) {
                        review.style.opacity = '1';
                        review.style.visibility = 'visible';
                        review.style.display = 'flex';
                    });
                    
                    var sliders = document.querySelectorAll('.grs-direct-slider');
                    sliders.forEach(function(slider) {
                        slider.style.opacity = '1';
                        slider.style.visibility = 'visible';
                        slider.style.minHeight = '300px';
                    });
                    
                    // Trigger resize event to refresh slider
                    if (typeof jQuery !== 'undefined' && jQuery('.grs-direct-slider').hasClass('slick-initialized')) {
                        jQuery('.grs-direct-slider').slick('refresh');
                    }
                }, 500);
            });
        }
    })();
    </script>
    <?php
}

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
include(GRS_PLUGIN_PATH . 'includes/outscraper-api.php');
include(GRS_PLUGIN_PATH . 'includes/admin-page.php');
include(GRS_PLUGIN_PATH . 'includes/shortcode.php');
include(GRS_PLUGIN_PATH . 'includes/api-handler.php');
include(GRS_PLUGIN_PATH . 'includes/reviews-manager.php');

// TEMPORARY: Force load mobile debug on all pages with slider
add_action('wp_footer', 'grs_force_mobile_debug', 9999);
function grs_force_mobile_debug() {
    // Only on mobile devices
    if (!wp_is_mobile()) {
        return;
    }

    // Check if slider is on page
    global $post;
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'google_reviews_slider')) {
        return;
    }

    ?>
    <div id="grs-live-debug" style="position:fixed;bottom:0;left:0;right:0;background:rgba(0,0,0,0.95);color:#0f0;font-family:monospace;font-size:10px;padding:10px;max-height:200px;overflow-y:auto;z-index:999999;border-top:2px solid #0f0;">
        <div style="color:#ff0;font-weight:bold;margin-bottom:5px;">🔴 LIVE DEBUG - Mobile Slider</div>
    </div>
    <script>
    (function() {
        var debugEl = document.getElementById('grs-live-debug');
        var logs = [];

        function log(msg, color) {
            color = color || '#0f0';
            var time = new Date().toLocaleTimeString();
            var line = '<div style="color:' + color + '">[' + time + '] ' + msg + '</div>';
            logs.push(line);
            debugEl.innerHTML = '<div style="color:#ff0;font-weight:bold;margin-bottom:5px;">🔴 LIVE DEBUG - Mobile Slider (Tap to hide/show)</div>' + logs.slice(-20).join('');
            debugEl.scrollTop = debugEl.scrollHeight;
            console.log('[GRS] ' + msg);
        }

        // Toggle debug
        debugEl.addEventListener('click', function() {
            if (this.style.maxHeight === '30px') {
                this.style.maxHeight = '200px';
            } else {
                this.style.maxHeight = '30px';
            }
        });

        log('=== DEBUG STARTED ===', '#fff');
        log('Device: ' + (navigator.userAgent.includes('iPhone') ? 'iPhone' : navigator.userAgent.includes('Android') ? 'Android' : 'Unknown'));
        log('Screen: ' + screen.width + 'x' + screen.height);
        log('Window: ' + window.innerWidth + 'x' + window.innerHeight);
        log('Touch: ' + ('ontouchstart' in window ? 'YES' : 'NO'));

        // Check jQuery
        setTimeout(function() {
            if (typeof jQuery === 'undefined') {
                log('❌ jQuery NOT LOADED!', '#f00');
                return;
            }
            log('✅ jQuery loaded: v' + jQuery.fn.jquery, '#0f0');

            // Check Slick
            if (typeof jQuery.fn.slick === 'undefined') {
                log('❌ Slick NOT LOADED!', '#f00');
            } else {
                log('✅ Slick loaded', '#0f0');
            }

            // Check slider
            var $ = jQuery;
            var sliders = $('.grs-direct-slider');
            log('Sliders found: ' + sliders.length, sliders.length > 0 ? '#0f0' : '#f00');

            if (sliders.length > 0) {
                var initialized = sliders.hasClass('slick-initialized');
                log('Slider initialized: ' + initialized, initialized ? '#0f0' : '#f00');

                // Monitor initialization
                var checkCount = 0;
                var checkInit = setInterval(function() {
                    checkCount++;
                    if ($('.grs-direct-slider').hasClass('slick-initialized')) {
                        clearInterval(checkInit);
                        log('✅ Slider NOW initialized!', '#0f0');

                        var $slider = $('.grs-direct-slider');
                        var prevArrow = $slider.find('.slick-prev');
                        var nextArrow = $slider.find('.slick-next');

                        log('Prev arrow: ' + prevArrow.length + ' (visible: ' + prevArrow.is(':visible') + ')');
                        log('Next arrow: ' + nextArrow.length + ' (visible: ' + nextArrow.is(':visible') + ')');

                        // Check CSS values
                        var $track = $slider.find('.slick-track');
                        var $list = $slider.find('.slick-list');
                        var $slides = $slider.find('.slick-slide');

                        log('🔍 CSS DEBUG:', '#fff');
                        log('Track display: ' + $track.css('display'));
                        log('Track width: ' + $track.width() + 'px');
                        log('Track transform: ' + $track.css('transform'));
                        log('List width: ' + $list.width() + 'px');
                        log('Slide count: ' + $slides.length);
                        log('Slide width: ' + $slides.first().width() + 'px');
                        log('Slide display: ' + $slides.first().css('display'));
                        log('Slide float: ' + $slides.first().css('float'));

                        // Monitor arrow clicks
                        prevArrow.on('touchstart click', function(e) {
                            log('👆 PREV arrow ' + e.type, '#ff0');
                        });

                        nextArrow.on('touchstart click', function(e) {
                            log('👆 NEXT arrow ' + e.type, '#ff0');
                        });

                        // Monitor slide changes - LOG TRANSFORM
                        $slider.on('beforeChange', function(e, slick, current, next) {
                            log('📍 Slide: ' + current + ' → ' + next, '#ff0');
                        });

                        $slider.on('afterChange', function(e, slick, current) {
                            var transform = $track.css('transform');
                            log('Transform now: ' + transform, '#0ff');
                        });

                        // FORCE FIX IMMEDIATELY - NUCLEAR OPTION
                        setTimeout(function() {
                            log('🔧 FORCING CSS FIX (NUCLEAR)...', '#f0f');

                            // Use setAttribute to bypass jQuery and CSS cascade
                            $slider.find('.slick-slide').each(function() {
                                var currentStyle = this.getAttribute('style') || '';
                                this.setAttribute('style', currentStyle + '; float: left !important; display: block !important;');
                            });

                            var trackEl = $slider.find('.slick-track')[0];
                            if (trackEl) {
                                var trackStyle = trackEl.getAttribute('style') || '';
                                trackEl.setAttribute('style', trackStyle + '; display: block !important; position: relative !important;');
                            }

                            // Force Slick to recalculate EVERYTHING
                            $slider.slick('setPosition');
                            $slider.slick('refresh');

                            // Re-check after a moment
                            setTimeout(function() {
                                var newFloat = window.getComputedStyle($slider.find('.slick-slide')[0]).float;
                                var newTrackDisplay = window.getComputedStyle(trackEl).display;

                                log('✅ NUCLEAR - Slide float: ' + newFloat, newFloat === 'left' ? '#0f0' : '#f00');
                                log('✅ NUCLEAR - Track display: ' + newTrackDisplay, newTrackDisplay === 'block' ? '#0f0' : '#f00');

                                if (newFloat === 'left' && newTrackDisplay === 'block') {
                                    log('🎉 SUCCESS! CSS FIXED!', '#0f0');
                                } else {
                                    log('❌ FAILED. Styles still overridden.', '#f00');
                                }
                            }, 200);
                        }, 100);

                    } else if (checkCount > 50) {
                        clearInterval(checkInit);
                        log('❌ Slider NEVER initialized!', '#f00');
                    }
                }, 100);
            }

            // Monitor touch events on arrows
            $(document).on('touchstart', '.slick-arrow', function(e) {
                log('👆 Touch on: ' + $(this).attr('class'), '#ff0');
            });

        }, 1000);
    })();
    </script>
    <?php
}

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
    
    // Get API usage
    $api = new GRS_Outscraper_API();
    $usage = $api->get_usage_info();
    
    if (is_wp_error($usage)) {
        wp_send_json_error('Unable to retrieve usage information');
        return;
    }
    
    // Format the usage data
    $formatted = array();
    if (isset($usage['credits_left'])) {
        $formatted['Credits Remaining'] = $usage['credits_left'];
    }
    if (isset($usage['requests_left'])) {
        $formatted['Requests Remaining'] = $usage['requests_left'];
    }
    if (isset($usage['plan'])) {
        $formatted['Plan'] = $usage['plan'];
    }
    
    // If no specific fields found, return all data
    if (empty($formatted)) {
        $formatted = $usage;
    }
    
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