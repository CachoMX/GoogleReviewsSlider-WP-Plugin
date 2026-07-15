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
     * Max paginated requests per sync. Each page is one billable SerpAPI
     * search returning ~10 reviews sorted newest-first, so two pages give
     * enough raw material to fill the 10-review cap after rating/text
     * filters without burning extra credits.
     */
    const MAX_PAGES = 2;

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
     * GET a SerpAPI endpoint and log the call for spend auditing.
     *
     * @param string      $url
     * @param string      $context  Call-site label stored in the API log.
     * @param string|null $place_id
     * @return array|WP_Error Decoded JSON body.
     */
    private function request($url, $context, $place_id = null) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'No SerpAPI key configured');
        }

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array('Accept' => 'application/json'),
        ));

        require_once(GRS_PLUGIN_PATH . 'includes/database-handler.php');

        if (is_wp_error($response)) {
            GRS_Database::log_api_call($context, $place_id, null, 'error');
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // A 200 whose body fails JSON parsing is still a failed call.
        $parsed_ok = ($http_code === 200 && is_array($data));
        GRS_Database::log_api_call($context, $place_id, $http_code, $parsed_ok ? 'ok' : 'error');

        if ($http_code !== 200) {
            $msg = isset($data['error']) ? $data['error'] : 'HTTP ' . $http_code;
            return new WP_Error('api_error', $msg);
        }

        if (!is_array($data)) {
            return new WP_Error('parse_error', 'SerpAPI returned a non-JSON body');
        }

        return $data;
    }

    /**
     * Fetch the newest reviews for a place, mapped and filtered.
     *
     * Pages through google_maps_reviews (sort newest-first) until
     * GRS_Database::MAX_REVIEWS_PER_PLACE usable reviews are collected or
     * MAX_PAGES is hit. Usable means: rating >= $min_rating, non-empty
     * text, and a parseable review date. Reviews with no parseable date
     * are dropped because an invented timestamp would corrupt the
     * newest-first ordering.
     *
     * @param string $data_id    Google Maps data ID (0x...:0x...).
     * @param string $place_id   Storage/logging key.
     * @param int    $min_rating 1-5.
     * @return array|WP_Error {place_info: array|null, reviews: array, raw_count: int}
     */
    public function fetch_recent_reviews($data_id, $place_id, $min_rating = 1) {
        $needed = GRS_Database::MAX_REVIEWS_PER_PLACE;
        $collected = array();
        $place_info = null;
        $raw_count = 0;
        $next_page_token = null;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $params = array(
                'engine' => 'google_maps_reviews',
                'data_id' => $data_id,
                'hl' => 'en',
                'sort_by' => 'newestFirst',
                'api_key' => $this->api_key,
            );
            if ($next_page_token) {
                $params['next_page_token'] = $next_page_token;
            }

            $data = $this->request(
                self::API_BASE_URL . '?' . http_build_query($params),
                'reviews_page_' . $page,
                $place_id
            );

            if (is_wp_error($data)) {
                if (empty($collected)) {
                    return $data;
                }
                break;
            }

            if (!$place_info && isset($data['place_info'])) {
                $place_info = $this->map_place_info($data['place_info']);
            }

            $raw_reviews = isset($data['reviews']) && is_array($data['reviews']) ? $data['reviews'] : array();
            $raw_count += count($raw_reviews);

            foreach ($raw_reviews as $raw) {
                $mapped = $this->map_review($raw);
                if ($mapped === null || $mapped['rating'] < $min_rating) {
                    continue;
                }
                // Keyed by review_id: dedupes across pages.
                $collected[$mapped['review_id']] = $mapped;
            }

            if (count($collected) >= $needed) {
                break;
            }

            if (empty($data['serpapi_pagination']['next_page_token'])) {
                break;
            }
            $next_page_token = $data['serpapi_pagination']['next_page_token'];
        }

        return array(
            'place_info' => $place_info,
            'reviews' => array_values($collected),
            'raw_count' => $raw_count,
        );
    }

    /**
     * Map one raw SerpAPI review to the storage row shape.
     *
     * @param array $raw
     * @return array|null Null when the review is unusable (no text or no
     *                    parseable date).
     */
    public function map_review($raw) {
        $text = '';
        if (!empty($raw['snippet'])) {
            $text = $raw['snippet'];
        } elseif (!empty($raw['extracted_snippet']['original'])) {
            $text = $raw['extracted_snippet']['original'];
        }

        if (trim($text) === '') {
            return null;
        }

        $time = $this->parse_timestamp(isset($raw['iso_date']) ? $raw['iso_date'] : null);
        if ($time === false) {
            return null;
        }

        $author = isset($raw['user']['name']) ? $raw['user']['name'] : '';
        $review_id = !empty($raw['review_id'])
            ? $raw['review_id']
            : md5($author . '|' . $raw['iso_date']);

        return array(
            'review_id' => $review_id,
            'author_name' => $author !== '' ? $author : 'Anonymous',
            'author_url' => isset($raw['user']['link']) ? $raw['user']['link'] : null,
            'profile_photo_url' => isset($raw['user']['thumbnail']) ? $raw['user']['thumbnail'] : null,
            'rating' => isset($raw['rating']) ? intval($raw['rating']) : 0,
            'text' => $text,
            'time' => $time,
            'relative_time_description' => isset($raw['date']) ? $raw['date'] : '',
            'language' => 'en',
            'photos_links' => isset($raw['images']) ? $raw['images'] : null,
            'review_likes_count' => isset($raw['likes']) ? intval($raw['likes']) : 0,
            'total_number_of_reviews_by_reviewer' => isset($raw['user']['reviews']) ? intval($raw['user']['reviews']) : null,
            'is_local_guide' => !empty($raw['user']['local_guide']),
            'response_from_owner_text' => isset($raw['response']['snippet']) ? $raw['response']['snippet'] : null,
            'response_from_owner_time' => $this->parse_owner_response_time($raw),
            'source' => 'serpapi',
        );
    }

    /**
     * Owner responses sometimes carry only a relative date string
     * ("a week ago"), which strtotime can still resolve; unlike review
     * dates this field is cosmetic, so a missing value is just null.
     */
    private function parse_owner_response_time($raw) {
        foreach (array('iso_date', 'date') as $field) {
            if (!empty($raw['response'][$field])) {
                $parsed = $this->parse_timestamp($raw['response'][$field]);
                if ($parsed !== false) {
                    return $parsed;
                }
            }
        }
        return null;
    }

    private function map_place_info($place_info) {
        return array(
            'name' => isset($place_info['title']) ? $place_info['title'] : (isset($place_info['name']) ? $place_info['name'] : ''),
            'address' => isset($place_info['address']) ? $place_info['address'] : '',
            'rating' => isset($place_info['rating']) ? $place_info['rating'] : 0,
            'reviews_count' => isset($place_info['reviews']) ? $place_info['reviews'] : (isset($place_info['reviews_count']) ? $place_info['reviews_count'] : 0),
        );
    }

    /**
     * Parse an ISO-8601 date into a UTC Unix timestamp.
     *
     * @param mixed $value
     * @return int|false False when the value is missing or unparseable;
     *                   callers must drop the review rather than guess.
     */
    private function parse_timestamp($value) {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_numeric($value) && strlen((string) $value) >= 10) {
            return intval($value);
        }

        if (is_string($value) && $value !== '') {
            $parsed = strtotime($value);
            if ($parsed !== false && $parsed > 0) {
                return $parsed;
            }
        }

        return false;
    }

    /**
     * Get account info / credits
     *
     * @return array|WP_Error
     */
    public function get_account_info() {
        return $this->request(
            'https://serpapi.com/account.json?api_key=' . $this->api_key,
            'account_info'
        );
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
            'api_key' => $this->api_key,
        );

        $data = $this->request(self::API_BASE_URL . '?' . http_build_query($params), 'place_lookup', $place_id);

        if (is_wp_error($data)) {
            return $data;
        }

        if (isset($data['place_results']['data_id'])) {
            return $data['place_results']['data_id'];
        }

        return new WP_Error('not_found', 'Could not find data_id for this Place ID');
    }
}

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

    $place_id = isset($_POST['place_id']) ? sanitize_text_field(wp_unslash($_POST['place_id'])) : '';
    $data_id = isset($_POST['data_id']) ? sanitize_text_field(wp_unslash($_POST['data_id'])) : '';

    if (empty($place_id) && empty($data_id)) {
        wp_send_json_error('Place ID is required');
        return;
    }

    // The form's live values may differ from saved settings when the admin
    // picked a new place on the map without hitting Save. Persist them first
    // so sync, admin table, and frontend all key off the same place.
    $options = get_option('grs_settings', array());
    $saved_place = isset($options['grs_place_id']) ? $options['grs_place_id'] : '';

    if (!empty($place_id) && $place_id !== $saved_place) {
        $options['grs_place_id'] = $place_id;
        $options['grs_data_id'] = $data_id;
        update_option('grs_settings', $options);
        delete_option('grs_business_info');

        // The old place's reviews are kept as orphans and cleaned up only
        // after the next successful sync (GRS_Sync), so a failed sync can
        // never leave the site with zero reviews. Cached pages showing the
        // old place must die now, though, even if the sync below fails.
        require_once(GRS_PLUGIN_PATH . 'includes/sync-handler.php');
        GRS_Sync::purge_page_caches();
        // Also clears last_attempt, so the 2-minute manual rate limit
        // cannot block the admin's very next click after a place switch.
        GRS_Sync::reset_for_new_place();
    }

    require_once(GRS_PLUGIN_PATH . 'includes/sync-handler.php');
    $result = GRS_Sync::run('manual');

    if ($result['status'] === 'ok') {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message']);
    }
}

// Test API connection
add_action('wp_ajax_grs_test_api', 'grs_test_api_connection');
function grs_test_api_connection() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    if (!check_ajax_referer('grs_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }

    $api = new GRS_SerpAPI();
    $account = $api->get_account_info();

    if (is_wp_error($account)) {
        wp_send_json_error('Connection error: ' . $account->get_error_message());
        return;
    }

    wp_send_json_success(array(
        'account_email' => isset($account['account_email']) ? $account['account_email'] : 'N/A',
        'plan' => isset($account['plan_name']) ? $account['plan_name'] : 'N/A',
        'searches_per_month' => isset($account['plan_searches_left']) ? $account['plan_searches_left'] : 'N/A',
        'total_searches_left' => isset($account['total_searches_left']) ? $account['total_searches_left'] : 'N/A',
    ));
}
