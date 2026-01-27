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

    // Swiper JS from CDN (safe to load alongside Elementor — just sets window.Swiper)
    wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), '11.0.0', true);
    // NO swiper CSS from CDN — it overrides other carousels. All styles are in grs-direct.css scoped to .grs-swiper

    // Custom styles and scripts
    $version = get_option('grs_version', '2.0') . '.' . time();
    wp_enqueue_style('grs-direct-styles', plugins_url('css/grs-direct.css', dirname(__FILE__)), array(), $version);
    wp_enqueue_script('grs-swiper-init', plugins_url('js/swiper-init.js', dirname(__FILE__)), array('swiper-js'), $version, true);
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
        'slides_mobile' => '1',
        'arrows' => 'true'
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

    // Use Google total > SerpAPI total > database count
    $serpapi_total = isset($options['grs_total_reviews']) ? intval($options['grs_total_reviews']) : 0;
    if ($google_total !== false) {
        $total_review_count = $google_total;
    } elseif ($serpapi_total > 0) {
        $total_review_count = $serpapi_total;
    } else {
        $total_review_count = $stats['total'];
    }
    
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
    }
    </style>
    
    <div class="grs-direct-wrapper<?php echo esc_attr($body_classes); ?>" 
         id="<?php echo esc_attr($unique_id); ?>-wrapper"
         data-theme="<?php echo $is_avada ? 'avada' : 'default'; ?>"
         data-device="<?php echo $is_mobile ? 'mobile' : 'desktop'; ?>">
        
        <div class="grs-direct-container">
            <?php if ($atts['show_summary'] === 'true') :
                $business_name = isset($options['grs_business_name']) ? $options['grs_business_name'] : '';
            ?>
            <div class="grs-direct-summary">
                <div class="grs-direct-rating-large">EXCELLENT</div>
                <?php if (!empty($business_name)) : ?>
                <div class="grs-direct-business-name"><?php echo esc_html($business_name); ?></div>
                <?php endif; ?>
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
                <!-- Navigation Prev -->
                <button class="grs-nav-button grs-nav-prev" aria-label="Previous review">
                    <svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z"/></svg>
                </button>

                <!-- Swiper Container -->
                <div id="<?php echo esc_attr($unique_id); ?>"
                     class="swiper grs-swiper"
                     data-autoplay="<?php echo esc_attr($atts['autoplay']); ?>"
                     data-autoplay-speed="<?php echo esc_attr($atts['autoplay_speed']); ?>"
                     data-slides-desktop="<?php echo esc_attr($atts['slides_desktop']); ?>"
                     data-slides-tablet="<?php echo esc_attr($atts['slides_tablet']); ?>"
                     data-slides-mobile="<?php echo esc_attr($atts['slides_mobile']); ?>"
                     role="region"
                     aria-label="Customer Reviews Slider">

                    <div class="swiper-wrapper">
                    <?php foreach ($reviews as $index => $review) :
                        $review_text = !empty($review['text']) ? $review['text'] : '';

                        // Skip reviews without text
                        if (empty(trim($review_text))) {
                            continue;
                        }

                        $needs_truncation = strlen($review_text) > 120; // Truncate reviews over 120 chars
                        $author_name = esc_html($review['author_name']);
                        $time_description = !empty($review['relative_time_description']) ?
                            esc_html($review['relative_time_description']) :
                            date('F Y', $review['time']);
                        $profile_photo = !empty($review['profile_photo_url']) ?
                            esc_url($review['profile_photo_url']) :
                            plugins_url('assets/default-avatar.png', dirname(__FILE__));
                        $rating = intval($review['rating']);
                    ?>
                        <div class="swiper-slide grs-direct-slide">
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
                    </div><!-- /.swiper-wrapper -->

                    <!-- Swiper Pagination -->
                    <div class="swiper-pagination"></div>
                </div><!-- /.grs-swiper -->

                <!-- Navigation Next -->
                <button class="grs-nav-button grs-nav-next" aria-label="Next review">
                    <svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>
                </button>
            </div>
        </div>
    </div>
    
    
    <?php
    return ob_get_clean();
}

