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
     * The value is "timestamp:token"; release_lock() deletes only its own
     * token, so a sync that stalls past LOCK_TTL and finishes after a
     * takeover cannot free the taker's fresh lock.
     */
    const LOCK_OPTION = 'grs_sync_lock';

    /**
     * Full "timestamp:token" lock value this process wrote, or '' when it
     * holds no lock. Fences release_lock() to the caller's own lock.
     */
    private static $lock_token = '';

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
        // Guard outcomes go through finish() so the admin status badge can
        // show skipped/rate_limited/locked, not only ok/error.
        $guard = self::check_interval($trigger);
        if ($guard !== true) {
            return self::finish($trigger, $guard['status'], $guard['message'], 0);
        }

        if (!self::acquire_lock()) {
            return self::finish($trigger, 'locked', __('Another sync is already running.', 'google-reviews-slider'), 0);
        }

        try {
            // Authoritative check: under the lock, nothing else can update
            // last_success/last_attempt between here and do_sync.
            $guard = self::check_interval($trigger);
            if ($guard !== true) {
                return self::finish($trigger, $guard['status'], $guard['message'], 0);
            }

            $result = self::do_sync($place_id, $data_id, $options, $trigger);
        } finally {
            self::release_lock();
        }

        return $result;
    }

    /**
     * Queue a near-immediate one-shot sync and clear the monthly guard
     * so that run('cron') will not skip it. WP dedupes single events
     * within 10 minutes of an existing identical event; in that case
     * the recurring event fires soon anyway, so scheduling
     * unconditionally is safe.
     */
    public static function request_resync() {
        $status = get_option('grs_sync_status', array());
        unset($status['last_success']);
        update_option('grs_sync_status', $status, false);
        // Durable flag, not just the last_success clear above: an in-flight
        // sync's finish() would re-arm the monthly guard and swallow the
        // request. Cleared only in do_sync()'s success path.
        update_option('grs_resync_pending', time(), false);
        wp_schedule_single_event(time() + 30, 'grs_auto_refresh_reviews');
    }

    /**
     * Reset sync bookkeeping and queue a near-immediate one-shot sync.
     * Called on place changes: the whole status is deleted (not just
     * last_success) so the new place also escapes the manual rate limit,
     * and visitors should not wait for the admin to remember Sync Now.
     */
    public static function reset_for_new_place() {
        delete_option('grs_sync_status');
        // Durable flag, not just the status delete above: an in-flight
        // sync's finish() would re-arm the monthly guard and swallow the
        // request. Cleared only in do_sync()'s success path.
        update_option('grs_resync_pending', time(), false);
        wp_schedule_single_event(time() + 30, 'grs_auto_refresh_reviews');
    }

    /**
     * @return true|array True to proceed, or a rate_limited result array.
     */
    private static function check_interval($trigger) {
        $status = get_option('grs_sync_status', array());
        $last_success = isset($status['last_success']) ? intval($status['last_success']) : 0;
        // Manual rate limiting keys off its own clock so a cron attempt
        // seconds earlier cannot block Sync Now with a misleading message.
        $last_manual_attempt = isset($status['last_manual_attempt']) ? intval($status['last_manual_attempt']) : 0;

        // A pending resync request overrides the monthly guard; otherwise a
        // place/min-rating change inside the 27-day window would be skipped.
        if ($trigger === 'cron' && !get_option('grs_resync_pending') && $last_success && (time() - $last_success) < self::CRON_MIN_INTERVAL) {
            return array(
                'status' => 'skipped',
                'message' => __('Cron sync skipped: last successful sync is under a month old.', 'google-reviews-slider'),
                'reviews_saved' => 0,
            );
        }

        if ($trigger === 'manual' && $last_manual_attempt && (time() - $last_manual_attempt) < self::MANUAL_MIN_INTERVAL) {
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

        $lock_value = time() . ':' . uniqid('', true);

        // A contended acquire makes these INSERTs hit the UNIQUE KEY by
        // design; without suppression each miss writes duplicate-entry
        // noise to debug.log.
        $previous = $wpdb->suppress_errors(true);

        // add_option() is get_option()+ODKU under the hood and can hand the
        // lock to two racing processes; a plain INSERT against the
        // option_name UNIQUE KEY cannot.
        $acquired = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            self::LOCK_OPTION,
            $lock_value
        ));

        if ($acquired) {
            $wpdb->suppress_errors($previous);
            wp_cache_delete(self::LOCK_OPTION, 'options');
            self::$lock_token = $lock_value;
            return true;
        }

        // Conditional DELETE frees only a stale lock, never a fresh one a
        // competing process just wrote, then a single retry races cleanly.
        // The timestamp prefix of "timestamp:token" must be compared
        // numerically (SUBSTRING_INDEX also handles legacy bare-timestamp
        // values left by pre-token versions).
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) < %d",
            self::LOCK_OPTION,
            time() - self::LOCK_TTL
        ));

        $acquired = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            self::LOCK_OPTION,
            $lock_value
        ));

        $wpdb->suppress_errors($previous);

        if ($acquired) {
            wp_cache_delete(self::LOCK_OPTION, 'options');
            self::$lock_token = $lock_value;
            return true;
        }

        return false;
    }

    private static function release_lock() {
        global $wpdb;

        if (self::$lock_token === '') {
            return;
        }

        // Matching on the full "timestamp:token" value keeps a sync that
        // outlived LOCK_TTL from deleting a successor's fresh lock.
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
            self::LOCK_OPTION,
            self::$lock_token
        ));
        self::$lock_token = '';
        wp_cache_delete(self::LOCK_OPTION, 'options');
    }

    private static function do_sync($place_id, $data_id, $options, $trigger) {
        require_once(GRS_PLUGIN_PATH . 'includes/serpapi-handler.php');
        require_once(GRS_PLUGIN_PATH . 'includes/database-handler.php');

        $started_at = time();
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
                // Re-read before write-back: the $options snapshot from
                // run() start would clobber a concurrent admin save.
                $fresh_options = get_option('grs_settings', array());
                $fresh_options['grs_data_id'] = $data_id;
                update_option('grs_settings', $fresh_options);
            }
        }

        $min_rating = isset($options['grs_min_rating']) ? intval($options['grs_min_rating']) : 1;

        $result = $api->fetch_recent_reviews($data_id, $place_id, $min_rating);

        if (is_wp_error($result)) {
            GRS_Database::log_extraction($place_id, 'failed', 0, $result->get_error_message());
            return self::finish($trigger, 'error', $result->get_error_message(), 0);
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

        // Business identity updates only after the review set actually
        // replaced, so a failed swap cannot desync the two.
        if (!empty($result['place_info'])) {
            self::store_business_info($result['place_info']);
        }

        GRS_Database::log_extraction($place_id, 'success', $saved);
        // A resync requested AFTER this sync began was filed against newer
        // settings than the snapshot just synced; it must survive so the
        // queued one-shot still runs.
        $pending_since = intval(get_option('grs_resync_pending', 0));
        if ($pending_since && $pending_since <= $started_at) {
            delete_option('grs_resync_pending');
        }
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
        if ($trigger === 'manual') {
            $status['last_manual_attempt'] = time();
        }
        $status['trigger'] = $trigger;
        update_option('grs_sync_status', $status, false);
    }

    private static function finish($trigger, $outcome, $message, $saved, $success = false) {
        $status = get_option('grs_sync_status', array());
        if (!isset($status['last_attempt'])) {
            $status['last_attempt'] = time();
            $status['trigger'] = $trigger;
        } elseif ($outcome !== 'skipped' && $outcome !== 'locked' && $outcome !== 'rate_limited') {
            // Guard outcomes keep the prior attempt's trigger label; a cron
            // skip must not relabel an older manual attempt as "(cron)".
            $status['trigger'] = $trigger;
        }
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
     * Re-arm the monthly recurrence if it has been lost. A pending
     * one-shot satisfies wp_next_scheduled() but dies after one firing;
     * the recurring event must exist independently or auto-refresh
     * silently stops. Scanning the whole cron array (instead of
     * wp_get_scheduled_event, which returns only the earliest event)
     * avoids double-scheduling when a one-shot sorts before a live
     * recurring event.
     */
    private static function ensure_recurring_scheduled() {
        if (!function_exists('_get_cron_array')) {
            return;
        }

        $crons = _get_cron_array();
        if (is_array($crons)) {
            foreach ($crons as $hooks) {
                if (empty($hooks['grs_auto_refresh_reviews'])) {
                    continue;
                }
                foreach ($hooks['grs_auto_refresh_reviews'] as $event) {
                    if (!empty($event['schedule'])) {
                        return;
                    }
                }
            }
        }

        wp_schedule_event(time() + DAY_IN_SECONDS, 'grs_monthly', 'grs_auto_refresh_reviews');
    }

    /**
     * Data for the admin status panel. Also self-heals a missing monthly
     * recurrence: admin page views are frequent enough and the check is
     * cheap.
     *
     * @return array {last_success, last_attempt, status, message, reviews_saved, next_cron, api_calls_30d}
     */
    public static function get_status() {
        self::ensure_recurring_scheduled();

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
