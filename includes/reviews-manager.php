<?php
/**
 * Reviews Manager Interface for Google Reviews Slider
 * 
 * @package GoogleReviewsSlider
 * @since 2.0.0
 */

class GRS_Reviews_Manager {
    
    /**
     * Display the reviews management interface
     * 
     * @param string $place_id
     */
    public static function display_interface($place_id) {
        // Initialize database
        require_once(GRS_PLUGIN_PATH . 'includes/database-handler.php');
        
        // Get review statistics
        $stats = GRS_Database::get_review_stats($place_id);
        $extraction_history = GRS_Database::get_extraction_history($place_id, 5);
        
        ?>
        <div class="grs-reviews-manager">
            <h3>Reviews Management</h3>
            
            <!-- Statistics Section -->
            <div class="grs-stats-section">
                <h4>Review Statistics</h4>
                <div class="grs-stats-grid">
                    <div class="grs-stat-box">
                        <span class="stat-number"><?php echo $stats['total']; ?></span>
                        <span class="stat-label">Total Reviews</span>
                    </div>
                    <div class="grs-stat-box">
                        <span class="stat-number"><?php echo $stats['average']; ?></span>
                        <span class="stat-label">Average Rating</span>
                    </div>
                    <?php foreach ($stats['by_rating'] as $rating => $count): ?>
                        <div class="grs-stat-box">
                            <span class="stat-number"><?php echo $count; ?></span>
                            <span class="stat-label"><?php echo $rating; ?> Star Reviews</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Extract Reviews Section -->
            <div class="grs-extract-section">
                <h4>Extract New Reviews</h4>
                <div class="grs-extract-controls">
                    <label for="reviews_limit">Number of reviews to extract:</label>
                    <select id="reviews_limit" name="reviews_limit">
                        <option value="10">10 Reviews</option>
                        <option value="25">25 Reviews</option>
                        <option value="50" selected>50 Reviews</option>
                        <option value="100">100 Reviews</option>
                        <option value="200">200 Reviews</option>
                        <option value="500">500 Reviews</option>
                    </select>
                    
                    <button type="button" id="extract-reviews-btn" class="button button-primary">
                        <span class="dashicons dashicons-download"></span> Extract Reviews
                    </button>
                    
                    <button type="button" id="check-usage-btn" class="button button-secondary">
                        <span class="dashicons dashicons-info"></span> Check API Usage
                    </button>
                    
                    <div id="extraction-status" class="grs-status-message" style="display:none;"></div>
                </div>
                
                <div id="api-usage-info" class="grs-api-usage" style="display:none;">
                    <h5>API Usage Information</h5>
                    <div class="usage-content"></div>
                </div>
            </div>
            
            <!-- Extraction History -->
            <?php if (!empty($extraction_history)): ?>
            <div class="grs-history-section">
                <h4>Recent Extraction History</h4>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Reviews Extracted</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($extraction_history as $entry): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($entry['extraction_date'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($entry['status']); ?>">
                                    <?php echo ucfirst($entry['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $entry['reviews_extracted']; ?></td>
                            <td><?php echo esc_html($entry['error_message'] ?: '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Reviews Table -->
            <div class="grs-reviews-table-section">
                <h4>Extracted Reviews</h4>
                <div class="grs-table-controls">
                    <label for="filter-rating">Filter by rating:</label>
                    <select id="filter-rating" name="filter_rating">
                        <option value="0">All Ratings</option>
                        <option value="5">5 Stars Only</option>
                        <option value="4">4+ Stars</option>
                        <option value="3">3+ Stars</option>
                        <option value="2">2+ Stars</option>
                        <option value="1">1+ Stars</option>
                    </select>
                    
                    <button type="button" id="refresh-reviews-btn" class="button">
                        <span class="dashicons dashicons-update"></span> Refresh
                    </button>
                </div>
                
                <div id="reviews-table-container">
                    <?php self::display_reviews_table($place_id); ?>
                </div>
            </div>
        </div>
        
        <style>
        .grs-reviews-manager {
            margin-top: 20px;
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .grs-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .grs-stat-box {
            background: white;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .grs-stat-box .stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .grs-stat-box .stat-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .grs-extract-section,
        .grs-history-section,
        .grs-reviews-table-section {
            margin-top: 30px;
            background: white;
            padding: 20px;
            border-radius: 4px;
        }
        
        .grs-extract-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 15px;
        }
        
        .grs-extract-controls select {
            min-width: 150px;
        }
        
        .grs-status-message {
            margin-top: 15px;
            padding: 10px 15px;
            border-radius: 4px;
        }
        
        .grs-status-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .grs-status-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .grs-status-message.loading {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-badge.status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .grs-table-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 15px 0;
        }
        
        .grs-api-usage {
            margin-top: 20px;
            padding: 15px;
            background: #e8f4f8;
            border: 1px solid #bee5eb;
            border-radius: 4px;
        }
        
        .grs-api-usage h5 {
            margin: 0 0 10px 0;
            color: #0c5460;
        }
        
        .review-text-cell {
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .review-text-full {
            white-space: normal;
        }
        
        .review-expand-btn {
            color: #0073aa;
            cursor: pointer;
            text-decoration: underline;
            font-size: 12px;
            margin-left: 5px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var placeId = '<?php echo esc_js($place_id); ?>';
            
            // Extract reviews
            $('#extract-reviews-btn').on('click', function() {
                console.log('Extract button clicked');
                var button = $(this);
                var status = $('#extraction-status');
                var reviewsLimit = $('#reviews_limit').val();
                
                console.log('Place ID:', placeId);
                console.log('Reviews Limit:', reviewsLimit);
                console.log('Nonce:', '<?php echo wp_create_nonce("grs_nonce"); ?>');
                
                button.prop('disabled', true);
                status.removeClass('success error').addClass('loading').html(
                    '<span class="dashicons dashicons-update spinning"></span> Extracting reviews...'
                ).show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'grs_extract_reviews',
                        place_id: placeId,
                        reviews_limit: reviewsLimit,
                        nonce: '<?php echo wp_create_nonce("grs_nonce"); ?>'
                    },
                    success: function(response) {
                        console.log('Success response:', response);
                        if (response.success) {
                            var data = response.data;
                            status.removeClass('loading error').addClass('success').html(
                                '<span class="dashicons dashicons-yes"></span> Successfully extracted ' + 
                                data.reviews_saved + ' reviews out of ' + data.reviews_found + ' found.'
                            );
                            
                            // Reload the page after 2 seconds to show new data
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            console.error('Error in response:', response);
                            status.removeClass('loading success').addClass('error').html(
                                '<span class="dashicons dashicons-warning"></span> Error: ' + (response.data || 'Unknown error')
                            );
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', xhr, status, error);
                        console.error('Response Text:', xhr.responseText);
                        status.removeClass('loading success').addClass('error').html(
                            '<span class="dashicons dashicons-warning"></span> An error occurred while extracting reviews.'
                        );
                    },
                    complete: function() {
                        button.prop('disabled', false);
                    }
                });
            });
            
            // Check API usage
            $('#check-usage-btn').on('click', function() {
                var button = $(this);
                var usageDiv = $('#api-usage-info');
                var content = usageDiv.find('.usage-content');
                
                button.prop('disabled', true);
                content.html('<span class="dashicons dashicons-update spinning"></span> Loading...');
                usageDiv.show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'grs_check_api_usage',
                        nonce: '<?php echo wp_create_nonce("grs_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var usage = response.data;
                            var html = '<table class="widefat">';
                            for (var key in usage) {
                                if (usage.hasOwnProperty(key)) {
                                    html += '<tr><td><strong>' + key + ':</strong></td><td>' + usage[key] + '</td></tr>';
                                }
                            }
                            html += '</table>';
                            content.html(html);
                        } else {
                            content.html('<span class="dashicons dashicons-warning"></span> Error: ' + response.data);
                        }
                    },
                    error: function() {
                        content.html('<span class="dashicons dashicons-warning"></span> Failed to retrieve usage information.');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                    }
                });
            });
            
            // Filter reviews
            $('#filter-rating, #refresh-reviews-btn').on('change click', function(e) {
                if (e.type === 'click') e.preventDefault();
                
                var rating = $('#filter-rating').val();
                var container = $('#reviews-table-container');
                
                container.html('<p>Loading reviews...</p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'grs_get_reviews_table',
                        place_id: placeId,
                        min_rating: rating,
                        nonce: '<?php echo wp_create_nonce("grs_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            container.html(response.data);
                        } else {
                            container.html('<p>Error loading reviews.</p>');
                        }
                    }
                });
            });
            
            // Expand/collapse review text
            $(document).on('click', '.review-expand-btn', function(e) {
                e.preventDefault();
                var cell = $(this).closest('.review-text-cell');
                cell.toggleClass('review-text-full');
                $(this).text(cell.hasClass('review-text-full') ? 'Show less' : 'Read more');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Display reviews table
     * 
     * @param string $place_id
     * @param int $min_rating
     */
    public static function display_reviews_table($place_id, $min_rating = 0) {
        $reviews = GRS_Database::get_reviews($place_id, $min_rating, 100);
        
        if (empty($reviews)) {
            echo '<p>No reviews found. Click "Extract Reviews" to fetch reviews from Google.</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;">Author</th>
                    <th style="width: 80px;">Rating</th>
                    <th>Review Text</th>
                    <th style="width: 120px;">Date</th>
                    <th style="width: 80px;">Source</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reviews as $review): ?>
                <tr>
                    <td>
                        <?php if ($review['profile_photo_url']): ?>
                            <img src="<?php echo esc_url($review['profile_photo_url']); ?>" 
                                 alt="<?php echo esc_attr($review['author_name']); ?>"
                                 style="width: 30px; height: 30px; border-radius: 50%; vertical-align: middle; margin-right: 10px;">
                        <?php endif; ?>
                        <?php echo esc_html($review['author_name']); ?>
                        <?php if ($review['is_local_guide']): ?>
                            <span title="Local Guide" style="color: #ea4335;">⭐</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php for ($i = 0; $i < $review['rating']; $i++): ?>
                            <span class="dashicons dashicons-star-filled" style="color: #FFC107;"></span>
                        <?php endfor; ?>
                    </td>
                    <td class="review-text-cell">
                        <?php 
                        $text = $review['text'];
                        if (strlen($text) > 200) {
                            echo esc_html(substr($text, 0, 200)) . '...';
                            echo '<a href="#" class="review-expand-btn">Read more</a>';
                            echo '<span style="display:none;">' . esc_html($text) . '</span>';
                        } else {
                            echo esc_html($text);
                        }
                        ?>
                    </td>
                    <td><?php echo date('Y-m-d', $review['time']); ?></td>
                    <td><?php echo ucfirst($review['source']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}

// AJAX handler for getting reviews table
add_action('wp_ajax_grs_get_reviews_table', 'grs_handle_get_reviews_table');
function grs_handle_get_reviews_table() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_ajax_referer('grs_nonce', 'nonce');
    
    $place_id = sanitize_text_field($_POST['place_id']);
    $min_rating = isset($_POST['min_rating']) ? intval($_POST['min_rating']) : 0;
    
    ob_start();
    GRS_Reviews_Manager::display_reviews_table($place_id, $min_rating);
    $html = ob_get_clean();
    
    wp_send_json_success($html);
}