<?php
/**
 * Sync orchestrator for Google Reviews Slider.
 *
 * Single entry point for every review refresh (cron, admin button).
 * Guarantees: at most one sync runs at a time (mutex), cron cannot
 * double-fire within a month (persisted last-success guard), and stored
 * reviews are never deleted before a validated replacement set exists.
 *
 * @package GoogleReviewsSlider
 * @since 2.8.0
 */

class GRS_Sync {
    /**
     * Mutex option name. Acquired via a raw INSERT against wp_options'
     * option_name UNIQUE KEY (see acquire_lock()): the row either inserts
     * or it doesn't, so two processes can never both hold the lock.
     */
    const LOCK_OPTION = 'grs_sync_lock';

    /**
     * Seconds after which a crashed sync's lock is considered stale.
     */
    const LOCK_TTL = 10 * MINUTE_IN_SECONDS;

    /**
     * Minimum seconds between cron-triggered syncs. Slightly under 30
     * days so a cron that fires a few hours early is not skipped into
     * a 60-day gap.
     */
    const CRON_MIN_INTERVAL = 27 * DAY_IN_SECONDS;

    /**
     * Minimum seconds between manual (admin button) syncs.
     */
    const MANUAL_MIN_INTERVAL = 2 * MINUTE_IN_SECONDS;

    /**
     * Run a full sync for the configured place.
     *
     * @param string $trigger 'cron' | 'manual'.
     * @return array {status: ok|error|rate_limited|locked|skipped, message, reviews_saved}
     */
    public static function run($trigger = 'manual') {
        $options = get_option('grs_settings', array());
        $place_id = isset($options['grs_place_id']) ? $options['grs_place_id'] : '';
        $data_id = isset($options['grs_data_id']) ? $options['grs_data_id'] : '';

        if (empty($place_id) && empty($data_id)) {
            return self::finish($trigger, 'error', __('No Place ID configured.', 'google-reviews-slider'), 0);
        }

        // Cheap pre-check only; a competing sync may finish between this
        // read and the lock, so it cannot be trusted on its own.
        $guard = self::check_interval($trigger);
        if ($guard !== true) {
            return $guard;
        }

        if (!self::acquire_lock()) {
            return array(
                'status' => 'locked',
                'message' => __('Another sync is already running.', 'google-reviews-slider'),
                'reviews_saved' => 0,
            );
        }

        try {
            // Authoritative check: under the lock, nothing else can update
            // last_success/last_attempt between here and do_sync.
            $guard = self::check_interval($trigger);
            if ($guard !== true) {
                return $guard;
            }

            $result = self::do_sync($place_id, $data_id, $options, $trigger);
        } finally {
            self::release_lock();
        }

        return $result;
    }

    /**
     * @return true|array True to proceed, or a rate_limited result array.
     */
    private static function check_interval($trigger) {
        $status = get_option('grs_sync_status', array());
        $last_success = isset($status['last_success']) ? intval($status['last_success']) : 0;
        $last_attempt = isset($status['last_attempt']) ? intval($status['last_attempt']) : 0;

        if ($trigger === 'cron' && $last_success && (time() - $last_success) < self::CRON_MIN_INTERVAL) {
            return array(
                'status' => 'skipped',
                'message' => __('Cron sync skipped: last successful sync is under a month old.', 'google-reviews-slider'),
                'reviews_saved' => 0,
            );
        }

        if ($trigger === 'manual' && $last_attempt && (time() - $last_attempt) < self::MANUAL_MIN_INTERVAL) {
            return array(
                'status' => 'rate_limited',
                'message' => __('Please wait a couple of minutes between manual syncs.', 'google-reviews-slider'),
                'reviews_saved' => 0,
            );
        }

        return true;
    }

    private static function acquire_lock() {
        global $wpdb;

        // add_option() is get_option()+ODKU under the hood and can hand the
        // lock to two racing processes; a plain INSERT against the
        // option_name UNIQUE KEY cannot.
        $acquired = @$wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            self::LOCK_OPTION,
            (string) time()
        ));

        if ($acquired) {
            wp_cache_delete(self::LOCK_OPTION, 'options');
            return true;
        }

        // Conditional DELETE frees only a stale lock, never a fresh one a
        // competing process just wrote, then a single retry races cleanly.
        // option_value holds time() as a string; Unix timestamps keep the
        // same digit count until 2286, so lexicographic compare equals
        // numeric compare here.
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value < %s",
            self::LOCK_OPTION,
            (string) (time() - self::LOCK_TTL)
        ));

        $acquired = @$wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            self::LOCK_OPTION,
            (string) time()
        ));

        if ($acquired) {
            wp_cache_delete(self::LOCK_OPTION, 'options');
            return true;
        }

        return false;
    }

    private static function release_lock() {
        global $wpdb;
        $wpdb->delete($wpdb->options, array('option_name' => self::LOCK_OPTION));
        wp_cache_delete(self::LOCK_OPTION, 'options');
    }

    private static function do_sync($place_id, $data_id, $options, $trigger) {
        require_once(GRS_PLUGIN_PATH . 'includes/serpapi-handler.php');
        require_once(GRS_PLUGIN_PATH . 'includes/database-handler.php');

        self::record_attempt($trigger);

        $api = new GRS_SerpAPI();

        if (empty($data_id)) {
            if (strpos($place_id, '0x') === 0) {
                $data_id = $place_id;
            } else {
                $data_id = $api->get_data_id_from_place_id($place_id);
                if (is_wp_error($data_id)) {
                    GRS_Database::log_extraction($place_id, 'failed', 0, $data_id->get_error_message());
                    return self::finish($trigger, 'error', $data_id->get_error_message(), 0);
                }
                $options['grs_data_id'] = $data_id;
                update_option('grs_settings', $options);
            }
        }

        $min_rating = isset($options['grs_min_rating']) ? intval($options['grs_min_rating']) : 1;

        $result = $api->fetch_recent_reviews($data_id, $place_id, $min_rating);

        if (is_wp_error($result)) {
            GRS_Database::log_extraction($place_id, 'failed', 0, $result->get_error_message());
            return self::finish($trigger, 'error', $result->get_error_message(), 0);
        }

        if (!empty($result['place_info'])) {
            self::store_business_info($result['place_info']);
        }

        if (empty($result['reviews'])) {
            // Existing reviews stay untouched: an empty API answer must
            // never blank out the live slider.
            GRS_Database::log_extraction($place_id, 'failed', 0, 'API returned no usable reviews');
            return self::finish($trigger, 'error', __('The API returned no usable reviews. Existing reviews were kept.', 'google-reviews-slider'), 0);
        }

        $saved = GRS_Database::replace_reviews($place_id, $result['reviews']);

        if (is_wp_error($saved)) {
            GRS_Database::log_extraction($place_id, 'failed', 0, $saved->get_error_message());
            return self::finish($trigger, 'error', $saved->get_error_message(), 0);
        }

        GRS_Database::log_extraction($place_id, 'success', $saved);
        // Only now is it safe to drop other places' rows: the new place's
        // set is stored, so a failed place switch can never zero the site.
        GRS_Database::delete_orphan_reviews($place_id);
        self::purge_page_caches();

        return self::finish($trigger, 'ok', sprintf(
            /* translators: %d: number of reviews stored */
            __('Sync complete: %d reviews stored.', 'google-reviews-slider'),
            $saved
        ), $saved, true);
    }

    /**
     * Business identity lives in its own option, separate from
     * grs_settings, so a sync can never clobber a concurrent settings
     * save (and vice versa).
     */
    private static function store_business_info($place_info) {
        $info = get_option('grs_business_info', array());

        if (!empty($place_info['name'])) {
            $info['name'] = sanitize_text_field($place_info['name']);
        }
        if (!empty($place_info['rating'])) {
            $info['rating'] = floatval($place_info['rating']);
        }
        if (!empty($place_info['reviews_count'])) {
            $info['total_reviews'] = intval($place_info['reviews_count']);
        }
        $info['updated_at'] = time();

        update_option('grs_business_info', $info);
    }

    private static function record_attempt($trigger) {
        $status = get_option('grs_sync_status', array());
        $status['last_attempt'] = time();
        $status['trigger'] = $trigger;
        update_option('grs_sync_status', $status, false);
    }

    private static function finish($trigger, $outcome, $message, $saved, $success = false) {
        $status = get_option('grs_sync_status', array());
        $status['last_attempt'] = isset($status['last_attempt']) ? $status['last_attempt'] : time();
        $status['trigger'] = $trigger;
        $status['status'] = $outcome;
        $status['message'] = $message;
        $status['reviews_saved'] = $saved;
        if ($success) {
            $status['last_success'] = time();
        }
        update_option('grs_sync_status', $status, false);

        return array(
            'status' => $outcome,
            'message' => $message,
            'reviews_saved' => $saved,
        );
    }

    /**
     * Best-effort purge of the page caches that serve the shortcode's
     * server-rendered HTML. Without this, the admin sees fresh data
     * while visitors keep getting the cached page (BUG 1 / CR-4).
     */
    public static function purge_page_caches() {
        do_action('grs_reviews_synced');

        if (has_action('litespeed_purge_all')) {
            do_action('litespeed_purge_all');
        }
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        if (function_exists('w3tc_flush_posts')) {
            w3tc_flush_posts();
        }
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache();
        }
        if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) {
            autoptimizeCache::clearall();
        }
    }

    /**
     * Data for the admin status panel.
     *
     * @return array {last_success, last_attempt, status, message, reviews_saved, next_cron, api_calls_30d}
     */
    public static function get_status() {
        $status = get_option('grs_sync_status', array());

        return array(
            'last_success' => isset($status['last_success']) ? intval($status['last_success']) : 0,
            'last_attempt' => isset($status['last_attempt']) ? intval($status['last_attempt']) : 0,
            'status' => isset($status['status']) ? $status['status'] : '',
            'message' => isset($status['message']) ? $status['message'] : '',
            'reviews_saved' => isset($status['reviews_saved']) ? intval($status['reviews_saved']) : 0,
            'trigger' => isset($status['trigger']) ? $status['trigger'] : '',
            'next_cron' => wp_next_scheduled('grs_auto_refresh_reviews'),
            'api_calls_30d' => GRS_Database::count_api_calls_since(gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS)),
        );
    }
}
