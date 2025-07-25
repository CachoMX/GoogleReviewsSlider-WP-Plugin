<?php
function grs_get_reviews() {
    // Check if reviews are cached
    $cached_reviews = get_transient('grs_reviews');
    $total_review_count = get_transient('grs_total_review_count');
    
    if ($cached_reviews !== false && $total_review_count !== false) {
        return [
            'reviews' => $cached_reviews,
            'total_count' => $total_review_count
        ];
    }

    // If not cached, fetch from API
    $options = get_option('grs_settings');
    $api_key = $options['grs_api_key'];
    $place_id = $options['grs_place_id'];
    $min_rating = isset($options['grs_min_rating']) ? intval($options['grs_min_rating']) : 1;

    // Construct the API URL
     $url = "https://maps.googleapis.com/maps/api/place/details/json?placeid=$place_id&key=$api_key";
    // $url = "https://maps.googleapis.com/maps/api/place/details/json?placeid=$place_id&key=$api_key&fields=rating,reviews,user_ratings_total&reviews_sort=newest&reviews_no_translations=true&maxwidth=800";
   


    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        return ['error' => 'API Request Error: ' . $response->get_error_message()];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['result'])) {
        return ['error' => 'API Response Error: ' . print_r($data, true)];
    }

    // Get total review count from place details
    $total_review_count = isset($data['result']['user_ratings_total']) ? 
        intval($data['result']['user_ratings_total']) : 0;

    // Get reviews
    $reviews = isset($data['result']['reviews']) ? $data['result']['reviews'] : [];

    // Sort and filter reviews
    usort($reviews, function($a, $b) {
        return $b['time'] - $a['time'];
    });

    $filtered_reviews = array_filter($reviews, function($review) use ($min_rating) {
        return $review['rating'] >= $min_rating;
    });

    // Cache both the reviews and total count
    // Set cache for 1 month (30 days)
    $one_month = 30 * DAY_IN_SECONDS;
    set_transient('grs_reviews', $filtered_reviews, $one_month);
    set_transient('grs_total_review_count', $total_review_count, $one_month);

    return [
        'reviews' => $filtered_reviews,
        'total_count' => $total_review_count
    ];
}
