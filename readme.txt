=== Google Reviews Slider ===
Contributors: carlosaragon
Tags: google reviews, reviews slider, testimonials, google places, reviews carousel, review management
Requires at least: 5.3
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your newest Google Reviews in a responsive slider, synced automatically once a month via SerpAPI.

== Description ==

Google Reviews Slider shows your business's Google reviews in a responsive Swiper-based slider. Reviews are fetched through SerpAPI, stored in your WordPress database, and refreshed automatically once a month, so your API credits stay predictable and your site never blocks on an external call.

**Key features:**

* Automatic monthly sync with a lock and last-success guard: a double-fired cron can never sync (or bill) twice
* Reviews are never deleted unless a fresh set is already stored - API failures keep your slider intact
* Keeps the 10 newest reviews per location, ordered newest to oldest by the real review date
* Minimum Rating setting applies to fetching, storage, and display
* Manual "Sync Now" button with server-side rate limiting
* Sync status panel: last/next sync, result, reviews stored, and API calls over the last 30 days
* Per-request SerpAPI call log for spend auditing
* Page caches (LiteSpeed, WP Rocket, W3TC, WP Super Cache, SiteGround, Autoptimize) are purged after every data change
* Responsive slider with touch support, pagination, and navigation arrows

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/google-reviews-slider/` or install through WordPress admin
2. Activate the plugin through the 'Plugins' screen
3. Go to 'Google Reviews' in your admin menu
4. Enter your SerpAPI key (or define the `GRS_SERPAPI_KEY` constant in wp-config.php)
5. Optionally enter a Google Places API key to enable the business-finder map, then pick your business
6. Click "Sync Now" to fetch your reviews
7. Add the shortcode `[google_reviews_slider]` to any page or post

== Frequently Asked Questions ==

= Do I need a Google API key? =

Only for the business-finder map that helps you locate your Place ID. Reviews themselves are fetched via SerpAPI.

= Do I need a SerpAPI key? =

Yes. Create one at serpapi.com and paste it in the settings, or define `GRS_SERPAPI_KEY` in wp-config.php to keep it out of the database.

= How many reviews are stored? =

The 10 newest reviews that meet your Minimum Rating and have text. The cap keeps API spend low and the slider fast.

= How often are reviews updated? =

Automatically once every 30 days, or whenever you click "Sync Now" (rate-limited to avoid accidental double spending).

= What happens if the API fails? =

Nothing is deleted. Your existing reviews keep displaying, and the failure is recorded in the sync status panel and extraction history.

= Can I show only 5-star reviews? =

Yes - set Minimum Rating to "5 Stars only". The setting applies to both fetching and display.

= Is the slider responsive? =

Yes. It uses Swiper with per-breakpoint slide counts you can control from the shortcode: `slides_desktop`, `slides_tablet`, `slides_mobile`, plus `autoplay`, `arrows`, `show_summary`, and `min_rating`.

== Shortcode ==

`[google_reviews_slider show_summary="true" autoplay="true" autoplay_speed="4000" slides_desktop="3" slides_tablet="2" slides_mobile="1" arrows="true" min_rating="4"]`

All attributes are optional.

== Changelog ==

= 2.8.0 =
* Fixed the backend/frontend desync: the frontend now reads only from the reviews table, the same data the admin sees
* Review dates on the slider always reflect the real review timestamp instead of a frozen relative string
* Reviews are strictly ordered newest to oldest by review date, enforced in SQL
* Monthly auto-sync with lock and last-success guard; a double-fired cron cannot double-bill
* Hard cap of 10 newest reviews per place across request, storage, and render
* Minimum Rating now governs fetching, storage, and display
* Reviews are never deleted unless a validated fresh set is already stored
* Page caches are purged automatically after every data change
* Per-request SerpAPI call log for spend auditing; sync status panel in admin
* Removed the hardcoded API key from source (rotate your keys - see CHANGELOG.md)
* Removed the legacy Google Places pipeline, dead Outscraper code, and Slick assets
* Fixed navigation arrows advancing two slides per click, the missing default avatar, and the uninstall routine

= 2.7.11 =
* Self-healing database tables and a circuit breaker on the front-end fetch

= 2.0 =
* Review extraction service integration, database storage, review manager, statistics

Older versions: see the RELEASE-v*.md files in the repository.

== Upgrade Notice ==

= 2.8.0 =
Major data-pipeline rework: reliable monthly sync, 10-review cap, real newest-first ordering, and no more stale frontend. Enter your own SerpAPI key after upgrading - the plugin no longer ships one.

== Support ==

For support and feature requests, please visit: https://carlosaragon.online/contact/

== Privacy Policy ==

This plugin stores Google review data in your WordPress database. External calls are limited to:
- SerpAPI (review fetching, at most a few requests per month plus manual syncs)
- Google Maps JavaScript API (admin-only business-finder map, if you provide a key)
- GitHub (plugin update checks)
