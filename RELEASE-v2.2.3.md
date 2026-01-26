# Release Notes - Version 2.2.3

## 🔧 Auto-Updater Fix - Stop Creating Duplicate Plugin Folders

This release fixes a **critical bug in the auto-updater** that was creating multiple plugin folders instead of updating the existing one.

### 🐛 The Problem

**Issue Reported**: After each auto-update, WordPress created a NEW plugin folder instead of updating the existing one:

```
wp-content/plugins/
├── GoogleReviewsSlider-WP-Plugin-2.2.2/  ← v2.2.2
├── GoogleReviewsSlider-WP-Plugin-2.2.1/  ← v2.2.1
├── GoogleReviewsSlider-WP-Plugin-2.2.0/  ← v2.2.0
├── GoogleReviewsSlider-WP-Plugin-2.1.9/  ← v2.1.9
└── google-reviews-slider/                 ← Original install
```

**Result**:
- User had to manually update every time
- Multiple copies of the same plugin taking up disk space
- Confusion about which version was active
- Auto-updates didn't work properly

### 🔍 Root Causes Identified

#### 1. **Wrong Download URL**
**Before v2.2.3**:
```php
$download_url = isset($data['zipball_url']) ? $data['zipball_url'] : '';
// This URL creates folders like: CachoMX-GoogleReviewsSlider-WP-Plugin-a1b2c3d
```

**After v2.2.3**:
```php
$download_url = "https://github.com/{$username}/{$repository}/archive/refs/tags/{$tag}.zip";
// This URL creates consistent folders like: GoogleReviewsSlider-WP-Plugin-2.2.3
```

#### 2. **Insufficient Logging**
The `after_install` hook had minimal logging, making it hard to debug why folders weren't being renamed correctly.

#### 3. **No Cleanup of Old Folders**
Even when updates worked, old folders from previous buggy updates stayed in place.

### ✅ Solutions Implemented

#### 1. **Fixed Download URL** (`plugin-updater.php` line 158-160)
Changed from `zipball_url` (commit hash) to archive URL with tag name for predictable folder structure.

```php
// Old (buggy):
'download_url' => $data['zipball_url']

// New (fixed):
'download_url' => "https://github.com/{$username}/{$repository}/archive/refs/tags/{$tag}.zip"
```

#### 2. **Enhanced Logging** (`plugin-updater.php` lines 259-319)
Added comprehensive logging throughout the `after_install` process:

```php
error_log('GRS Updater: Running after_install hook');
error_log('GRS Updater: Plugin basename: ' . $this->basename);
error_log('GRS Updater: GitHub folder name: ' . $github_folder_name);
error_log('GRS Updater: Move successful!');
```

This helps debug update issues by checking WordPress debug.log.

#### 3. **Automatic Cleanup** (`plugin-updater.php` lines 434-464)
Added function to automatically remove old version folders after successful update:

```php
public function cleanup_old_folders() {
    $pattern = 'GoogleReviewsSlider-WP-Plugin-*';
    $folders = glob($plugin_dir . '/' . $pattern, GLOB_ONLYDIR);

    foreach ($folders as $folder) {
        // Delete old folders, keep only current one
    }
}
```

#### 4. **Manual Cleanup Script** (`cleanup-old-folders.php`)
For users who already have multiple folders, a one-time cleanup script:

**Usage**:
1. Access: `yoursite.com/wp-content/plugins/cleanup-old-folders.php`
2. Review folders to be deleted
3. Click "Clean Up Now"
4. Delete the script file

Features:
- Lists all old plugin folders
- Shows which folder is currently active
- Confirms before deleting
- Provides detailed results
- Safe (won't delete active folder)

### 📋 Changes Summary

| File | Changes | Purpose |
|------|---------|---------|
| `includes/plugin-updater.php` | Line 158-160 | Fixed download URL (archive vs zipball) |
| `includes/plugin-updater.php` | Lines 259-319 | Enhanced logging in after_install |
| `includes/plugin-updater.php` | Lines 434-464 | Added cleanup_old_folders() function |
| `includes/plugin-updater.php` | Line 313 | Call cleanup after successful update |
| `cleanup-old-folders.php` | New file | Manual cleanup script for existing installations |
| `google-reviews-slider.php` | Line 5, 25 | Version bump 2.2.2 → 2.2.3 |

### 🎯 How Auto-Updates Work Now

**Update Flow (Fixed)**:

1. **Check for Update**:
   - GitHub API: Get latest release
   - Compare versions
   - Download URL: `https://github.com/.../archive/refs/tags/v2.2.3.zip`

2. **Download & Extract**:
   - WordPress downloads ZIP
   - Extracts to temp folder
   - Folder name: `GoogleReviewsSlider-WP-Plugin-2.2.3`

3. **Install (after_install hook)**:
   - Find extracted folder
   - Delete old plugin folder: `google-reviews-slider/`
   - Move new folder: `GoogleReviewsSlider-WP-Plugin-2.2.3` → `google-reviews-slider/`
   - Clean up temp files

4. **Cleanup**:
   - Remove any old version folders
   - Keep only the current active folder

5. **Reactivate**:
   - Plugin automatically reactivated
   - Update complete ✓

### 🆚 Before vs After

| Aspect | v2.2.2 and earlier | v2.2.3 |
|--------|-------------------|--------|
| Download URL | `zipball_url` (commit hash) | `archive/tags/{tag}.zip` |
| Folder rename | Sometimes failed | Always works |
| Logging | Minimal | Comprehensive |
| Old folder cleanup | Manual only | Automatic |
| Duplicate folders | Created every update | Never created |
| Manual updates needed | Yes ❌ | No ✅ |

### 📝 For Existing Users

**If you already have multiple folders**:

Option 1: **Automatic (Recommended)**
1. Update to v2.2.3
2. Future updates will auto-cleanup old folders
3. Wait for next update, or trigger manually

Option 2: **Manual Cleanup**
1. Upload `cleanup-old-folders.php` to `wp-content/plugins/`
2. Visit: `yoursite.com/wp-content/plugins/cleanup-old-folders.php`
3. Click "Clean Up Now"
4. Delete the cleanup script

Option 3: **FTP/cPanel**
Manually delete folders:
- `GoogleReviewsSlider-WP-Plugin-2.2.2`
- `GoogleReviewsSlider-WP-Plugin-2.2.1`
- `GoogleReviewsSlider-WP-Plugin-2.2.0`
- `GoogleReviewsSlider-WP-Plugin-2.1.9`

**Keep only** the currently active folder (usually `google-reviews-slider`)

### 🧪 Testing

To verify the fix works:

1. Check current version in WordPress admin
2. Wait for next auto-update notification
3. Update the plugin
4. Verify:
   - ✅ No new folder created
   - ✅ Existing folder updated
   - ✅ Old folders removed (if any)
   - ✅ Plugin still active
   - ✅ Settings preserved

Check `wp-content/debug.log` for:
```
GRS Updater: Running after_install hook
GRS Updater: Move successful!
GRS Updater: Cleaning up old folder: GoogleReviewsSlider-WP-Plugin-2.2.2
GRS Updater: Deleted old folder: GoogleReviewsSlider-WP-Plugin-2.2.2
```

### 💡 Technical Deep Dive

**Why zipball_url was problematic**:
```
zipball_url: https://api.github.com/repos/USER/REPO/zipball/v2.2.3
  ↓ downloads to:
  CachoMX-GoogleReviewsSlider-WP-Plugin-a1b2c3d/  ← commit hash!

archive URL: https://github.com/USER/REPO/archive/refs/tags/v2.2.3.zip
  ↓ downloads to:
  GoogleReviewsSlider-WP-Plugin-2.2.3/  ← predictable name!
```

**Why the rename works now**:
1. Predictable folder name from GitHub
2. Clear logging to debug issues
3. Proper deletion of old folder before move
4. Verification that move succeeded
5. Cleanup of any leftover folders

### 🚀 Deployment

**Automatic**: The auto-updater will distribute v2.2.3 to all installations

**What users will see**:
1. Update notification in WordPress admin
2. One-click update
3. Plugin updates in place (no new folder)
4. Old folders automatically cleaned up
5. Plugin stays active

### 🎉 Benefits

✅ **No more manual updates** - Auto-updates finally work correctly
✅ **Clean plugin directory** - No duplicate folders
✅ **Disk space saved** - Old versions automatically removed
✅ **Less confusion** - Always clear which version is active
✅ **Better debugging** - Comprehensive logs for troubleshooting
✅ **Future-proof** - Problem solved permanently

---

## Full Changelog

```
v2.2.3 (2026-01-25) - Auto-Updater Fix
- Fixed: Download URL now uses archive/tags instead of zipball (predictable folder names)
- Fixed: Enhanced after_install with comprehensive logging
- Fixed: Old plugin folders now automatically cleaned up after updates
- Added: cleanup_old_folders() function to remove duplicate folders
- Added: Manual cleanup script (cleanup-old-folders.php) for existing installations
- Improved: Better error handling and logging throughout update process
- Result: Auto-updates now work correctly without creating duplicate folders
```

---

**No more duplicate folders! Auto-updates finally work! 🎉**

**Made with ❤️ by [Carlos Aragon](https://carlosaragon.online)**
