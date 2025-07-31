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
            'fields' => 'query,name,reviews_data,reviews_per_rating,reviews_link,web_site,verified,phone,address,postal_code,opening_hours,current_opening_status'
        );
        
        $params = wp_parse_args($params, $default_params);
        
        // Build the API URL
        $url = self::API_BASE_URL . '/maps/reviews-v3?' . http_build_query($params);
        
        // Make the API request
        $response = wp_remote_get($url, array(
            'timeout' => 60,
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
        
        $data = json_decode($response_body, true);
        
        if (!$data || !isset($data['status'])) {
            return new WP_Error('parse_error', 'Failed to parse API response');
        }
        
        if ($data['status'] !== 'Success') {
            return new WP_Error('api_status_error', 'API request failed', $data);
        }
        
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
            if (!isset($api_response['data']) || !is_array($api_response['data']) || empty($api_response['data'])) {
                throw new Exception('No data found in API response');
            }
            
            $place_data = $api_response['data'][0];
            
            // Extract place information
            $results['place_info'] = array(
                'name' => isset($place_data['name']) ? $place_data['name'] : '',
                'address' => isset($place_data['address']) ? $place_data['address'] : '',
                'phone' => isset($place_data['phone']) ? $place_data['phone'] : '',
                'website' => isset($place_data['web_site']) ? $place_data['web_site'] : '',
                'verified' => isset($place_data['verified']) ? $place_data['verified'] : false,
                'reviews_link' => isset($place_data['reviews_link']) ? $place_data['reviews_link'] : '',
                'reviews_per_rating' => isset($place_data['reviews_per_rating']) ? $place_data['reviews_per_rating'] : null
            );
            
            // Extract reviews
            if (!isset($place_data['reviews_data']) || !is_array($place_data['reviews_data'])) {
                throw new Exception('No reviews found in API response');
            }
            
            $reviews = $place_data['reviews_data'];
            $results['reviews_found'] = count($reviews);
            
            // Save reviews to database
            require_once(GRS_PLUGIN_PATH . 'includes/database-handler.php');
            $saved_count = GRS_Database::save_reviews($place_id, $reviews);
            
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
        $url = self::API_BASE_URL . '/usage';
        
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
        wp_die('Unauthorized');
    }
    
    // Verify nonce
    check_ajax_referer('grs_nonce', 'nonce');
    
    // Get parameters
    $place_id = sanitize_text_field($_POST['place_id']);
    $reviews_limit = isset($_POST['reviews_limit']) ? intval($_POST['reviews_limit']) : 100;
    
    if (empty($place_id)) {
        wp_send_json_error('Place ID is required');
    }
    
    // Initialize API
    $api = new GRS_Outscraper_API();
    
    // Extract reviews
    $response = $api->extract_reviews($place_id, $reviews_limit);
    
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }
    
    // Process and save reviews
    $results = $api->process_reviews_response($response, $place_id);
    
    if ($results['success']) {
        wp_send_json_success($results);
    } else {
        wp_send_json_error($results['error']);
    }
}

// AJAX handler for checking API usage
add_action('wp_ajax_grs_check_api_usage', 'grs_handle_check_api_usage');
function grs_handle_check_api_usage() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Verify nonce
    check_ajax_referer('grs_nonce', 'nonce');
    
    // Initialize API
    $api = new GRS_Outscraper_API();
    
    // Get usage info
    $usage = $api->get_usage_info();
    
    if (is_wp_error($usage)) {
        wp_send_json_error($usage->get_error_message());
    }
    
    wp_send_json_success($usage);
}