<?php
/**
 * Google Reviews Slider - UPDATED Shortcode Implementation
 * Fixed to work with improved CSS and JavaScript
 */

add_action('init', 'grs_direct_init');

function grs_direct_init() {
    add_shortcode('google_reviews_slider', 'grs_direct_display');
    
    // Register and enqueue assets
    add_action('wp_enqueue_scripts', 'grs_direct_enqueue_assets');
}

function grs_direct_enqueue_assets() {
    // Only enqueue on pages that use the shortcode
    if (!has_shortcode(get_post()->post_content ?? '', 'google_reviews_slider') && !is_admin()) {
        return;
    }
    
    // Enqueue required styles and scripts
    wp_enqueue_style('dashicons');
    wp_enqueue_script('jquery');
    
    // Use CDN for Slick resources with version control
    wp_enqueue_style('grs-slick', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css', array(), '1.8.1');
    wp_enqueue_style('grs-slick-theme', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css', array(), '1.8.1');
    wp_enqueue_script('grs-slick-js', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js', array('jquery'), '1.8.1', true);
    
    // Enqueue our custom styles and scripts with cache busting
    $version = get_option('grs_version', '1.1') . '.' . time(); // Cache busting during development
    wp_enqueue_style('grs-direct-styles', plugins_url('css/grs-direct.css', dirname(__FILE__)), array('grs-slick', 'grs-slick-theme'), $version);
    wp_enqueue_script('grs-direct-script', plugins_url('js/script.js', dirname(__FILE__)), array('jquery', 'grs-slick-js'), $version, true);
    
    // Localize script with useful data
    wp_localize_script('grs-direct-script', 'grsData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('grs_nonce'),
        'pluginUrl' => plugins_url('', dirname(__FILE__)),
        'isDebug' => defined('WP_DEBUG') && WP_DEBUG
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
    
    // Get reviews data from API handler
    $options = get_option('grs_settings');
    $cached_reviews = get_transient('grs_reviews');
    $total_review_count = get_transient('grs_total_review_count');
    
    // Check if we need to fetch reviews
    if ($cached_reviews === false || $total_review_count === false) {
        // Include API handler if not already included
        if (!function_exists('grs_get_reviews')) {
            include_once(plugin_dir_path(dirname(__FILE__)) . 'includes/api-handler.php');
        }
        
        $result = grs_get_reviews();
        
        if (isset($result['error'])) {
            if (current_user_can('manage_options')) {
                return '<div class="grs-error" style="padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404;">
                    <strong>Google Reviews Slider Error:</strong> ' . esc_html($result['error']) . '
                    <br><small>This message is only visible to administrators.</small>
                </div>';
            }
            return ''; // Don't show errors to regular visitors
        }
        
        $reviews = $result['reviews'];
        $total_review_count = $result['total_count'];
    } else {
        $reviews = $cached_reviews;
    }
    
    if (empty($reviews)) {
        if (current_user_can('manage_options')) {
            return '<div class="grs-error" style="padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404;">
                <strong>Google Reviews Slider:</strong> No reviews available. Please check your API configuration.
                <br><small>This message is only visible to administrators.</small>
            </div>';
        }
        return '';
    }
    
    // Apply minimum rating filter from shortcode attribute
    if ($atts['min_rating'] !== null) {
        $min_rating = intval($atts['min_rating']);
        $reviews = array_filter($reviews, function($review) use ($min_rating) {
            return $review['rating'] >= $min_rating;
        });
    }
    
    // Calculate average rating for display
    $total_rating = 0;
    foreach ($reviews as $review) {
        $total_rating += $review['rating'];
    }
    $average_rating = count($reviews) > 0 ? round($total_rating / count($reviews), 1) : 5;
    
    // Generate a unique ID for this slider instance
    $unique_id = 'grs-slider-' . uniqid();
    
    // Start output buffering
    ob_start();
    ?>
    <div class="grs-direct-wrapper" id="<?php echo esc_attr($unique_id); ?>-wrapper">
        <div class="grs-direct-container">
            <?php if ($atts['show_summary'] === 'true') : ?>
            <!-- Summary section -->
            <div class="grs-direct-summary">
                <div class="grs-direct-rating-large">EXCELLENT</div>
                <div class="grs-direct-stars">
                    <?php for ($i = 0; $i < 5; $i++) : ?>
                        <span class="dashicons dashicons-star-filled"></span>
                    <?php endfor; ?>
                </div>
                <div class="grs-direct-rating-text">
                    Based on <strong><?php echo esc_html($total_review_count); ?> reviews</strong>
                    <?php if (count($reviews) < $total_review_count) : ?>
                        <br><small>Showing <?php echo count($reviews); ?> recent reviews</small>
                    <?php endif; ?>
                </div>
                <div class="grs-direct-logo">
                    <img src="<?php echo plugins_url('assets/google-logo.svg', dirname(__FILE__)); ?>" 
                         alt="Google" width="110" height="35">
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Reviews slider -->
            <div class="grs-direct-slider-container">
                <div id="<?php echo esc_attr($unique_id); ?>" 
                     class="grs-direct-slider" 
                     data-slides="<?php echo count($reviews); ?>"
                     data-autoplay="<?php echo esc_attr($atts['autoplay']); ?>"
                     data-autoplay-speed="<?php echo esc_attr($atts['autoplay_speed']); ?>"
                     data-slides-desktop="<?php echo esc_attr($atts['slides_desktop']); ?>"
                     data-slides-tablet="<?php echo esc_attr($atts['slides_tablet']); ?>"
                     data-slides-mobile="<?php echo esc_attr($atts['slides_mobile']); ?>">
                    
                    <?php foreach ($reviews as $index => $review) : 
                        $review_text = !empty($review['text']) ? $review['text'] : '(No review text provided)';
                        $needs_truncation = strlen($review_text) > 150;
                        $author_name = esc_html($review['author_name']);
                        $time_description = esc_html($review['relative_time_description']);
                        $profile_photo = !empty($review['profile_photo_url']) ? 
                            esc_url($review['profile_photo_url']) : 
                            plugins_url('assets/default-avatar.png', dirname(__FILE__));
                    ?>
                        <div class="grs-direct-slide">
                            <div class="grs-direct-review" data-review-index="<?php echo $index; ?>">
                                <div class="grs-direct-header">
                                    <div class="grs-direct-profile-img">
                                        <img src="<?php echo $profile_photo; ?>" 
                                             alt="<?php echo $author_name; ?>"
                                             onerror="this.src='<?php echo plugins_url('assets/default-avatar.png', dirname(__FILE__)); ?>';">
                                    </div>
                                    <div class="grs-direct-profile-details">
                                        <div class="grs-direct-name"><?php echo $author_name; ?></div>
                                        <div class="grs-direct-date"><?php echo $time_description; ?></div>
                                    </div>
                                </div>
                                
                                <div class="grs-direct-stars small">
                                    <?php for ($i = 0; $i < $review['rating']; $i++) : ?>
                                        <span class="dashicons dashicons-star-filled"></span>
                                    <?php endfor; ?>
                                </div>
                                
                                <div class="grs-direct-content">
                                    <div class="grs-direct-text <?php echo $needs_truncation ? 'truncated' : ''; ?>">
                                        <?php echo esc_html($review_text); ?>
                                    </div>
                                    
                                    <?php if ($needs_truncation) : ?>
                                        <a href="#" class="grs-direct-read-more" role="button" aria-expanded="false">Read more</a>
                                        <a href="#" class="grs-direct-hide" style="display:none;" role="button" aria-expanded="true">Show less</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    // Add debug info for administrators
    if (current_user_can('manage_options') && (defined('WP_DEBUG') && WP_DEBUG)) {
        ?>
        <script>
        console.log('Google Reviews Slider Debug Info:', {
            sliderId: '<?php echo $unique_id; ?>',
            reviewCount: <?php echo count($reviews); ?>,
            totalReviews: <?php echo $total_review_count; ?>,
            averageRating: <?php echo $average_rating; ?>,
            attributes: <?php echo json_encode($atts); ?>
        });
        </script>
        <?php
    }
    
    return ob_get_clean();
}