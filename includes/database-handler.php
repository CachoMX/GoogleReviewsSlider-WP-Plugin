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
    const DB_VERSION = '2.1.0';

    /**
     * Hard cap of stored/rendered reviews per place. Keeps SerpAPI spend
     * bounded and the slider payload small.
     */
    const MAX_REVIEWS_PER_PLACE = 10;
    
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
            source varchar(50) DEFAULT 'serpapi',
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
        
        // API call audit table: one row per billable SerpAPI request
        $api_log_table = $wpdb->prefix . 'grs_api_log';
        $sql_api_log = "CREATE TABLE IF NOT EXISTS $api_log_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            context varchar(50) NOT NULL,
            place_id varchar(255),
            http_code smallint,
            status varchar(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_created (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_reviews);
        dbDelta($sql_history);
        dbDelta($sql_api_log);
    }

    /**
     * Ensure required tables exist, recreating them on the fly if missing.
     *
     * Self-healing guard. If the reviews table is dropped (e.g. uninstall.php
     * running during a botched re-install, or a manual DB cleanup), the
     * front-end would otherwise hit a "Table doesn't exist" error on every
     * request and fall through to an external API fetch + bulk save on each
     * page load, exhausting PHP workers and taking the whole account down.
     * This runs at most one cheap SHOW TABLES per request.
     */
    public static function ensure_tables() {
        static $checked = false;

        if ($checked) {
            return;
        }
        $checked = true;

        global $wpdb;
        $reviews_table = $wpdb->prefix . 'grs_reviews';
        $found = $wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($reviews_table) . "'");

        if ($found !== $reviews_table) {
            self::create_tables();
        }
    }

    /**
     * Atomically replace all stored reviews for a place.
     *
     * Deletes old rows only after the caller has a validated, non-empty
     * replacement set, so an upstream API failure can never leave the
     * place without reviews. Rows beyond MAX_REVIEWS_PER_PLACE are
     * dropped (input is expected newest-first; a defensive sort keeps
     * the invariant even if the caller forgot).
     *
     * @param string $place_id
     * @param array  $reviews Mapped review rows (see GRS_SerpAPI::map_review()).
     * @return int|WP_Error Number of reviews stored, or error when input is empty.
     */
    public static function replace_reviews($place_id, $reviews) {
        global $wpdb;

        self::ensure_tables();

        if (empty($reviews)) {
            return new WP_Error('empty_set', 'Refusing to replace stored reviews with an empty set');
        }

        usort($reviews, function ($a, $b) {
            return $b['time'] <=> $a['time'];
        });
        $reviews = array_slice($reviews, 0, self::MAX_REVIEWS_PER_PLACE);

        $table_name = $wpdb->prefix . 'grs_reviews';
        $saved_count = 0;

        // No-op on MyISAM; on InnoDB it makes delete+insert atomic.
        $wpdb->query('START TRANSACTION');

        $wpdb->delete($table_name, array('place_id' => $place_id), array('%s'));

        foreach ($reviews as $review) {
            $result = $wpdb->insert($table_name, array(
                'place_id' => $place_id,
                'review_id' => $review['review_id'],
                'author_name' => !empty($review['author_name']) ? $review['author_name'] : 'Anonymous',
                'author_url' => isset($review['author_url']) ? $review['author_url'] : null,
                'profile_photo_url' => isset($review['profile_photo_url']) ? $review['profile_photo_url'] : null,
                'rating' => isset($review['rating']) ? intval($review['rating']) : 5,
                'text' => isset($review['text']) ? $review['text'] : '',
                'time' => intval($review['time']),
                'relative_time_description' => isset($review['relative_time_description']) ? $review['relative_time_description'] : '',
                'language' => isset($review['language']) ? $review['language'] : 'en',
                'response_from_owner_text' => isset($review['response_from_owner_text']) ? $review['response_from_owner_text'] : null,
                'response_from_owner_time' => isset($review['response_from_owner_time']) ? $review['response_from_owner_time'] : null,
                'photos_links' => isset($review['photos_links']) ? wp_json_encode($review['photos_links']) : null,
                'review_likes_count' => isset($review['review_likes_count']) ? intval($review['review_likes_count']) : 0,
                'total_number_of_reviews_by_reviewer' => isset($review['total_number_of_reviews_by_reviewer']) ? intval($review['total_number_of_reviews_by_reviewer']) : null,
                'is_local_guide' => !empty($review['is_local_guide']) ? 1 : 0,
                'source' => isset($review['source']) ? $review['source'] : 'serpapi',
            ));

            if ($result === false) {
                error_log('GRS DB Error: ' . $wpdb->last_error);
            } else {
                $saved_count++;
            }
        }

        if ($saved_count === 0) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('insert_failed', 'All review inserts failed: ' . $wpdb->last_error);
        }

        $wpdb->query('COMMIT');

        return $saved_count;
    }

    /**
     * Delete rows stored under any place other than $place_id. Runs only
     * after a successful sync so a failed place switch can never leave
     * the site with zero reviews.
     *
     * @param string $place_id
     * @return int Rows deleted.
     */
    public static function delete_orphan_reviews($place_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'grs_reviews';
        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE place_id != %s",
            $place_id
        ));
    }

    /**
     * Trim stored reviews for a place down to the newest $keep by review time.
     *
     * @param string $place_id
     * @param int    $keep
     * @return int Rows deleted.
     */
    public static function prune_reviews($place_id, $keep = self::MAX_REVIEWS_PER_PLACE) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'grs_reviews';

        // MySQL forbids LIMIT in a NOT IN subquery; the derived table works around it.
        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name
            WHERE place_id = %s
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM $table_name
                    WHERE place_id = %s
                    ORDER BY time DESC, id DESC
                    LIMIT %d
                ) keepers
            )",
            $place_id,
            $place_id,
            $keep
        ));
    }

    /**
     * Record one outbound API request for spend auditing.
     *
     * @param string      $context   Which call site (e.g. 'reviews_page', 'place_lookup').
     * @param string|null $place_id
     * @param int|null    $http_code
     * @param string      $status    'ok' | 'error'.
     */
    public static function log_api_call($context, $place_id = null, $http_code = null, $status = 'ok') {
        global $wpdb;

        self::ensure_tables();

        $wpdb->insert($wpdb->prefix . 'grs_api_log', array(
            'context' => $context,
            'place_id' => $place_id,
            'http_code' => $http_code,
            'status' => $status,
        ));
    }

    /**
     * Count API calls made since a given moment.
     *
     * @param string $since MySQL datetime.
     * @return int
     */
    public static function count_api_calls_since($since) {
        global $wpdb;

        self::ensure_tables();

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}grs_api_log WHERE created_at >= %s",
            $since
        ));
    }
    
    /**
     * Get reviews from database
     * 
     * @param string $place_id
     * @param int $min_rating
     * @param int $limit
     * @return array
     */
    public static function get_reviews($place_id, $min_rating = 1, $limit = self::MAX_REVIEWS_PER_PLACE) {
        global $wpdb;

        self::ensure_tables();

        $table_name = $wpdb->prefix . 'grs_reviews';

        $query = $wpdb->prepare(
            "SELECT * FROM $table_name
            WHERE place_id = %s
            AND rating >= %d
            ORDER BY time DESC, id DESC
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

        self::ensure_tables();

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
     * Remove duplicate reviews
     * 
     * @param string $place_id
     * @return int Number of duplicates removed
     */
    public static function remove_duplicates($place_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'grs_reviews';
        
        // Find and delete duplicates, keeping the one with the lowest ID
        if ($place_id) {
            $query = "
                DELETE r1 FROM $table_name r1
                INNER JOIN $table_name r2 
                WHERE r1.id > r2.id 
                AND r1.place_id = r2.place_id 
                AND r1.review_id = r2.review_id
                AND r1.place_id = %s
            ";
            
            return $wpdb->query($wpdb->prepare($query, $place_id));
        } else {
            $query = "
                DELETE r1 FROM $table_name r1
                INNER JOIN $table_name r2 
                WHERE r1.id > r2.id 
                AND r1.place_id = r2.place_id 
                AND r1.review_id = r2.review_id
            ";
            
            return $wpdb->query($query);
        }
    }
    
    /**
     * Delete all reviews for a place
     * 
     * @param string $place_id
     * @return int Number of reviews deleted
     */
    public static function delete_all_reviews($place_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'grs_reviews';
        
        return $wpdb->delete(
            $table_name,
            array('place_id' => $place_id),
            array('%s')
        );
    }
}