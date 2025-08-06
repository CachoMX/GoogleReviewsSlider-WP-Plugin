<?php
// Updated includes/shortcode.php - Mobile Display Fix
/**
 * Google Reviews Slider - Fixed Shortcode with Mobile Compatibility
 * Enhanced implementation with comprehensive mobile support
 */

add_action('init', 'grs_direct_init');

function grs_direct_init() {
    add_shortcode('google_reviews_slider', 'grs_direct_display');
    add_action('wp_enqueue_scripts', 'grs_direct_enqueue_assets');
}

function grs_direct_enqueue_assets() {
    // Check if shortcode is present
    global $post;
    $has_shortcode = false;
    
    if (is_a($post, 'WP_Post')) {
        $has_shortcode = has_shortcode($post->post_content, 'google_reviews_slider');
    }
    
    // Also check for Avada live builder or admin
    $is_avada_builder = isset($_GET['builder']) || 
                       (function_exists('fusion_is_preview_frame') && fusion_is_preview_frame()) ||
                       is_admin();
    
    if (!$has_shortcode && !$is_avada_builder) {
        return;
    }
    
    // Enqueue required WordPress assets
    wp_enqueue_style('dashicons');
    wp_enqueue_script('jquery');
    
    // Slick carousel from CDN
    wp_enqueue_style('grs-slick', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css', array(), '1.8.1');
    wp_enqueue_style('grs-slick-theme', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css', array(), '1.8.1');
    wp_enqueue_script('grs-slick-js', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js', array('jquery'), '1.8.1', true);
    
    // Custom styles and scripts with cache busting
    $version = get_option('grs_version', '2.0') . '.' . time();
    wp_enqueue_style('grs-direct-styles', plugins_url('css/grs-direct.css', dirname(__FILE__)), array('grs-slick', 'grs-slick-theme'), $version);
    wp_enqueue_script('grs-direct-script', plugins_url('js/script.js', dirname(__FILE__)), array('jquery', 'grs-slick-js'), $version, true);
    
    // Localize script with enhanced data
    wp_localize_script('grs-direct-script', 'grsData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('grs_nonce'),
        'pluginUrl' => plugins_url('', dirname(__FILE__)),
        'isDebug' => defined('WP_DEBUG') && WP_DEBUG,
        'isMobile' => wp_is_mobile(),
        'isAvada' => function_exists('fusion_builder_container'),
        'version' => $version
    ));
}

function grs_direct_display($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'show_summary' => 'true',
        'min_rating' => null,
        'autoplay' => 'true',
        'autoplay_speed' => '4000',
        'slides_desktop' => '3',
        'slides_tablet' => '2', 
        'slides_mobile' => '1'
    ), $atts, 'google_reviews_slider');
    
    // Get plugin settings
    $options = get_option('grs_settings');
    $place_id = isset($options['grs_place_id']) ? $options['grs_place_id'] : '';
    
    if (empty($place_id)) {
        if (current_user_can('manage_options')) {
            return '<div class="grs-error" style="padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404; margin: 20px 0;">
                <strong>Google Reviews Slider:</strong> Place ID not configured.
                <br><small>This message is only visible to administrators. <a href="' . admin_url('admin.php?page=google_reviews_slider') . '">Configure Settings</a></small>
            </div>';
        }
        return '';
    }
    
    // Load database handler
    require_once(plugin_dir_path(dirname(__FILE__)) . 'includes/database-handler.php');
    
    // Determine minimum rating
    $min_rating = $atts['min_rating'] !== null ? intval($atts['min_rating']) : 
                  (isset($options['grs_min_rating']) ? intval($options['grs_min_rating']) : 1);
    
    // Get reviews from database
    $reviews = GRS_Database::get_reviews($place_id, $min_rating, 50);
    
    // If no reviews in database, try to get from Google API first
    if (empty($reviews)) {
        // Try cached transient data first
        $cached_reviews = get_transient('grs_reviews');
        
        if ($cached_reviews === false) {
            // Try to fetch from Google API
            if (!function_exists('grs_get_reviews')) {
                include_once(plugin_dir_path(dirname(__FILE__)) . 'includes/api-handler.php');
            }
            
            $result = grs_get_reviews();
            
            if (!isset($result['error']) && isset($result['reviews'])) {
                $reviews = $result['reviews'];
                // Save to database for future use
                GRS_Database::save_reviews($place_id, $reviews);
            }
        } else {
            $reviews = $cached_reviews;
            // Save cached reviews to database
            GRS_Database::save_reviews($place_id, $reviews);
        }
    }
    
    // Get stats for the summary    
    $stats = GRS_Database::get_review_stats($place_id);

    // Get the actual total from Google instead of database count
    $options = get_option('grs_settings');
    $api_key = isset($options['grs_api_key']) ? $options['grs_api_key'] : '';

    // Try to get the real total from Google
    $google_total = get_transient('grs_google_total_reviews');
    if ($google_total === false && !empty($api_key)) {
        // Make API call to get place details
        $url = "https://maps.googleapis.com/maps/api/place/details/json?placeid=$place_id&key=$api_key&fields=user_ratings_total";
        $response = wp_remote_get($url);
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['result']['user_ratings_total'])) {
                $google_total = $data['result']['user_ratings_total'];
                // Cache for 24 hours
                set_transient('grs_google_total_reviews', $google_total, DAY_IN_SECONDS);
            }
        }
    }

    // Use Google total if available, otherwise fall back to database count
    $total_review_count = $google_total !== false ? $google_total : $stats['total'];
    
    // Check if we have reviews to display
    if (empty($reviews)) {
        if (current_user_can('manage_options')) {
            return '<div class="grs-error" style="padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404; margin: 20px 0;">
                <strong>Google Reviews Slider:</strong> No reviews found. 
                <br>Please <a href="' . admin_url('admin.php?page=google_reviews_slider') . '">extract reviews</a> using the admin panel.
                <br><small>This message is only visible to administrators.</small>
            </div>';
        }
        return '';
    }
    
    // Calculate average rating
    $average_rating = $stats['average'] ?: 5;
    
    // Generate unique ID for this slider instance
    $unique_id = 'grs-slider-' . uniqid();
    
    // Detect theme and device for compatibility
    $is_mobile = wp_is_mobile();
    $is_avada = function_exists('fusion_builder_container');
    $body_classes = '';
    
    if ($is_avada) {
        $body_classes .= ' grs-avada-theme';
    }
    if ($is_mobile) {
        $body_classes .= ' grs-mobile-device';
    }
    
    // Start output buffering
    ob_start();
    ?>
    
    <!-- Critical CSS for immediate visibility -->
    <style id="grs-critical-<?php echo esc_attr($unique_id); ?>">
    .grs-direct-wrapper {
        opacity: 1 !important;
        visibility: visible !important;
        display: block !important;
    }
    .grs-direct-slider {
        opacity: 1 !important;
        visibility: visible !important;
        display: block !important;
        min-height: 250px !important;
    }
    .grs-direct-review {
        opacity: 1 !important;
        visibility: visible !important;
        display: flex !important;
        flex-direction: column !important;
        background: white !important;
        border: 1px solid #e8e8e8 !important;
        padding: 15px !important;
        margin-bottom: 15px !important;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1) !important;
    }
    .grs-direct-summary {
<<<<<<< HEAD
        background: #ffffffff !important;
=======
        background: #FFF9C4 !important;
>>>>>>> 2998ed01a3d308cd73b0a89055f4df70d677bbf8
        margin: 0 auto !important;
        text-align: center !important;
    }
    .grs-direct-text {
        color: #333 !important;
        opacity: 1 !important;
        visibility: visible !important;
        display: block !important;
    }
    
    /* Mobile-specific critical styles */
    @media screen and (max-width: 768px) {
        .grs-direct-wrapper {
            padding: 0 15px !important;
            overflow-x: hidden !important;
        }
        .grs-direct-container {
            flex-direction: column !important;
        }
        .grs-direct-summary {
            width: 100% !important;
            margin: 0 auto 20px auto !important;
            position: static !important;
<<<<<<< HEAD
            background: #ffffffff !important;
=======
            background: #FFF9C4 !important;
>>>>>>> 2998ed01a3d308cd73b0a89055f4df70d677bbf8
        }
        .grs-direct-slider {
            min-height: 320px !important;
        }
        .grs-direct-review {
            min-height: 280px !important;
            margin: 0 !important;
            padding: 20px !important;
        }
        .grs-direct-slider .slick-slide {
            width: 100% !important;
            padding: 0 5px !important;
        }
        .grs-direct-text {
            font-size: 14px !important;
            line-height: 1.6 !important;
        }
    }
    </style>
    
    <div class="grs-direct-wrapper<?php echo esc_attr($body_classes); ?>" 
         id="<?php echo esc_attr($unique_id); ?>-wrapper"
         data-theme="<?php echo $is_avada ? 'avada' : 'default'; ?>"
         data-device="<?php echo $is_mobile ? 'mobile' : 'desktop'; ?>">
        
        <div class="grs-direct-container">
            <?php if ($atts['show_summary'] === 'true') : ?>
            <div class="grs-direct-summary">
                <div class="grs-direct-rating-large">EXCELLENT</div>
                <div class="grs-direct-stars">
                    <?php for ($i = 0; $i < 5; $i++) : ?>
                        <span class="dashicons dashicons-star-filled"></span>
                    <?php endfor; ?>
                </div>
                <div class="grs-direct-rating-text">
                    Based on <strong><?php echo esc_html($total_review_count); ?> reviews</strong>
                </div>
                <div class="grs-direct-logo">
                    <img src="<?php echo esc_url(plugins_url('assets/google-logo.svg', dirname(__FILE__))); ?>" 
                        alt="Google" width="110" height="35"
                        style="max-width: 110px; height: auto;">
                </div>
            </div>
            <?php endif; ?>
            
            <div class="grs-direct-slider-container">
                <div id="<?php echo esc_attr($unique_id); ?>" 
                     class="grs-direct-slider" 
                     data-slides="<?php echo count($reviews); ?>"
                     data-autoplay="<?php echo esc_attr($atts['autoplay']); ?>"
                     data-autoplay-speed="<?php echo esc_attr($atts['autoplay_speed']); ?>"
                     data-slides-desktop="<?php echo esc_attr($atts['slides_desktop']); ?>"
                     data-slides-tablet="<?php echo esc_attr($atts['slides_tablet']); ?>"
                     data-slides-mobile="<?php echo esc_attr($atts['slides_mobile']); ?>"
                     role="region"
                     aria-label="Customer Reviews Slider">
                    
                    <?php foreach ($reviews as $index => $review) : 
                        $review_text = !empty($review['text']) ? $review['text'] : '(No review text provided)';
                        $needs_truncation = strlen($review_text) > 150;
                        $author_name = esc_html($review['author_name']);
                        $time_description = !empty($review['relative_time_description']) ? 
                            esc_html($review['relative_time_description']) : 
                            date('F Y', $review['time']);
                        $profile_photo = !empty($review['profile_photo_url']) ? 
                            esc_url($review['profile_photo_url']) : 
                            plugins_url('assets/default-avatar.png', dirname(__FILE__));
                        $rating = intval($review['rating']);
                    ?>
                        <div class="grs-direct-slide">
                            <div class="grs-direct-review" 
                                 data-review-index="<?php echo esc_attr($index); ?>"
                                 role="article"
                                 aria-label="Review by <?php echo $author_name; ?>">
                                
                                <div class="grs-direct-header">
                                    <div class="grs-direct-profile-img">
                                        <img src="<?php echo esc_url($profile_photo); ?>" 
                                             alt="<?php echo esc_attr($author_name); ?>"
                                             loading="lazy"
                                             onerror="this.src='<?php echo esc_url(plugins_url('assets/default-avatar.png', dirname(__FILE__))); ?>';">
                                    </div>
                                    <div class="grs-direct-profile-details">
                                        <div class="grs-direct-name"><?php echo $author_name; ?></div>
                                        <div class="grs-direct-date"><?php echo $time_description; ?></div>
                                    </div>
                                </div>
                                
                                <div class="grs-direct-stars small" 
                                     role="img" 
                                     aria-label="<?php echo $rating; ?> out of 5 stars">
                                    <?php for ($i = 0; $i < $rating; $i++) : ?>
                                        <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                                    <?php endfor; ?>
                                </div>
                                
                                <div class="grs-direct-content">
                                    <div class="grs-direct-text <?php echo $needs_truncation ? 'truncated' : ''; ?>"
                                         data-full-text="<?php echo esc_attr($review_text); ?>">
                                        <?php echo esc_html($review_text); ?>
                                    </div>
                                    
                                    <?php if ($needs_truncation) : ?>
                                        <a href="#" class="grs-direct-read-more" 
                                           role="button" 
                                           aria-expanded="false"
                                           aria-label="Read full review">Read more</a>
                                        <a href="#" class="grs-direct-hide" 
                                           style="display:none;" 
                                           role="button" 
                                           aria-expanded="true"
                                           aria-label="Show less of review">Show less</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Initialization script for immediate execution -->
    <script type="text/javascript">
    (function() {
        'use strict';
        
        var sliderId = '<?php echo esc_js($unique_id); ?>';
        var isMobile = window.innerWidth <= 768;
        
        // Immediate visibility fix
        function forceVisibility() {
            var wrapper = document.getElementById(sliderId + '-wrapper');
            if (wrapper) {
                wrapper.style.opacity = '1';
                wrapper.style.visibility = 'visible';
                wrapper.style.display = 'block';
                
                var reviews = wrapper.querySelectorAll('.grs-direct-review');
                for (var i = 0; i < reviews.length; i++) {
                    reviews[i].style.opacity = '1';
                    reviews[i].style.visibility = 'visible';
                    reviews[i].style.display = 'flex';
                    reviews[i].style.flexDirection = 'column';
                }
                
                var texts = wrapper.querySelectorAll('.grs-direct-text');
                for (var j = 0; j < texts.length; j++) {
                    texts[j].style.color = '#333';
                    texts[j].style.opacity = '1';
                    texts[j].style.visibility = 'visible';
                    texts[j].style.display = 'block';
                }
                
                var slider = document.getElementById(sliderId);
                if (slider) {
                    slider.style.minHeight = isMobile ? '320px' : '250px';
                    slider.style.opacity = '1';
                    slider.style.visibility = 'visible';
                    slider.style.display = 'block';
                }
            }
        }
        
        // Apply fixes immediately
        forceVisibility();
        
        // Enhanced DOM ready detection
        function ready(fn) {
            if (document.readyState !== 'loading') {
                fn();
            } else {
                document.addEventListener('DOMContentLoaded', fn);
            }
        }
        
        // Re-apply visibility fixes when DOM is ready
        ready(function() {
            forceVisibility();
            
            // Additional mobile fixes
            if (isMobile) {
                setTimeout(forceVisibility, 100);
                setTimeout(forceVisibility, 500);
                setTimeout(forceVisibility, 1000);
            }
        });
        
        // Mobile-specific initialization
        if (isMobile) {
            // Set interval to keep enforcing visibility
            var visibilityInterval = setInterval(function() {
                var slider = document.getElementById(sliderId);
                if (slider && slider.classList.contains('slick-initialized')) {
                    clearInterval(visibilityInterval);
                } else {
                    forceVisibility();
                }
            }, 200);
            
            // Clear interval after 5 seconds
            setTimeout(function() {
                clearInterval(visibilityInterval);
            }, 5000);
        }
        
        <?php if ($is_avada): ?>
        // Avada live builder compatibility
        if (window.FusionEvents) {
            window.FusionEvents.on('fusion-element-render-fusion_text', function() {
                setTimeout(forceVisibility, 100);
            });
        }
        <?php endif; ?>
        
    })();
    </script>
    
    <!-- Fallback CSS for non-JavaScript users -->
    <noscript>
        <style>
        .grs-direct-wrapper {
            opacity: 1 !important;
            visibility: visible !important;
        }
        .grs-direct-review {
            display: block !important;
            margin-bottom: 20px !important;
            padding: 20px !important;
            border: 1px solid #ddd !important;
            background: white !important;
        }
        .grs-direct-slider {
            display: block !important;
        }
        .grs-direct-slider .grs-direct-slide {
            display: block !important;
            margin-bottom: 15px !important;
        }
        @media screen and (max-width: 768px) {
            .grs-direct-wrapper {
                padding: 0 15px !important;
            }
            .grs-direct-review {
                margin: 10px 0 !important;
            }
        }
        </style>
    </noscript>
    
    <?php
    return ob_get_clean();
}

// Add compatibility hooks for popular themes
add_action('wp_head', 'grs_theme_compatibility_css', 999);
function grs_theme_compatibility_css() {
    global $post;
    
    // Only add on pages with the shortcode
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'google_reviews_slider')) {
        return;
    }
    
    $is_avada = function_exists('fusion_builder_container');
    $is_mobile = wp_is_mobile();
    
    ?>
    <style id="grs-theme-compatibility">
    /* Universal compatibility fixes */
    .grs-direct-wrapper,
    .grs-direct-wrapper * {
        box-sizing: border-box !important;
    }
    
    /* Force visibility across all themes */
    .grs-direct-wrapper {
        opacity: 1 !important;
        visibility: visible !important;
        display: block !important;
        clear: both !important;
        width: 100% !important;
        position: relative !important;
    }
    
    /* Mobile-specific overrides */
    @media screen and (max-width: 768px) {
        .grs-direct-wrapper {
            padding: 0 !important;
            overflow-x: hidden !important;
        }
        
        .grs-direct-container {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .grs-direct-summary {
            margin: 0 auto 20px auto !important;
            width: auto !important;
            box-sizing: border-box !important;
<<<<<<< HEAD
            background: #ffffffff !important;
=======
            background: #FFF9C4 !important;
>>>>>>> 2998ed01a3d308cd73b0a89055f4df70d677bbf8
            text-align: center !important;
        }
        
        .grs-direct-slider-container {
            padding: 0 15px !important;
            margin-bottom: 40px !important;
        }
        
        .grs-direct-slider {
            min-height: 320px !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .grs-direct-slider .slick-slide {
            opacity: 1 !important;
            visibility: visible !important;
            height: auto !important;
            min-height: 280px !important;
        }
        
        .grs-direct-review {
            opacity: 1 !important;
            visibility: visible !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 280px !important;
            background: white !important;
            border: 1px solid #e8e8e8 !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
            margin: 0 !important;
            padding: 20px !important;
        }
        
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
        
        .grs-direct-text {
            color: #333 !important;
            font-size: 14px !important;
            line-height: 1.6 !important;
        }
        
        /* Force slick slider to work properly on mobile */
        .grs-direct-slider .slick-track {
            display: flex !important;
            align-items: stretch !important;
        }
        
        .grs-direct-slider.slick-initialized .slick-slide {
            display: block !important;
            float: left !important;
            height: 100% !important;
        }
        
        /* Navigation arrows on mobile */
        .grs-direct-slider .slick-prev,
        .grs-direct-slider .slick-next {
            display: flex !important;
            width: 30px !important;
            height: 30px !important;
            z-index: 3 !important;
        }
        
        .grs-direct-slider .slick-prev {
            left: 10px !important;
        }
        
        .grs-direct-slider .slick-next {
            right: 10px !important;
        }
        
        /* Dots positioning */
        .grs-direct-slider .slick-dots {
            position: relative !important;
            bottom: auto !important;
            margin-top: 20px !important;
        }
    }
    
    <?php if ($is_avada): ?>
    /* Avada-specific mobile fixes */
    @media screen and (max-width: 768px) {
        .fusion-body .grs-direct-wrapper,
        .fusion-builder-container .grs-direct-wrapper,
        .fusion-text .grs-direct-wrapper {
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
            overflow: visible !important;
            transform: none !important;
        }
        
        .fusion-body .grs-direct-review,
        .fusion-builder-container .grs-direct-review,
        .fusion-text .grs-direct-review {
            display: flex !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
    }
    <?php endif; ?>
    
    /* iOS Safari fixes */
    @supports (-webkit-touch-callout: none) {
        .grs-direct-slider,
        .grs-direct-slider * {
            -webkit-transform: translate3d(0, 0, 0) !important;
            transform: translate3d(0, 0, 0) !important;
        }
        
        @media screen and (max-width: 768px) {
            .grs-direct-review {
                -webkit-backface-visibility: hidden !important;
                backface-visibility: hidden !important;
            }
        }
    }
    
    /* Force text selection on mobile */
    @media screen and (max-width: 768px) {
        .grs-direct-text {
            user-select: text !important;
            -webkit-user-select: text !important;
            -moz-user-select: text !important;
            -ms-user-select: text !important;
        }
    }
    </style>
    <?php
}

// Add body class for theme detection
add_filter('body_class', 'grs_add_body_classes');
function grs_add_body_classes($classes) {
    global $post;
    
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'google_reviews_slider')) {
        $classes[] = 'has-google-reviews-slider';
        
        if (function_exists('fusion_builder_container')) {
            $classes[] = 'grs-avada-theme';
        }
        
        if (wp_is_mobile()) {
            $classes[] = 'grs-mobile-device';
        }
    }
    
    return $classes;
}

// Add inline script for immediate mobile execution
add_action('wp_footer', 'grs_footer_script', 999);
function grs_footer_script() {
    global $post;
    
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'google_reviews_slider')) {
        return;
    }
    
    ?>
    <script type="text/javascript">
    // Final mobile visibility enforcement
    (function() {
        'use strict';
        
        if (window.innerWidth <= 768) {
            function forceMobileVisibility() {
                var sliders = document.querySelectorAll('.grs-direct-slider');
                var reviews = document.querySelectorAll('.grs-direct-review');
                var texts = document.querySelectorAll('.grs-direct-text');
                
                // Force sliders visible
                for (var i = 0; i < sliders.length; i++) {
                    sliders[i].style.setProperty('opacity', '1', 'important');
                    sliders[i].style.setProperty('visibility', 'visible', 'important');
                    sliders[i].style.setProperty('display', 'block', 'important');
                    sliders[i].style.setProperty('min-height', '320px', 'important');
                }
                
                // Force reviews visible
                for (var j = 0; j < reviews.length; j++) {
                    reviews[j].style.setProperty('opacity', '1', 'important');
                    reviews[j].style.setProperty('visibility', 'visible', 'important');
                    reviews[j].style.setProperty('display', 'flex', 'important');
                    reviews[j].style.setProperty('flex-direction', 'column', 'important');
                    reviews[j].style.setProperty('min-height', '280px', 'important');
                }
                
                // Force text visible
                for (var k = 0; k < texts.length; k++) {
                    texts[k].style.setProperty('opacity', '1', 'important');
                    texts[k].style.setProperty('visibility', 'visible', 'important');
                    texts[k].style.setProperty('display', 'block', 'important');
                    texts[k].style.setProperty('color', '#333', 'important');
                }
                
                console.log('GRS: Forced mobile visibility for', reviews.length, 'reviews');
            }
            
            // Run immediately
            forceMobileVisibility();
            
            // Run at multiple intervals to catch lazy-loaded content
            setTimeout(forceMobileVisibility, 500);
            setTimeout(forceMobileVisibility, 1000);
            setTimeout(forceMobileVisibility, 2000);
            
            // Also run on window load
            if (window.addEventListener) {
                window.addEventListener('load', function() {
                    setTimeout(forceMobileVisibility, 100);
                });
            }
            
            // Force refresh slick sliders on mobile
            if (typeof jQuery !== 'undefined' && typeof jQuery.fn.slick !== 'undefined') {
                jQuery(document).ready(function($) {
                    setTimeout(function() {
                        $('.grs-direct-slider.slick-initialized').slick('refresh');
                        forceMobileVisibility();
                    }, 1500);
                });
            }
        }
    })();
    </script>
    <?php
}