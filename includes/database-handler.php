<?php
/**
 * Database handler for Google Reviews Slider
 * 
 * @package GoogleReviewsSlider
 * @since 2.0.0
 */

class GRS_Database {
    /**
     * Database version
     */
    const DB_VERSION = '2.0.0';
    
    /**
     * Initialize database
     */
    public static function init() {
        // Check if we need to create/update tables
        $installed_version = get_option('grs_db_version');
        
        if ($installed_version != self::DB_VERSION) {
            self::create_tables();
            update_option('grs_db_version', self::DB_VERSION);
        }
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Reviews table
        $reviews_table = $wpdb->prefix . 'grs_reviews';
        $sql_reviews = "CREATE TABLE IF NOT EXISTS $reviews_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            place_id varchar(255) NOT NULL,
            review_id varchar(255) NOT NULL,
            author_name varchar(255) NOT NULL,
            author_url varchar(500),
            profile_photo_url varchar(500),
            rating int(1) NOT NULL,
            text text,
            time int(11) NOT NULL,
            relative_time_description varchar(255),
            language varchar(10),
            translated_text text,
            response_from_owner_text text,
            response_from_owner_time int(11),
            photos_links text,
            review_likes_count int(11) DEFAULT 0,
            total_number_of_reviews_by_reviewer int(11),
            reviewer_number_of_photos int(11),
            is_local_guide boolean DEFAULT 0,
            review_translated_by_google boolean DEFAULT 0,
            response_from_owner_translated_by_google boolean DEFAULT 0,
            extracted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            source varchar(50) DEFAULT 'outscraper',
            PRIMARY KEY (id),
            UNIQUE KEY unique_review (place_id, review_id),
            KEY idx_place_rating (place_id, rating),
            KEY idx_time (time)
        ) $charset_collate;";
        
        // Extraction history table
        $history_table = $wpdb->prefix . 'grs_extraction_history';
        $sql_history = "CREATE TABLE IF NOT EXISTS $history_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            place_id varchar(255) NOT NULL,
            extraction_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviews_extracted int(11) NOT NULL DEFAULT 0,
            status varchar(50) NOT NULL,
            error_message text,
            api_response_id varchar(255),
            PRIMARY KEY (id),
            KEY idx_place_date (place_id, extraction_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_reviews);
        dbDelta($sql_history);
    }
    
    /**
     * Save reviews to database
     * 
     * @param string $place_id
     * @param array $reviews
     * @return int Number of reviews saved
     */
    public static function save_reviews($place_id, $reviews) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'grs_reviews';
        $saved_count = 0;
        
        foreach ($reviews as $review) {
            // Prepare review data
            $data = array(
                'place_id' => $place_id,
                'review_id' => isset($review['review_id']) ? $review['review_id'] : md5($review['author_name'] . $review['time']),
                'author_name' => $review['author_name'],
                'author_url' => isset($review['author_url']) ? $review['author_url'] : null,
                'profile_photo_url' => isset($review['profile_photo_url']) ? $review['profile_photo_url'] : null,
                'rating' => intval($review['rating']),
                'text' => isset($review['text']) ? $review['text'] : '',
                'time' => isset($review['time']) ? $review['time'] : time(),
                'relative_time_description' => isset($review['relative_time_description']) ? $review['relative_time_description'] : '',
                'language' => isset($review['language']) ? $review['language'] : 'en',
                'translated_text' => isset($review['translated_text']) ? $review['translated_text'] : null,
                'response_from_owner_text' => isset($review['response_from_owner_text']) ? $review['response_from_owner_text'] : null,
                'response_from_owner_time' => isset($review['response_from_owner_time']) ? $review['response_from_owner_time'] : null,
                'photos_links' => isset($review['photos_links']) ? json_encode($review['photos_links']) : null,
                'review_likes_count' => isset($review['review_likes_count']) ? intval($review['review_likes_count']) : 0,
                'total_number_of_reviews_by_reviewer' => isset($review['total_number_of_reviews_by_reviewer']) ? intval($review['total_number_of_reviews_by_reviewer']) : null,
                'reviewer_number_of_photos' => isset($review['reviewer_number_of_photos']) ? intval($review['reviewer_number_of_photos']) : null,
                'is_local_guide' => isset($review['is_local_guide']) ? (bool)$review['is_local_guide'] : false,
                'review_translated_by_google' => isset($review['review_translated_by_google']) ? (bool)$review['review_translated_by_google'] : false,
                'response_from_owner_translated_by_google' => isset($review['response_from_owner_translated_by_google']) ? (bool)$review['response_from_owner_translated_by_google'] : false,
                'source' => 'outscraper'
            );
            
            // Insert or update review
            $result = $wpdb->replace($table_name, $data);
            
            if ($result !== false) {
                $saved_count++;
            }
        }
        
        return $saved_count;
    }
    
    /**
     * Get reviews from database
     * 
     * @param string $place_id
     * @param int $min_rating
     * @param int $limit
     * @return array
     */
    public static function get_reviews($place_id, $min_rating = 1, $limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'grs_reviews';
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE place_id = %s 
            AND rating >= %d 
            ORDER BY time DESC 
            LIMIT %d",
            $place_id,
            $min_rating,
            $limit
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Decode JSON fields
        foreach ($results as &$review) {
            if (!empty($review['photos_links'])) {
                $review['photos_links'] = json_decode($review['photos_links'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get review count by rating
     * 
     * @param string $place_id
     * @return array
     */
    public static function get_review_stats($place_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'grs_reviews';
        
        $query = $wpdb->prepare(
            "SELECT rating, COUNT(*) as count 
            FROM $table_name 
            WHERE place_id = %s 
            GROUP BY rating 
            ORDER BY rating DESC",
            $place_id
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        $stats = array(
            'total' => 0,
            'average' => 0,
            'by_rating' => array(
                5 => 0,
                4 => 0,
                3 => 0,
                2 => 0,
                1 => 0
            )
        );
        
        $total_rating = 0;
        
        foreach ($results as $row) {
            $rating = intval($row['rating']);
            $count = intval($row['count']);
            
            $stats['by_rating'][$rating] = $count;
            $stats['total'] += $count;
            $total_rating += ($rating * $count);
        }
        
        if ($stats['total'] > 0) {
            $stats['average'] = round($total_rating / $stats['total'], 1);
        }
        
        return $stats;
    }
    
    /**
     * Log extraction history
     * 
     * @param string $place_id
     * @param string $status
     * @param int $reviews_count
     * @param string $error_message
     * @param string $api_response_id
     * @return bool
     */
    public static function log_extraction($place_id, $status, $reviews_count = 0, $error_message = null, $api_response_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'grs_extraction_history';
        
        $data = array(
            'place_id' => $place_id,
            'status' => $status,
            'reviews_extracted' => $reviews_count,
            'error_message' => $error_message,
            'api_response_id' => $api_response_id
        );
        
        return $wpdb->insert($table_name, $data) !== false;
    }
    
    /**
     * Get extraction history
     * 
     * @param string $place_id
     * @param int $limit
     * @return array
     */
    public static function get_extraction_history($place_id = null, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'grs_extraction_history';
        
        if ($place_id) {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name 
                WHERE place_id = %s 
                ORDER BY extraction_date DESC 
                LIMIT %d",
                $place_id,
                $limit
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name 
                ORDER BY extraction_date DESC 
                LIMIT %d",
                $limit
            );
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Delete old reviews
     * 
     * @param string $place_id
     * @param int $days_old
     * @return int Number of reviews deleted
     */
    public static function delete_old_reviews($place_id = null, $days_old = 90) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'grs_reviews';
        $date_threshold = date('Y-m-d H:i:s', strtotime("-$days_old days"));
        
        if ($place_id) {
            $query = $wpdb->prepare(
                "DELETE FROM $table_name 
                WHERE place_id = %s 
                AND extracted_at < %s",
                $place_id,
                $date_threshold
            );
        } else {
            $query = $wpdb->prepare(
                "DELETE FROM $table_name 
                WHERE extracted_at < %s",
                $date_threshold
            );
        }
        
        return $wpdb->query($query);
    }
}