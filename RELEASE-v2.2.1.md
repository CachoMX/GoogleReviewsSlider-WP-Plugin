# Release Notes - Version 2.2.1

## 🔴 CRITICAL BUGFIX - Star Ratings Not Saving Correctly

This is a **critical hotfix** that resolves a severe bug where all reviews were being saved with 5 stars regardless of their actual rating.

### 🐛 The Problem

**Severity**: CRITICAL
**Impact**: ALL reviews extracted via Outscraper API

When extracting reviews using the Outscraper API, the plugin was incorrectly mapping the rating field, causing:
- ⛔ Reviews with 1 star → Saved as 5 stars
- ⛔ Reviews with 2 stars → Saved as 5 stars
- ⛔ Reviews with 3 stars → Saved as 5 stars
- ⛔ Reviews with 4 stars → Saved as 5 stars
- ✅ Reviews with 5 stars → Saved correctly

**Root Cause**: The Outscraper API returns ratings in a field called `review_rating`, but the plugin was looking for a field called `rating`. When the field wasn't found, it defaulted to 5 stars.

### ✅ The Solution

**Files Modified**:
1. `includes/outscraper-api.php` (Line 248)
   - Changed field mapping from `$review['rating']` to `$review['review_rating']`
   - Added fallback to check both field names for compatibility

2. `includes/outscraper-api.php` (Lines 42-51)
   - Removed `fields` parameter that was causing API to switch to async mode
   - API now returns complete data by default

### 🧪 Testing

A comprehensive test script (`test-outscraper-mapping.php`) was created to verify the fix:

```
✓ PASS | Mike Spring (3 stars) → Correctly mapped to 3 stars
✓ PASS | Alyson Roberts (1 star) → Correctly mapped to 1 star
✓ PASS | Londyn Henning (2 stars) → Correctly mapped to 2 stars
✓ PASS | Happy Customer (5 stars) → Correctly mapped to 5 stars
```

**All tests passed** ✅

### 📋 What Changed

#### Before v2.2.1 (BROKEN)
```php
'rating' => isset($review['rating']) ? intval($review['rating']) : 5,
// ↑ Always returned 5 because 'rating' field doesn't exist in Outscraper response
```

#### After v2.2.1 (FIXED)
```php
'rating' => isset($review['review_rating']) ? intval($review['review_rating']) :
           (isset($review['rating']) ? intval($review['rating']) : 5),
// ↑ Now checks 'review_rating' FIRST, then falls back to 'rating', then 5
```

### 🔄 What You Need to Do

**If you already extracted reviews with v2.2.0 or earlier:**

1. **Delete existing reviews** in the WordPress admin panel (Google Reviews → Reviews Management)
2. **Re-extract reviews** using the "Extract Reviews" button
3. Reviews will now be saved with their **correct star ratings**

**For new installations:**
- No action needed - reviews will extract correctly from the start

### 📊 Impact Analysis

**Who is affected:**
- ✅ Anyone using Outscraper API to extract reviews
- ❌ NOT affected: Reviews from Google Places API (those were already working)

**Data integrity:**
- Reviews text, author names, dates, photos → All saved correctly ✅
- Review ratings → Were INCORRECT in v2.2.0 and earlier ❌
- Review ratings → Now CORRECT in v2.2.1 ✅

### 🚀 Deployment

This hotfix will be automatically distributed to all **200+ installations** via the GitHub auto-update system.

### 📝 Technical Details

**Outscraper API Response Structure:**
```json
{
  "reviews_data": [
    {
      "author_title": "Mike Spring",
      "review_rating": 3,  ← The actual field name
      "review_text": "...",
      "review_timestamp": 1767965000
    }
  ]
}
```

**Our Mapping (Now Correct):**
```php
$processed_review = array(
    'author_name' => $review['author_title'],  // ✓ Correct
    'rating' => $review['review_rating'],       // ✓ NOW CORRECT!
    'text' => $review['review_text'],           // ✓ Correct
    'time' => $review['review_timestamp']       // ✓ Correct
);
```

### 🔍 How We Found This

User reported seeing reviews in Google Maps with different star ratings than what appeared in the plugin:
- Google Maps: "Mike Spring - 3 stars"
- Plugin (v2.2.0): "Mike Spring - 5 stars" ❌
- Plugin (v2.2.1): "Mike Spring - 3 stars" ✅

After testing with the actual Outscraper API:
```bash
curl "https://api.app.outscraper.com/maps/reviews-v3?query=ChIJ...&reviewsLimit=3&async=false"
```

We discovered the field name mismatch and corrected it.

### 📦 Version Comparison

| Version | Star Ratings | Status |
|---------|-------------|--------|
| 2.2.0 and earlier | ❌ BROKEN | All saved as 5 stars |
| 2.2.1 | ✅ FIXED | Saved with actual ratings |

### 🙏 Acknowledgments

Special thanks to the user who reported this issue with specific examples from their veterinary hospital's Google reviews. This detailed bug report made it possible to quickly identify and fix the problem.

### 📞 Support

If you continue to see incorrect star ratings after:
1. Updating to v2.2.1
2. Deleting old reviews
3. Re-extracting reviews

Please contact support with:
- Your Place ID
- Screenshots comparing Google Maps vs Plugin
- Extraction history from admin panel

---

## Full Changelog

```
v2.2.1 (2026-01-25) - CRITICAL HOTFIX
- Fixed: Star ratings now map correctly from Outscraper API (review_rating field)
- Fixed: Removed 'fields' parameter that caused async mode issues
- Added: Test script to verify rating mapping (test-outscraper-mapping.php)
- Improved: Better field name fallback for API compatibility
- Improved: API response logging for debugging
```

---

**UPGRADE IMMEDIATELY** - This is a critical data integrity fix.

**Made with ❤️ by [Carlos Aragon](https://carlosaragon.online)**
