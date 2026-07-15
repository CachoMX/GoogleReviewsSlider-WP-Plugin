# Changelog

## 2.8.0 - 2026-07-15

Full rework of the data pipeline. Root-cause fixes for the backend/frontend
desync (BUG 1) and the review ordering bug (BUG 2), plus the monthly-sync and
10-review requirements. The slider's visual design is untouched by request.

### Fixed

- **Backend/frontend desync (BUG 1).** The legacy Google Places pipeline
  (`api-handler.php` + 30-day transients) is gone. It re-seeded the reviews
  table with stale data whenever the table was empty, using a different
  `review_id` formula that made duplicates undetectable. The shortcode now
  reads only from `wp_grs_reviews` - the exact table the admin sees.
- **Frozen review dates.** The slider showed the relative string captured at
  extraction time ("a month ago", forever). Dates now derive from the stored
  timestamp at render time.
- **Order (BUG 2).** Reviews without a parseable `iso_date` used to be
  stamped with `time()` and jumped to the front as fake-newest. They are now
  dropped at ingestion; ordering is `ORDER BY time DESC, id DESC` in SQL.
- **Destructive refresh.** The cron deleted all reviews before calling the
  API; any failure left the site empty. Replacement is now staged
  (`::staging`/`::retiring` rename swap with restore paths): stored reviews
  survive every API failure mode, including on MyISAM.
- **Page caches.** Every data mutation (sync, delete-all, place change,
  min-rating change, upgrade prune) now purges LiteSpeed, WP Rocket, W3TC,
  WP Super Cache, SiteGround, Autoptimize, and fires `grs_reviews_synced`
  for anything else.
- **Nav arrows advanced two slides per click** (Swiper's navigation module
  double-bound with the manual iOS handlers). Missing `default-avatar.png`
  replaced with a shipped SVG; `onerror` no longer loops.
- **Blocking render.** The shortcode made a live Google Places HTTP call
  when a 24-hour transient expired. Removed.
- **Asset caching.** CSS/JS were versioned with `time()`, defeating browser
  and CDN caches. Now versioned by plugin version.
- **Uninstall** dropped a misspelled table name (`grs_extraction_log`) and
  left options behind. Now removes all tables, options, user meta, and cron.
- Dashicons and a nonce-bearing dummy script loaded on every page of the
  site. Assets now load only where the shortcode renders (with a fallback
  for widgets/templates).
- `dbDelta` could never alter existing tables because of
  `CREATE TABLE IF NOT EXISTS`; schema migrations work again.

### Added

- **Sync engine (`GRS_Sync`)** - single entry point for cron and manual
  syncs. Owner-tokened atomic lock in `wp_options`, persisted last-success
  guard (a double-fired cron cannot sync twice a month), durable resync
  flag for place/rating changes, and a one-shot self-heal sync 30 seconds
  after configuration changes.
- **Monthly auto-sync** on a plugin-prefixed `grs_monthly` schedule, with a
  27-day guard so WP-Cron duplicates never double-bill.
- **API spend audit**: every SerpAPI HTTP call is logged to
  `wp_grs_api_log`; the admin panel shows calls made in the last 30 days.
- **Sync status panel**: last/next sync, trigger, result badge
  (ok/error/rate-limited/locked/skipped), reviews stored, and the business
  identity shown to visitors.
- **Manual "Sync Now"** with nonce, capability check, and a server-side
  2-minute rate limit.
- **Upgrade migration** (2.7.11 → 2.8.0): moves business info out of
  `grs_settings`, prunes stored reviews to 10, deletes legacy transients,
  and reschedules the cron.

### Changed

- **`min_rating` now governs everything**: fetch, storage, and render. The
  hardcoded 5-star-only filter is gone.
- **Hard cap of 10 reviews per place** - newest first - enforced at request
  time (max 2 SerpAPI pages), storage, and render.
- API keys are never hardcoded. Resolution: constructor arg →
  `GRS_SERPAPI_KEY` constant (wp-config.php) → saved setting. **Rotate the
  SerpAPI key and the old Outscraper token**: both live in public git
  history.
- Place ID changes are safe: old reviews are kept for instant revert and
  cleaned up only after the new place syncs successfully.
- Swiper pinned to 11.0.0; `arrows` shortcode attribute honored;
  `loop` disabled when all slides fit.

### Removed

- Legacy Google Places review pipeline and its transients.
- Dead Outscraper class (never loaded; fatal-error landmine via
  `class_alias`), Slick carousel assets, `js/script.js`, `css/style.css`.
- Root debug scripts (`check-updates.php`, `force-update-check.php`,
  `cleanup-old-folders.php`, `test-*.php`) and tracked `build/`/`releases/`
  artifacts.
- No-op "Remove Duplicates" feature (the unique key makes those duplicates
  impossible) and the unused `grs_search_place`/`grs_clear_cache`/
  `grs_test_ajax` AJAX handlers.

## 2.7.11 and earlier

See the `RELEASE-v*.md` files and git history.
