# Release v2.7.11 — Critical Stability Fix

## Summary

Fixes a critical failure mode where a single site could exhaust the entire
hosting account's PHP workers and take **all** sites on the account offline
(LiteSpeed `HTTP 408` everywhere).

## Root cause

If the `wp_grs_reviews` table was missing (it is dropped by `uninstall.php`,
which can run during a botched re-install/auto-update), the front-end shortcode
fell into a loop: on **every page load** it queried the missing table, then
attempted an external reviews-API fetch and a bulk save that also failed —
dozens of failed DB queries plus a slow external HTTP call per request. Under
normal visitor/bot traffic this saturated the account CPU/entry-process quota,
and the host throttled every site on the account.

Observed in production: 3,570 "Table doesn't exist" errors in a single day on
one site, with all ~55 sites on the account returning 408.

## Fixes

1. **Self-healing tables** (`includes/database-handler.php`)
   - New `GRS_Database::ensure_tables()` runs one cheap `SHOW TABLES` per request
     and recreates the reviews table if it is missing.
   - Wired into `get_reviews()`, `save_reviews()` and `get_review_stats()`, so an
     existing install recovers automatically on the next page load — no
     re-activation required.

2. **Circuit breaker** (`includes/shortcode.php`)
   - The front-end now attempts an external fetch + bulk save **at most once
     every 15 minutes per place**, even when it fails or returns nothing
     (`grs_fetch_lock_<place>` transient). This bounds load if the table or the
     upstream API is ever unavailable.

3. **Version correction**
   - The plugin version string had been stuck at `2.7.8` through the 2.7.9 and
     2.7.10 releases. It is now correctly `2.7.11` in both the header and
     `GRS_VERSION`.

## Upgrade notes

Safe drop-in update. No schema changes (DB version stays `2.0.0`). Existing
installs self-heal on the next front-end page load after updating.
