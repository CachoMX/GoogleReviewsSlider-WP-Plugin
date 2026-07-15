<?php
/**
 * SerpAPI Integration for Google Reviews Slider
 *
 * @package GoogleReviewsSlider
 * @since 2.0.0
 */

class GRS_SerpAPI {
    /**
     * API Base URL
     */
    const API_BASE_URL = 'https://serpapi.com/search.json';

    /**
     * API Key
     */
    private $api_key;

    /**
     * Constructor
     *
     * Key resolution: explicit arg > GRS_SERPAPI_KEY constant (set in wp-config.php,
     * never committed) > saved setting. No key is hardcoded in source.
     */
    public function __construct($api_key = null) {
        if ($api_key) {
            $this->api_key = $api_key;
        } elseif (defined('GRS_SERPAPI_KEY') && GRS_SERPAPI_KEY) {
            $this->api_key = GRS_SERPAPI_KEY;
        } else {
            $options = get_option('grs_settings');
            $this->api_key = !empty($options['grs_serpapi_key']) ? $options['grs_serpapi_key'] : '';
        }
    }

    /**
     * Fast cURL request
     */
    private function curl_get($url) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => array('Accept: application/json')
        ));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return new WP_Error('curl_error', $error);
        }

        if ($http_code !== 200) {
            $data = json_decode($response, true);
            $msg = isset($data['error']) ? $data['error'] : 'HTTP ' . $http_code;
            return new WP_Error('api_error', $msg);
        }

        return json_decode($response, true);
    }

    /**
     * Extract reviews from Google Maps using SerpAPI
     *
     * @param string $data_id Google Maps Data ID (format: 0x...:0x...)
     * @param string $sort Sort order: qualityScore, newestFirst, ratingHigh, ratingLow
     * @param string $hl Language code
     * @return array|WP_Error
     */
    public function extract_reviews($data_id, $sort = 'newestFirst', $hl = 'en') {
        $params = array(
            'engine' => 'google_maps_reviews',
            'data_id' => $data_id,
            'hl' => $hl,
            'sort_by' => $sort,
            'api_key' => $this->api_key
        );

        $url = self::API_BASE_URL . '?' . http_build_query($params);
        return $this->curl_get($url);
    }

    /**
     * Extract reviews with pagination (fetch multiple pages)
     *
     * @param string $data_id Google Maps Data ID
     * @param int $max_reviews Maximum reviews to fetch
     * @param string $sort Sort order
     * @param string $hl Language
     * @return array|WP_Error
     */
    public function extract_all_reviews($data_id, $max_reviews = 100, $sort = 'newestFirst', $hl = 'en') {
        $all_reviews = array();
        $place_info = null;
        $next_page_token = null;

        while (count($all_reviews) < $max_reviews) {
            $params = array(
                'engine' => 'google_maps_reviews',
                'data_id' => $data_id,
                'hl' => $hl,
                'sort_by' => $sort,
                'api_key' => $this->api_key
            );

            if ($next_page_token) {
                $params['next_page_token'] = $next_page_token;
            }

            $url = self::API_BASE_URL . '?' . http_build_query($params);
            $data = $this->curl_get($url);

            if (is_wp_error($data)) {
                if (empty($all_reviews)) {
                    return $data;
                }
                break;
            }

            // Get place info from first response
            if (!$place_info && isset($data['place_info'])) {
                $place_info = $data['place_info'];
            }

            // Add reviews
            if (isset($data['reviews']) && is_array($data['reviews'])) {
                $all_reviews = array_merge($all_reviews, $data['reviews']);
            } else {
                break;
            }

            // Check for next page
            if (isset($data['serpapi_pagination']['next_page_token'])) {
                $next_page_token = $data['serpapi_pagination']['next_page_token'];
            } else {
                break;
            }

            // Small delay to avoid rate limits
            usleep(200000); // 200ms
        }

        // Trim to max reviews
        if (count($all_reviews) > $max_reviews) {
            $all_reviews = array_slice($all_reviews, 0, $max_reviews);
        }

        return array(
            'place_info' => $place_info,
            'reviews' => $all_reviews,
            'total_fetched' => count($all_reviews)
        );
    }

    /**
     * Process and save reviews from SerpAPI response
     *
     * @param array $api_response
     * @param string $place_id Place ID for database storage
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
            // Handle both single response and paginated response formats
            $reviews = array();
            $place_info = null;

            if (isset($api_response['reviews'])) {
                $reviews = $api_response['reviews'];
            }

            if (isset($api_response['place_info'])) {
                $place_info = $api_response['place_info'];

                // Try different field names that SerpAPI might use
                $business_name = $place_info['title'] ?? $place_info['name'] ?? '';
                $business_rating = $place_info['rating'] ?? 0;
                $reviews_count = $place_info['reviews'] ?? $place_info['reviews_count'] ?? $place_info['user_ratings_total'] ?? 0;

                $results['place_info'] = array(
                    'name' => $business_name,
                    'address' => $place_info['address'] ?? '',
                    'rating' => $business_rating,
                    'reviews_count' => $reviews_count
                );

                // Save business info to options for display in shortcode
                $options = get_option('grs_settings', array());
                if (!empty($business_name)) {
                    $options['grs_business_name'] = sanitize_text_field($business_name);
                }
                if (!empty($business_rating)) {
                    $options['grs_business_rating'] = floatval($business_rating);
                }
                if (!empty($reviews_count)) {
                    $options['grs_total_reviews'] = intval($reviews_count);
                }
                update_option('grs_settings', $options);

                error_log('GRS: Saved business info - Name: ' . $business_name . ', Rating: ' . $business_rating);
            } else {
                error_log('GRS: No place_info in API response. Keys: ' . implode(', ', array_keys($api_response)));
            }

            if (empty($reviews)) {
                throw new Exception('No reviews found in API response');
            }

            // Filter ONLY 5-star reviews
            $five_star_reviews = array_filter($reviews, function($review) {
                return isset($review['rating']) && intval($review['rating']) === 5;
            });

            $results['reviews_found'] = count($reviews);
            $results['five_star_count'] = count($five_star_reviews);

            if (empty($five_star_reviews)) {
                throw new Exception('No 5-star reviews found');
            }

            // Process each 5-star review - map SerpAPI fields to our DB structure
            $processed_reviews = array();
            foreach ($five_star_reviews as $review) {
                // Get review text from various possible fields
                $review_text = $review['snippet'] ?? $review['extracted_snippet']['original'] ?? '';

                // Skip reviews without text
                if (empty(trim($review_text))) {
                    continue;
                }

                $processed_review = array(
                    'review_id' => $review['review_id'] ?? md5(($review['user']['name'] ?? '') . ($review['iso_date'] ?? '')),
                    'author_name' => $review['user']['name'] ?? 'Anonymous',
                    'author_url' => $review['user']['link'] ?? null,
                    'profile_photo_url' => $review['user']['thumbnail'] ?? null,
                    'rating' => isset($review['rating']) ? intval($review['rating']) : 5,
                    'text' => $review_text,
                    'time' => $this->convert_to_timestamp($review['iso_date'] ?? null),
                    'relative_time_description' => $review['date'] ?? '',
                    'language' => 'en',
                    'photos_links' => isset($review['images']) ? $review['images'] : null,
                    'review_likes_count' => isset($review['likes']) ? intval($review['likes']) : 0,
                    'total_number_of_reviews_by_reviewer' => isset($review['user']['reviews']) ? intval($review['user']['reviews']) : null,
                    'is_local_guide' => isset($review['user']['local_guide']) ? (bool)$review['user']['local_guide'] : false,
                    'response_from_owner_text' => $review['response']['snippet'] ?? null,
                    'response_from_owner_time' => isset($review['response']['date']) ? $this->convert_to_timestamp($review['response']['date']) : null,
                    'source' => 'serpapi'
                );

                $processed_reviews[] = $processed_review;
            }

            // Save reviews to database
            require_once(GRS_PLUGIN_PATH . 'includes/database-handler.php');
            $saved_count = GRS_Database::save_reviews($place_id, $processed_reviews);

            $results['reviews_saved'] = $saved_count;
            $results['success'] = true;

            // Clear transient cache so shortcode reads fresh data from DB
            delete_transient('grs_reviews');
            delete_transient('grs_total_review_count');

            // Log extraction
            GRS_Database::log_extraction(
                $place_id,
                'success',
                $saved_count,
                null,
                null
            );

        } catch (Exception $e) {
            $results['error'] = $e->getMessage();

            GRS_Database::log_extraction(
                $place_id,
                'failed',
                0,
                $e->getMessage(),
                null
            );
        }

        return $results;
    }

    /**
     * Convert various timestamp formats to Unix timestamp
     *
     * @param mixed $timestamp
     * @return int Unix timestamp
     */
    private function convert_to_timestamp($timestamp) {
        if (is_int($timestamp)) {
            return $timestamp;
        }

        if (is_numeric($timestamp) && strlen($timestamp) >= 10) {
            return intval($timestamp);
        }

        if (is_string($timestamp) && !empty($timestamp)) {
            $parsed = strtotime($timestamp);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        return time();
    }

    /**
     * Get account info / credits
     *
     * @return array|WP_Error
     */
    public function get_account_info() {
        $url = 'https://serpapi.com/account.json?api_key=' . $this->api_key;
        return $this->curl_get($url);
    }

    /**
     * Get data_id from Google Place ID
     *
     * @param string $place_id Google Place ID (ChIJ...)
     * @return string|WP_Error data_id or error
     */
    public function get_data_id_from_place_id($place_id) {
        $params = array(
            'engine' => 'google_maps',
            'place_id' => $place_id,
            'api_key' => $this->api_key
        );

        $url = self::API_BASE_URL . '?' . http_build_query($params);
        $data = $this->curl_get($url);

        if (is_wp_error($data)) {
            return $data;
        }

        if (isset($data['place_results']['data_id'])) {
            return $data['place_results']['data_id'];
        }

        return new WP_Error('not_found', 'Could not find data_id for this Place ID');
    }

    /**
     * Search for a place and get its data_id
     *
     * @param string $query Business name or address
     * @return array|WP_Error
     */
    public function search_place($query) {
        $params = array(
            'engine' => 'google_maps',
            'q' => $query,
            'type' => 'search',
            'api_key' => $this->api_key
        );

        $url = self::API_BASE_URL . '?' . http_build_query($params);
        $data = $this->curl_get($url);

        if (is_wp_error($data)) {
            return $data;
        }

        if (isset($data['local_results'])) {
            return $data['local_results'];
        }

        return array();
    }
}

// Backwards compatibility alias
class_alias('GRS_SerpAPI', 'GRS_Outscraper_API');

// AJAX handlers for admin panel
add_action('wp_ajax_grs_extract_reviews', 'grs_handle_extract_reviews');
function grs_handle_extract_reviews() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        return;
    }

    if (!check_ajax_referer('grs_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }

    // Get parameters
    $data_id = isset($_POST['data_id']) ? sanitize_text_field($_POST['data_id']) : '';
    $place_id = isset($_POST['place_id']) ? sanitize_text_field($_POST['place_id']) : '';
    $reviews_limit = isset($_POST['reviews_limit']) ? intval($_POST['reviews_limit']) : 100;

    if (empty($place_id) && empty($data_id)) {
        wp_send_json_error('Place ID is required');
        return;
    }

    $api = new GRS_SerpAPI();

    // Resolve data_id: use what was sent, or derive from place_id
    if (!empty($place_id) && empty($data_id)) {
        if (strpos($place_id, '0x') === 0) {
            // place_id is already a data_id
            $data_id = $place_id;
        } else {
            // Convert Place ID (ChIJ...) -> Data ID (0x...) via SerpAPI
            $data_id = $api->get_data_id_from_place_id($place_id);

            if (is_wp_error($data_id)) {
                wp_send_json_error('Could not find business: ' . $data_id->get_error_message());
                return;
            }

            // Save both to settings and clear stale cached data
            $options = get_option('grs_settings', array());
            $options['grs_data_id'] = $data_id;
            $options['grs_place_id'] = $place_id;
            // Clear old business info so it gets refreshed from new place
            unset($options['grs_business_name']);
            unset($options['grs_business_rating']);
            unset($options['grs_total_reviews']);
            update_option('grs_settings', $options);
            // Clear cached Google review count from previous place
            delete_transient('grs_google_total_reviews');
        }
    }

    // Extract reviews
    $response = $api->extract_all_reviews($data_id, $reviews_limit);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
        return;
    }

    // Process and save reviews (use place_id for DB storage)
    $storage_id = !empty($place_id) ? $place_id : $data_id;
    $results = $api->process_reviews_response($response, $storage_id);

    if ($results['success']) {
        // Add saved business info to response for debugging
        $saved_options = get_option('grs_settings', array());
        $results['saved_business_name'] = $saved_options['grs_business_name'] ?? 'NOT SAVED';
        $results['raw_place_info_keys'] = isset($response['place_info']) ? array_keys($response['place_info']) : 'NO place_info';
        wp_send_json_success($results);
    } else {
        wp_send_json_error($results['error'] ?: 'Unknown error occurred');
    }
}

// Test API connection
add_action('wp_ajax_grs_test_api', 'grs_test_api_connection');
function grs_test_api_connection() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $api = new GRS_SerpAPI();
    $account = $api->get_account_info();

    if (is_wp_error($account)) {
        wp_send_json_error('Connection error: ' . $account->get_error_message());
        return;
    }

    wp_send_json_success(array(
        'account_email' => $account['account_email'] ?? 'N/A',
        'plan' => $account['plan_name'] ?? 'N/A',
        'searches_per_month' => $account['plan_searches_left'] ?? 'N/A',
        'total_searches_left' => $account['total_searches_left'] ?? 'N/A'
    ));
}

// Search for places
add_action('wp_ajax_grs_search_place', 'grs_handle_search_place');
function grs_handle_search_place() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    if (!check_ajax_referer('grs_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }

    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

    if (empty($query)) {
        wp_send_json_error('Search query is required');
        return;
    }

    $api = new GRS_SerpAPI();
    $results = $api->search_place($query);

    if (is_wp_error($results)) {
        wp_send_json_error($results->get_error_message());
        return;
    }

    wp_send_json_success($results);
}
