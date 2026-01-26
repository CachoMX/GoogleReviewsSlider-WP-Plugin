# Release Notes - Version 2.2.4

## 🔧 Auto-Updater FINAL FIX + Mobile Debug Tools

This release **completely fixes** the auto-updater folder naming issue and adds powerful mobile debugging tools.

### 🎯 The REAL Auto-Updater Fix

**Previous attempts (v2.2.3) didn't work** because GitHub always adds version numbers to folder names when you download from the archive URL.

**The REAL Solution**: Upload a properly-built ZIP file as a **release asset** instead of using GitHub's automatic archive.

#### How It Works Now:

**OLD (Broken)**:
```
https://github.com/.../archive/refs/tags/v2.2.3.zip
  ↓ Downloads:
GoogleReviewsSlider-WP-Plugin-2.2.3/  ← VERSION IN NAME!
```

**NEW (Fixed)**:
```
Build script creates: google-reviews-slider-2.2.4.zip
  Contains:
GoogleReviewsSlider-WP-Plugin/  ← NO VERSION!
  ├── google-reviews-slider.php
  ├── css/
  └── ...

Upload to GitHub Release as asset
Updater downloads the asset ZIP
WordPress extracts: GoogleReviewsSlider-WP-Plugin/
Updates in place ✓
```

### 📦 New Build System

Created automated build scripts that generate properly structured ZIPs:

**PowerShell (Windows)**: `build-release.ps1`
```powershell
.\build-release.ps1 2.2.4
```

**Bash (Linux/Mac)**: `build-release.sh`
```bash
./build-release.sh 2.2.4
```

**What the build script does**:
1. Creates `build/GoogleReviewsSlider-WP-Plugin/` folder
2. Copies all plugin files (excludes dev files)
3. Creates ZIP from `build/` directory
4. ZIP structure: `GoogleReviewsSlider-WP-Plugin/` (consistent name!)
5. Saves to `releases/google-reviews-slider-{version}.zip`

### 🔄 Updated Auto-Updater Logic

**File**: `includes/plugin-updater.php` (lines 158-178)

```php
// NEW: Look for uploaded ZIP asset first
if (isset($data['assets']) && is_array($data['assets'])) {
    foreach ($data['assets'] as $asset) {
        if (strpos($asset['name'], '.zip') !== false) {
            $download_url = $asset['browser_download_url'];
            break;
        }
    }
}

// Fallback to archive URL if no asset
if (empty($download_url)) {
    $download_url = "...archive/refs/tags/...";
}
```

**Result**: Updater downloads the properly-built ZIP with correct folder structure!

### 🐛 Mobile Debug Tools

Added comprehensive debugging system for troubleshooting mobile issues:

**File**: `js/mobile-debug.js`

**Features**:
- Floating debug console at bottom of screen
- Shows device info (iOS/Android, screen size, touch support)
- Monitors slider initialization
- Tracks autoplay events
- Detects arrow clicks and touch events
- Real-time logging
- Toggle button to show/hide

**How to Enable**:

Option 1: Add `?grs_debug=1` to URL
```
https://yoursite.com/page-with-slider/?grs_debug=1
```

Option 2: Enable WP_DEBUG in wp-config.php
```php
define('WP_DEBUG', true);
```

**What You'll See**:
```
[09:30:15] GRS Mobile Debug Console Initialized
[09:30:15] === DEVICE INFO ===
[09:30:15] iOS: true
[09:30:15] Android: false
[09:30:15] Mobile: true
[09:30:15] Touch: true
[09:30:15] Screen: 390x844
[09:30:15] Window: 390x664
[09:30:15] ==================
[09:30:17] Found 1 slider(s)
[09:30:17] Slider #0 has slick: true
[09:30:17] Prev arrow found: 1
[09:30:17] Next arrow found: 1
[09:30:17] Prev arrow visible: true
[09:30:17] Next arrow visible: true
[09:30:20] PREV ARROW CLICKED! Event: touchend
[09:30:21] Slide changing: 0 → 2
[09:30:22] Slide changed to: 2
```

**Debug API**:
```javascript
// Access debug info in browser console
window.GRS_DEBUG.deviceInfo  // Device information
window.GRS_DEBUG.messages     // Array of debug messages
window.GRS_DEBUG.getLog()     // Get all messages as text
window.GRS_DEBUG.clear()      // Clear debug console
```

### 📋 Changes Summary

| Component | Changes |
|-----------|---------|
| **Build System** | New PowerShell & Bash scripts to create proper ZIPs |
| **Auto-Updater** | Now checks for release assets before falling back to archive |
| **Mobile Debug** | Comprehensive debugging system with visual console |
| **Folder Structure** | ZIP now contains `GoogleReviewsSlider-WP-Plugin/` (no version!) |

### 🚀 Deployment Process (For Maintainers)

1. **Update version** in `google-reviews-slider.php`
2. **Run build script**:
   ```powershell
   .\build-release.ps1 2.2.4
   ```
3. **Verify ZIP structure**:
   - Should contain: `GoogleReviewsSlider-WP-Plugin/`
   - Should NOT contain version in folder name
4. **Commit changes**
5. **Create GitHub release** v2.2.4
6. **Upload ZIP** from `releases/` folder as release asset
7. **Auto-updates will use the uploaded ZIP** ✓

### 🎯 For Users - How To Update

**Automatic (Recommended)**:
1. WordPress will detect update
2. Click "Update Now"
3. Plugin updates in place (no new folder!)
4. Done ✓

**Manual**:
1. Download `google-reviews-slider-2.2.4.zip` from GitHub release
2. Upload via WordPress admin
3. Replaces existing plugin
4. Activate

### 🐛 Troubleshooting Mobile Issues

If slider doesn't work on your phone:

1. **Enable debug mode**:
   ```
   https://yoursite.com/page/?grs_debug=1
   ```

2. **Check debug console** (bottom of screen)
   - Green text = info
   - Yellow text = warnings/events
   - Red text = errors

3. **Look for**:
   - "Slider #0 has slick: true" ← Should be true
   - "Prev/Next arrow visible: true" ← Should be true
   - "ARROW CLICKED" messages when tapping ← Should appear

4. **Common issues**:
   - Slick not initialized → Check if jQuery loaded
   - Arrows not visible → Check CSS
   - Arrows don't respond → Check touch event handlers
   - Autoplay not working → Check iOS restrictions

5. **Share debug log**:
   ```javascript
   // In browser console:
   console.log(window.GRS_DEBUG.getLog());
   // Copy and share
   ```

### 🆚 Version Comparison

| Version | Auto-Update Works | Folder Structure | Mobile Debug |
|---------|------------------|------------------|--------------|
| ≤ 2.2.2 | ❌ Creates duplicates | Random | ❌ No |
| 2.2.3 | ❌ Still broken | Still has version | ❌ No |
| 2.2.4 | ✅ **WORKS!** | ✅ Consistent | ✅ Yes! |

### 📁 File Manifest

**New Files**:
- `build-release.ps1` - PowerShell build script
- `build-release.sh` - Bash build script
- `build-release.bat` - Windows batch script (deprecated, use PS1)
- `build-exclude.txt` - Files to exclude from build
- `js/mobile-debug.js` - Mobile debugging tools
- `RELEASE-v2.2.4.md` - This file

**Modified Files**:
- `google-reviews-slider.php` - Version 2.2.3 → 2.2.4, debug mode support
- `includes/plugin-updater.php` - Check for release assets first

**Directory Structure**:
```
google-reviews-slider-wp-plugin/
├── build/                          ← Build output (gitignored)
├── releases/                       ← Built ZIPs (gitignored)
│   └── google-reviews-slider-2.2.4.zip
├── build-release.ps1               ← Build script (PowerShell)
├── build-release.sh                ← Build script (Bash)
├── build-exclude.txt               ← Exclusion list
└── js/mobile-debug.js              ← Debug tools
```

### ⚠️ Important Notes

**For Users**:
- After updating to 2.2.4, you may still have old folders from previous updates
- Use the cleanup script (`cleanup-old-folders.php`) to remove them
- Or manually delete old folders via FTP/cPanel
- Future updates will work correctly!

**For Developers**:
- Always use build scripts to create releases
- Never use GitHub's automatic "Source code (zip)" download
- Always upload the built ZIP as a release asset
- Test the ZIP structure before releasing

### 🎉 What This Means

- ✅ Auto-updates finally work correctly
- ✅ No more duplicate plugin folders
- ✅ Easy mobile troubleshooting with debug tools
- ✅ Professional build/release process
- ✅ Consistent folder naming forever

---

## Full Changelog

```
v2.2.4 (2026-01-25) - Auto-Updater FINAL FIX + Mobile Debug
- Fixed: Auto-updater now uses release asset ZIPs with correct folder structure
- Added: Build scripts (PowerShell, Bash) to generate proper release ZIPs
- Added: Mobile debug system with visual console and comprehensive logging
- Added: Debug mode activation via ?grs_debug=1 or WP_DEBUG
- Improved: Updater checks for uploaded ZIP assets before falling back to archive
- Improved: ZIP structure now consistent: GoogleReviewsSlider-WP-Plugin/ (no version!)
- Result: Auto-updates work correctly, mobile issues can be debugged easily
```

---

**Auto-updates FINALLY fixed! + Powerful mobile debugging!** 🎉🔧

**Made with ❤️ by [Carlos Aragon](https://carlosaragon.online)**
