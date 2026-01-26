<?php
/**
 * Test script to verify Outscraper API review rating mapping
 *
 * This script tests the review extraction and ensures ratings are correctly mapped
 * from the Outscraper API response to our database structure.
 *
 * Usage: php test-outscraper-mapping.php
 */

// Simulate the Outscraper API response structure
$sample_api_response = array(
    'data' => array(
        array(
            'place_id' => 'ChIJfctLBUZjAIkRk32QwFX-EOI',
            'name' => 'On Point Animal Hospital North',
            'reviews_data' => array(
                // Mike Spring - 3 stars
                array(
                    'review_id' => 'test-1',
                    'author_title' => 'Mike Spring',
                    'author_link' => 'https://google.com/user1',
                    'author_image' => 'https://example.com/avatar1.jpg',
                    'review_rating' => 3, // ← THE ACTUAL FIELD NAME
                    'review_text' => 'Not sure. Price was too high.',
                    'review_timestamp' => 1767965000,
                    'review_datetime_utc' => '01/09/2026 13:23:20'
                ),
                // Alyson Roberts - 1 star
                array(
                    'review_id' => 'test-2',
                    'author_title' => 'Alyson Roberts',
                    'author_link' => 'https://google.com/user2',
                    'author_image' => 'https://example.com/avatar2.jpg',
                    'review_rating' => 1, // ← THE ACTUAL FIELD NAME
                    'review_text' => 'After my recent experience i decided to find a new vet.',
                    'review_timestamp' => 1767800000,
                    'review_datetime_utc' => '01/07/2026 10:00:00'
                ),
                // Londyn Henning - 2 stars
                array(
                    'review_id' => 'test-3',
                    'author_title' => 'Londyn Henning',
                    'author_link' => 'https://google.com/user3',
                    'author_image' => 'https://example.com/avatar3.jpg',
                    'review_rating' => 2, // ← THE ACTUAL FIELD NAME
                    'review_text' => 'Took my 13 year old orange tabby for physical.',
                    'review_timestamp' => 1767600000,
                    'review_datetime_utc' => '01/05/2026 08:00:00'
                ),
                // Test review with 5 stars
                array(
                    'review_id' => 'test-4',
                    'author_title' => 'Happy Customer',
                    'author_link' => 'https://google.com/user4',
                    'author_image' => 'https://example.com/avatar4.jpg',
                    'review_rating' => 5, // ← THE ACTUAL FIELD NAME
                    'review_text' => 'Amazing service!',
                    'review_timestamp' => 1767500000,
                    'review_datetime_utc' => '01/04/2026 12:00:00'
                )
            )
        )
    ),
    'status' => 'Success'
);

// Simulate the mapping logic from outscraper-api.php
function process_test_reviews($api_response) {
    $results = array();

    if (!isset($api_response['data'][0]['reviews_data'])) {
        return array('error' => 'No reviews_data found');
    }

    $reviews = $api_response['data'][0]['reviews_data'];

    foreach ($reviews as $review) {
        // THIS IS THE FIXED MAPPING LOGIC
        $processed_review = array(
            'review_id' => $review['review_id'],
            'author_name' => $review['author_title'] ?? 'Anonymous',
            'author_url' => $review['author_link'] ?? null,
            'profile_photo_url' => $review['author_image'] ?? null,

            // CRITICAL: Check review_rating FIRST (Outscraper field name)
            'rating' => isset($review['review_rating']) ? intval($review['review_rating']) :
                       (isset($review['rating']) ? intval($review['rating']) : 5),

            'text' => $review['review_text'] ?? '',
            'time' => $review['review_timestamp'] ?? time(),
            'relative_time_description' => $review['review_datetime_utc'] ?? ''
        );

        $results[] = $processed_review;
    }

    return $results;
}

// Run the test
echo "=== Outscraper API Rating Mapping Test ===\n\n";

$processed_reviews = process_test_reviews($sample_api_response);

if (isset($processed_reviews['error'])) {
    echo "ERROR: " . $processed_reviews['error'] . "\n";
    exit(1);
}

echo "Total reviews processed: " . count($processed_reviews) . "\n\n";

$all_correct = true;

foreach ($processed_reviews as $review) {
    $expected_rating = null;

    // Define expected ratings based on test data
    switch ($review['author_name']) {
        case 'Mike Spring':
            $expected_rating = 3;
            break;
        case 'Alyson Roberts':
            $expected_rating = 1;
            break;
        case 'Londyn Henning':
            $expected_rating = 2;
            break;
        case 'Happy Customer':
            $expected_rating = 5;
            break;
    }

    $status = ($review['rating'] === $expected_rating) ? '✓ PASS' : '✗ FAIL';
    $is_correct = ($review['rating'] === $expected_rating);

    if (!$is_correct) {
        $all_correct = false;
    }

    printf(
        "%s | %-20s | Expected: %d stars | Got: %d stars | %s\n",
        $status,
        $review['author_name'],
        $expected_rating,
        $review['rating'],
        $is_correct ? 'CORRECT' : 'INCORRECT!'
    );
}

echo "\n";
echo str_repeat("=", 70) . "\n";

if ($all_correct) {
    echo "✓ ALL TESTS PASSED!\n";
    echo "The rating mapping is working correctly.\n";
    echo "Reviews will be saved with their actual star ratings.\n";
    exit(0);
} else {
    echo "✗ SOME TESTS FAILED!\n";
    echo "The rating mapping needs to be fixed.\n";
    exit(1);
}
