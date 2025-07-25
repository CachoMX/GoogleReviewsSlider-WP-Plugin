<?php
/**
 * Google Reviews Slider - Fixed Mobile Display
 * Updated shortcode implementation with mobile fixes
 */

add_action('init', 'grs_direct_init');

function grs_direct_init() {
    add_shortcode('google_reviews_slider', 'grs_direct_display');
    add_action('wp_enqueue_scripts', 'grs_direct_enqueue_assets');
}

function grs_direct_enqueue_assets() {
    if (!has_shortcode(get_post()->post_content ?? '', 'google_reviews_slider') && !is_admin()) {
        return;
    }
    
    wp_enqueue_style('dashicons');
    wp_enqueue_script('jquery');
    
    // Slick carousel
    wp_enqueue_style('grs-slick', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css', array(), '1.8.1');
    wp_enqueue_style('grs-slick-theme', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css', array(), '1.8.1');
    wp_enqueue_script('grs-slick-js', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js', array('jquery'), '1.8.1', true);
    
    // Custom styles and scripts
    $version = get_option('grs_version', '1.2') . '.' . time();
    wp_enqueue_style('grs-direct-styles', plugins_url('css/grs-direct.css', dirname(__FILE__)), array('grs-slick', 'grs-slick-theme'), $version);
    wp_enqueue_script('grs-direct-script', plugins_url('js/script.js', dirname(__FILE__)), array('jquery', 'grs-slick-js'), $version, true);
    
    wp_localize_script('grs-direct-script', 'grsData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('grs_nonce'),
        'pluginUrl' => plugins_url('', dirname(__FILE__)),
        'isDebug' => defined('WP_DEBUG') && WP_DEBUG
    ));
}

function grs_direct_display($atts) {
    $atts = shortcode_atts(array(
        'show_summary' => 'true',
        'min_rating' => null,
        'autoplay' => 'true',
        'autoplay_speed' => '4000',
        'slides_desktop' => '3',
        'slides_tablet' => '2', 
        'slides_mobile' => '1'
    ), $atts, 'google_reviews_slider');
    
    $options = get_option('grs_settings');
    $cached_reviews = get_transient('grs_reviews');
    $total_review_count = get_transient('grs_total_review_count');
    
    if ($cached_reviews === false || $total_review_count === false) {
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
            return '';
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
    
    if ($atts['min_rating'] !== null) {
        $min_rating = intval($atts['min_rating']);
        $reviews = array_filter($reviews, function($review) use ($min_rating) {
            return $review['rating'] >= $min_rating;
        });
    }
    
    $total_rating = 0;
    foreach ($reviews as $review) {
        $total_rating += $review['rating'];
    }
    $average_rating = count($reviews) > 0 ? round($total_rating / count($reviews), 1) : 5;
    
    $unique_id = 'grs-slider-' . uniqid();
    
    ob_start();
    ?>
    <div class="grs-direct-wrapper" id="<?php echo esc_attr($unique_id); ?>-wrapper">
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
    
    <!-- Mobile-specific initialization script -->
    <script>
    (function() {
        // Force initialization on mobile
        if (window.innerWidth <= 768) {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    if (typeof jQuery !== 'undefined' && jQuery('.grs-direct-slider').length) {
                        jQuery('.grs-direct-slider').trigger('refresh');
                    }
                }, 100);
            });
        }
    })();
    </script>
    
    <?php
    return ob_get_clean();
}