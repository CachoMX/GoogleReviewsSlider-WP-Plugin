<?php
/**
 * Outscraper API Integration for Google Reviews Slider
 * 
 * @package GoogleReviewsSlider
 * @since 2.0.0
 */

class GRS_Outscraper_API {
    /**
     * API Base URL
     */
    const API_BASE_URL = 'https://api.app.outscraper.com';
    
    /**
     * API Token
     */
    private $api_token;
    
    /**
     * Constructor
     */
    public function __construct($api_token = null) {
        if (!$api_token) {
            $options = get_option('grs_settings');
            $this->api_token = isset($options['grs_outscraper_token']) ? $options['grs_outscraper_token'] : '';
        } else {
            $this->api_token = $api_token;
        }
    }
    
    /**
     * Extract reviews from Google Maps
     * 
     * @param string $place_id Google Place ID
     * @param int $reviews_limit Number of reviews to extract
     * @param array $params Additional parameters
     * @return array|WP_Error
     */
    public function extract_reviews($place_id, $reviews_limit = 100, $params = array()) {
        // Default parameters
        $default_params = array(
            'query' => $place_id,
            'reviewsLimit' => $reviews_limit,
            'sort' => 'newest',
            'ignoreEmpty' => 'false',
            'async' => 'false', // We'll use synchronous for now
            'language' => 'en',
            'fields' => 'query,name,reviews_data,reviews_per_rating,reviews_link,website,verified,phone,address,postal_code,opening_hours,current_opening_status'
        );

        $params = wp_parse_args($params, $default_params);

        // Build the API URL
        $url = self::API_BASE_URL . '/maps/reviews-v3?' . http_build_query($params);

        // Debug logging
        error_log('GRS: Extracting reviews from Outscraper');
        error_log('GRS: Place ID: ' . $place_id);
        error_log('GRS: Reviews Limit: ' . $reviews_limit);
        error_log('GRS: API URL: ' . $url);
        error_log('GRS: API Token (first 10 chars): ' . substr($this->api_token, 0, 10) . '...');

        // Make the API request
        $response = wp_remote_get($url, array(
            'timeout' => 60,
            'headers' => array(
                'X-API-KEY' => $this->api_token,
                'Accept' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            error_log('GRS: WordPress HTTP error: ' . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('GRS: Response code: ' . $response_code);
        error_log('GRS: Response body (first 500 chars): ' . substr($response_body, 0, 500));

        if ($response_code !== 200) {
            error_log('GRS: API error - code ' . $response_code . ', body: ' . $response_body);
            return new WP_Error(
                'api_error',
                sprintf('API returned error code: %d. Response: %s', $response_code, $response_body),
                array('body' => $response_body)
            );
        }

        $data = json_decode($response_body, true);

        if (!$data) {
            error_log('GRS: JSON decode failed. Response: ' . $response_body);
            return new WP_Error('parse_error', 'Failed to parse API response: ' . json_last_error_msg());
        }

        if (!isset($data['status'])) {
            error_log('GRS: No status field in response. Keys: ' . json_encode(array_keys($data)));
            return new WP_Error('parse_error', 'No status field in API response. Keys: ' . implode(', ', array_keys($data)));
        }

        if ($data['status'] !== 'Success') {
            error_log('GRS: API status not Success: ' . $data['status']);
            error_log('GRS: Full error response: ' . json_encode($data));
            return new WP_Error('api_status_error', 'API request failed with status: ' . $data['status'], $data);
        }

        error_log('GRS: Successfully received API response');

        return $data;
    }
    
    /**
     * Extract reviews asynchronously
     * 
     * @param string $place_id Google Place ID
     * @param int $reviews_limit Number of reviews to extract
     * @param array $params Additional parameters
     * @return array|WP_Error
     */
    public function extract_reviews_async($place_id, $reviews_limit = 100, $params = array()) {
        $params['async'] = 'true';
        
        // Make the initial request
        $response = $this->extract_reviews($place_id, $reviews_limit, $params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // For async requests, we get a request ID
        if (isset($response['id'])) {
            return array(
                'request_id' => $response['id'],
                'status' => 'pending'
            );
        }
        
        return $response;
    }
    
    /**
     * Check async request status
     * 
     * @param string $request_id
     * @return array|WP_Error
     */
    public function check_request_status($request_id) {
        $url = self::API_BASE_URL . '/request/' . $request_id;
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'X-API-KEY' => $this->api_token,
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error(
                'api_error',
                sprintf('API returned error code: %d', $response_code),
                array('body' => $response_body)
            );
        }
        
        return json_decode($response_body, true);
    }
    
    /**
     * Process and save reviews from API response
     * 
     * @param array $api_response
     * @param string $place_id
     * @return array Results summary
     */
    public function process_reviews_response($api_response, $place_id) {
        $results = array(
            'success' => false,
            'reviews_found' => 0,
            'reviews_saved' => 0,
            'place_info' => null,
            'error' => null
        );

        try {
            // Debug logging
            error_log('GRS: Processing API response for place_id: ' . $place_id);
            error_log('GRS: API response keys: ' . json_encode(array_keys($api_response)));

            if (!isset($api_response['data']) || !is_array($api_response['data']) || empty($api_response['data'])) {
                error_log('GRS: No data found in API response. Full response: ' . json_encode($api_response));
                throw new Exception('No data found in API response. Response keys: ' . implode(', ', array_keys($api_response)));
            }

            $place_data = $api_response['data'][0];
            error_log('GRS: Place data keys: ' . json_encode(array_keys($place_data)));
            
            // Extract place information
            $results['place_info'] = array(
                'name' => isset($place_data['name']) ? $place_data['name'] : '',
                'address' => isset($place_data['address']) ? $place_data['address'] : '',
                'phone' => isset($place_data['phone']) ? $place_data['phone'] : '',
                'website' => isset($place_data['website']) ? $place_data['website'] : '',
                'verified' => isset($place_data['verified']) ? $place_data['verified'] : false,
                'reviews_link' => isset($place_data['reviews_link']) ? $place_data['reviews_link'] : '',
                'reviews_per_rating' => isset($place_data['reviews_per_rating']) ? $place_data['reviews_per_rating'] : null
            );
            
            // Extract reviews - handle different possible field names
            $reviews = array();
            if (isset($place_data['reviews_data']) && is_array($place_data['reviews_data'])) {
                $reviews = $place_data['reviews_data'];
            } elseif (isset($place_data['reviews']) && is_array($place_data['reviews'])) {
                $reviews = $place_data['reviews'];
            }
            
            if (empty($reviews)) {
                throw new Exception('No reviews found in API response');
            }
            
            $results['reviews_found'] = count($reviews);
            
            // Process each review to ensure proper data structure
            $processed_reviews = array();
            foreach ($reviews as $review) {
                // Map Outscraper fields to our expected structure
                $processed_review = array(
                    'review_id' => isset($review['review_id']) ? $review['review_id'] : 
                                (isset($review['id']) ? $review['id'] : uniqid()),
                    'author_name' => isset($review['author_title']) ? $review['author_title'] : 
                                    (isset($review['name']) ? $review['name'] : 
                                    (isset($review['author_name']) ? $review['author_name'] : 'Anonymous')),
                    'author_url' => isset($review['author_link']) ? $review['author_link'] : 
                                (isset($review['author_url']) ? $review['author_url'] : null),
                    'profile_photo_url' => isset($review['author_image']) ? $review['author_image'] : 
                                        (isset($review['profile_photo_url']) ? $review['profile_photo_url'] : null),
                    'rating' => isset($review['rating']) ? intval($review['rating']) : 5,
                    'text' => isset($review['review_text']) ? $review['review_text'] : 
                            (isset($review['text']) ? $review['text'] : ''),
                    'time' => isset($review['review_timestamp']) ? $review['review_timestamp'] : 
                            (isset($review['time']) ? $review['time'] : time()),
                    'relative_time_description' => isset($review['review_datetime_utc']) ? $review['review_datetime_utc'] : 
                                                (isset($review['relative_time_description']) ? $review['relative_time_description'] : ''),
                    'language' => isset($review['review_language']) ? $review['review_language'] : 'en',
                    'photos_links' => isset($review['review_photos']) ? $review['review_photos'] : null,
                    'review_likes_count' => isset($review['likes_count']) ? intval($review['likes_count']) : 0,
                    'is_local_guide' => isset($review['is_local_guide']) ? $review['is_local_guide'] : false
                );
                
                $processed_reviews[] = $processed_review;
            }
            
            // Save reviews to database
            require_once(GRS_PLUGIN_PATH . 'includes/database-handler.php');
            $saved_count = GRS_Database::save_reviews($place_id, $processed_reviews);
            
            $results['reviews_saved'] = $saved_count;
            $results['success'] = true;
            
            // Log extraction
            GRS_Database::log_extraction(
                $place_id,
                'success',
                $saved_count,
                null,
                isset($api_response['id']) ? $api_response['id'] : null
            );
            
        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
            
            // Log failed extraction
            GRS_Database::log_extraction(
                $place_id,
                'failed',
                0,
                $e->getMessage(),
                isset($api_response['id']) ? $api_response['id'] : null
            );
        }
        
        return $results;
    }
    
    /**
     * Get available credits/usage
     * 
     * @return array|WP_Error
     */
    public function get_usage_info() {
        // Try the correct endpoint for Outscraper
        $url = self::API_BASE_URL . '/api-keys/me';
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'X-API-KEY' => $this->api_token,
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            // If the first endpoint fails, try an alternative
            $url_alt = self::API_BASE_URL . '/account/info';
            
            $response_alt = wp_remote_get($url_alt, array(
                'timeout' => 30,
                'headers' => array(
                    'X-API-KEY' => $this->api_token,
                    'Accept' => 'application/json'
                )
            ));
            
            if (!is_wp_error($response_alt)) {
                $response_code = wp_remote_retrieve_response_code($response_alt);
                $response_body = wp_remote_retrieve_body($response_alt);
                
                if ($response_code === 200) {
                    return json_decode($response_body, true);
                }
            }
            
            return new WP_Error(
                'api_error',
                sprintf('API returned error code: %d', $response_code),
                array('body' => $response_body)
            );
        }
        
        return json_decode($response_body, true);
    }
    
    /**
     * Extract reviews with filters
     * 
     * @param string $place_id
     * @param array $filters
     * @return array|WP_Error
     */
    public function extract_filtered_reviews($place_id, $filters = array()) {
        $params = array();
        
        // Apply rating filter
        if (isset($filters['min_rating'])) {
            $params['cutoffRating'] = intval($filters['min_rating']);
            $params['sort'] = 'highest_rating';
        }
        
        // Apply date filters
        if (isset($filters['start_date'])) {
            $params['start'] = strtotime($filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $params['cutoff'] = strtotime($filters['end_date']);
        }
        
        // Apply review query filter
        if (isset($filters['review_query'])) {
            $params['reviewsQuery'] = $filters['review_query'];
        }
        
        // Apply language filter
        if (isset($filters['language'])) {
            $params['language'] = $filters['language'];
        }
        
        // Extract reviews with filters
        return $this->extract_reviews($place_id, $filters['limit'] ?? 100, $params);
    }
}

// AJAX handlers for admin panel
add_action('wp_ajax_grs_extract_reviews', 'grs_handle_extract_reviews');
function grs_handle_extract_reviews() {
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
    
    // Get parameters
    $place_id = isset($_POST['place_id']) ? sanitize_text_field($_POST['place_id']) : '';
    $reviews_limit = isset($_POST['reviews_limit']) ? intval($_POST['reviews_limit']) : 100;
    
    if (empty($place_id)) {
        wp_send_json_error('Place ID is required');
        return;
    }
    
    // Initialize API
    $api = new GRS_Outscraper_API();
    
    // Extract reviews
    $response = $api->extract_reviews($place_id, $reviews_limit);
    
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
        return;
    }
    
    // Process and save reviews
    $results = $api->process_reviews_response($response, $place_id);
    
    if ($results['success']) {
        wp_send_json_success($results);
    } else {
        wp_send_json_error($results['error'] ?: 'Unknown error occurred');
    }
}

// Debug function to test the API connection
add_action('wp_ajax_grs_test_api', 'grs_test_api_connection');
function grs_test_api_connection() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $api = new GRS_Outscraper_API();
    
    // Test the API token
    $test_url = GRS_Outscraper_API::API_BASE_URL . '/maps/reviews-v3?query=test&reviewsLimit=1&async=false';
    
    $response = wp_remote_get($test_url, array(
        'timeout' => 30,
        'headers' => array(
            'X-API-KEY' => $api->api_token,
            'Accept' => 'application/json'
        )
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error('Connection error: ' . $response->get_error_message());
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    wp_send_json_success(array(
        'code' => $response_code,
        'body' => json_decode($response_body, true)
    ));
}