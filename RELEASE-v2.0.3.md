# Release v2.0.3 - Critical Update Fix

## Instructions to Create Release on GitHub

1. **Go to:** https://github.com/CachoMX/GoogleReviewsSlider-WP-Plugin/releases/new

2. **Fill in these exact values:**

---

### Tag
```
v2.0.3
```

### Release Title
```
Version 2.0.3 - Critical Update Fix
```

### Description (Copy everything below)
```markdown
## 🔧 Critical Fix - Update Installation

This version **fixes the update installation bug** where the plugin would deactivate after updating.

## ✅ What's Fixed
- **Update Installation**: Plugin now updates correctly without deactivating
- **GitHub Structure**: Properly handles GitHub's ZIP file structure
- **File Movement**: Correctly moves files to plugin directory
- **Cleanup**: Removes temporary files after successful update

## ⚠️ Important
**This is a critical fix.** Version 2.0.2 had an installation bug that caused the plugin to deactivate after update. Please update to 2.0.3 immediately.

## 🧪 Testing
After updating to this version:
1. Plugin will remain active ✅
2. All files will be in the correct location ✅
3. No manual reactivation needed ✅

## 📋 Technical Details
- Rewrote `after_install()` hook to handle GitHub zipball structure
- Added proper directory cleanup
- Improved error handling during update process

---

**Update from:** Any version
**Recommended:** Yes - Critical fix
```

---

3. **Leave these as default:**
   - ✅ Set as the latest release
   - ⬜ Set as a pre-release
   - ⬜ Create a discussion for this release

4. **Click:** "Publish release"

---

## After Creating the Release

Test on your live site:

1. Go to: `http://yoursite.com/wp-content/plugins/google-reviews-slider/check-updates.php`
2. Should show: "Update Available: 2.0.3"
3. Activate the plugin (that got deactivated)
4. Update to 2.0.3
5. This time it should stay activated! ✅

---

**DELETE THIS FILE after creating the release**
