<?php
/**
 * Google Reviews Slider - Fixed Implementation
 * This file should replace your current shortcode.php file
 */

add_action('init', 'grs_direct_init');

function grs_direct_init() {
    add_shortcode('google_reviews_slider', 'grs_direct_display');
    
    // Register and enqueue assets only once
    add_action('wp_enqueue_scripts', 'grs_direct_enqueue_assets');
}

function grs_direct_enqueue_assets() {
    // Enqueue required styles and scripts from local files
    wp_enqueue_style('dashicons');
    wp_enqueue_script('jquery');
    
    // Use local Slick resources instead of CDN
    wp_enqueue_style('grs-direct-slick', plugins_url('assets/slick/slick.css', dirname(__FILE__)));
    wp_enqueue_style('grs-direct-slick-theme', plugins_url('assets/slick/slick-theme.css', dirname(__FILE__)));
    wp_enqueue_script('grs-direct-slick-js', plugins_url('assets/slick/slick.min.js', dirname(__FILE__)), array('jquery'), null, true);
    
    // Enqueue our custom styles and scripts
    wp_enqueue_style('grs-direct-styles', plugins_url('css/grs-direct.css', dirname(__FILE__)));
    wp_enqueue_script('grs-direct-script', plugins_url('js/script.js', dirname(__FILE__)), array('jquery', 'grs-direct-slick-js'), null, true);
}

function grs_direct_display($atts) {
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
            return '<p>Error: ' . esc_html($result['error']) . '</p>';
        }
        
        $reviews = $result['reviews'];
        $total_review_count = $result['total_count'];
    } else {
        $reviews = $cached_reviews;
    }
    
    if (empty($reviews)) {
        return '<p>No reviews available.</p>';
    }
    
    // Generate a unique ID for this slider instance
    $unique_id = 'grs-direct-' . mt_rand(1000, 9999);
    
    // Start output buffering
    ob_start();
    ?>
    <div class="grs-direct-wrapper" id="<?php echo esc_attr($unique_id); ?>-wrapper">
        <div class="grs-direct-container">
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
                </div>
                <div class="grs-direct-logo">
                    <img src="<?php echo plugins_url('assets/google-logo.svg', dirname(__FILE__)); ?>" 
                         alt="Google" width="110" height="35">
                </div>
            </div>
            
            <!-- Reviews slider -->
            <div class="grs-direct-slider-container">
                <div id="<?php echo esc_attr($unique_id); ?>" class="grs-direct-slider" data-slides="<?php echo count($reviews); ?>" style="min-height: 200px;">
                    <?php foreach ($reviews as $review) : ?>
                        <div class="grs-direct-review">
                            <div class="grs-direct-header">
                                <div class="grs-direct-profile-img">
                                    <img src="<?php echo esc_url($review['profile_photo_url']); ?>" 
                                         alt="<?php echo esc_attr($review['author_name']); ?>">
                                </div>
                                <div class="grs-direct-profile-details">
                                    <div class="grs-direct-name">
                                        <?php echo esc_html($review['author_name']); ?>
                                    </div>
                                    <div class="grs-direct-date">
                                        <?php echo esc_html($review['relative_time_description']); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grs-direct-stars small">
                                <?php for ($i = 0; $i < $review['rating']; $i++) : ?>
                                    <span class="dashicons dashicons-star-filled"></span>
                                <?php endfor; ?>
                            </div>
                            
                            <div class="grs-direct-content">
                                <?php 
                                $text = !empty($review['text']) ? $review['text'] : '(No review text provided)';
                                $needs_truncation = strlen($text) > 120;
                                ?>
                                <div class="grs-direct-text <?php echo $needs_truncation ? 'truncated' : ''; ?>">
                                    <?php echo esc_html($text); ?>
                                </div>
                                
                                <?php if ($needs_truncation) : ?>
                                    <a href="#" class="grs-direct-read-more">Read more</a>
                                    <a href="#" class="grs-direct-hide" style="display:none;">Show less</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    
    // Add debug info if user is admin
    if (current_user_can('manage_options')) {
        ?>
        <script>
        console.log('Google Reviews Slider Debug: Rendered slider with ID <?php echo $unique_id; ?>');
        </script>
        <?php
    }
    
    return ob_get_clean();
}