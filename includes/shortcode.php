<?php
/**
 * [google_reviews_slider] shortcode: renders stored reviews as a Swiper
 * slider. Reads exclusively from the plugin's reviews table; refreshing
 * data is the sync engine's job (GRS_Sync), never the render path's.
 */

add_action('init', 'grs_direct_init');

function grs_direct_init() {
    add_shortcode('google_reviews_slider', 'grs_direct_display');
    add_action('wp_enqueue_scripts', 'grs_direct_enqueue_assets');
}

function grs_direct_enqueue_assets($force = false) {
    global $post;
    $has_shortcode = false;

    if (is_a($post, 'WP_Post')) {
        $has_shortcode = has_shortcode($post->post_content, 'google_reviews_slider');
    }

    // Avada's live builder renders the shortcode in a preview frame where
    // $post is not the edited page, so detection by content fails there.
    $is_builder = isset($_GET['builder']) ||
                  (function_exists('fusion_is_preview_frame') && fusion_is_preview_frame());

    if (!$force && !$has_shortcode && !$is_builder) {
        return;
    }

    wp_enqueue_style('dashicons');

    // Swiper JS only; its CDN CSS would override other carousels (e.g.
    // Elementor). All slider styles live in grs-direct.css scoped to .grs-swiper.
    // Pinned to the exact declared version; a floating @11 would drift
    // to untested releases.
    wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11.0.0/swiper-bundle.min.js', array(), '11.0.0', true);

    $version = defined('GRS_VERSION') ? GRS_VERSION : '2.0';
    wp_enqueue_style('grs-direct-styles', plugins_url('css/grs-direct.css', dirname(__FILE__)), array(), $version);
    wp_enqueue_script('grs-swiper-init', plugins_url('js/swiper-init.js', dirname(__FILE__)), array('swiper-js'), $version, true);
}

function grs_direct_display($atts) {
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

    // Normalized to 'true'/'false' strings so 'TRUE', '1' and 'yes' all
    // satisfy the === 'true' markup comparisons below and the JS's
    // data-autoplay !== 'false' check.
    foreach (array('show_summary', 'autoplay', 'arrows') as $flag) {
        $atts[$flag] = filter_var($atts[$flag], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }

    // Widgets, templates, and builder modules bypass the head-time
    // post_content scan; late enqueue prints styles in the footer, an
    // acceptable fallback so the markup is never dead.
    if (!wp_script_is('grs-swiper-init', 'enqueued')) {
        grs_direct_enqueue_assets(true);
    }

    $options = get_option('grs_settings');
    $place_id = isset($options['grs_place_id']) ? $options['grs_place_id'] : '';

    if (empty($place_id)) {
        if (current_user_can('manage_options')) {
            return '<div class="grs-error" style="padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404; margin: 20px 0;">
                <strong>Google Reviews Slider:</strong> Place ID not configured.
                <br><small>This message is only visible to administrators. <a href="' . esc_url(admin_url('admin.php?page=google_reviews_slider')) . '">Configure Settings</a></small>
            </div>';
        }
        return '';
    }

    require_once(plugin_dir_path(dirname(__FILE__)) . 'includes/database-handler.php');

    $min_rating = $atts['min_rating'] !== null ? intval($atts['min_rating']) :
                  (isset($options['grs_min_rating']) ? intval($options['grs_min_rating']) : 1);

    $reviews = GRS_Database::get_reviews($place_id, $min_rating, GRS_Database::MAX_REVIEWS_PER_PLACE);

    if (empty($reviews)) {
        if (current_user_can('manage_options')) {
            return '<div class="grs-error" style="padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404; margin: 20px 0;">
                <strong>Google Reviews Slider:</strong> No reviews found.
                <br>Please <a href="' . esc_url(admin_url('admin.php?page=google_reviews_slider')) . '">run a sync</a> from the admin panel.
                <br><small>This message is only visible to administrators.</small>
            </div>';
        }
        return '';
    }

    $stats = GRS_Database::get_review_stats($place_id);
    $business_info = get_option('grs_business_info', array());

    $total_review_count = !empty($business_info['total_reviews'])
        ? intval($business_info['total_reviews'])
        : $stats['total'];

    $business_name = isset($business_info['name']) ? $business_info['name'] : '';
    $default_avatar = plugins_url('assets/default-avatar.svg', dirname(__FILE__));
    $unique_id = 'grs-slider-' . uniqid();

    $is_mobile = wp_is_mobile();
    $is_avada = function_exists('fusion_builder_container');
    $body_classes = '';

    if ($is_avada) {
        $body_classes .= ' grs-avada-theme';
    }
    if ($is_mobile) {
        $body_classes .= ' grs-mobile-device';
    }

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
            <?php if ($atts['show_summary'] === 'true') : ?>
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
                <?php if ($atts['arrows'] === 'true') : ?>
                <button class="grs-nav-button grs-nav-prev" aria-label="Previous review">
                    <svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z"/></svg>
                </button>
                <?php endif; ?>

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

                        if (trim($review_text) === '') {
                            continue;
                        }

                        $needs_truncation = mb_strlen($review_text) > 120;
                        $author_name = esc_html($review['author_name']);
                        // Derived from the stored timestamp at render time; the
                        // relative string SerpAPI ships goes stale the moment
                        // it is stored (BUG 1 / CR-3).
                        $time_description = sprintf(
                            /* translators: %s: human-readable time span, e.g. "2 months" */
                            esc_html__('%s ago', 'google-reviews-slider'),
                            human_time_diff(intval($review['time']))
                        );
                        $profile_photo = !empty($review['profile_photo_url']) ?
                            esc_url($review['profile_photo_url']) :
                            esc_url($default_avatar);
                        $rating = intval($review['rating']);
                    ?>
                        <div class="swiper-slide grs-direct-slide">
                            <div class="grs-direct-review"
                                 data-review-index="<?php echo esc_attr($index); ?>"
                                 role="article"
                                 aria-label="Review by <?php echo $author_name; ?>">

                                <div class="grs-direct-header">
                                    <div class="grs-direct-profile-img">
                                        <img src="<?php echo $profile_photo; ?>"
                                             alt="<?php echo esc_attr($review['author_name']); ?>"
                                             loading="lazy"
                                             onerror="this.onerror=null;this.src='<?php echo esc_url($default_avatar); ?>';">
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
                                    <div class="grs-direct-text <?php echo $needs_truncation ? 'truncated' : ''; ?>">
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
                <?php if ($atts['arrows'] === 'true') : ?>
                <button class="grs-nav-button grs-nav-next" aria-label="Next review">
                    <svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <?php
    return ob_get_clean();
}
