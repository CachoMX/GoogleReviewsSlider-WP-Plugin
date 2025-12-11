# Deployment Guide - Auto-Update System

This plugin now supports **automatic updates** from GitHub for all 200+ installations. No need to manually update each site!

## How It Works

The plugin checks GitHub for new releases and automatically notifies all WordPress installations when an update is available. Users can update with one click from their WordPress admin panel.

## Setup Instructions

### 1. Making Your Repository Public (Required)

The auto-update system uses the GitHub API which requires the repository to be public (or you'd need to add authentication tokens to each site).

**Option A: Make Current Repo Public**
1. Go to https://github.com/CachoMX/GoogleReviewsSlider-WP-Plugin/settings
2. Scroll to "Danger Zone"
3. Click "Change visibility" → "Make public"

**Option B: Keep Private with Personal Access Token** (Advanced)
- You'd need to modify `plugin-updater.php` to include a GitHub personal access token
- Not recommended for 200 sites due to security concerns

### 2. Creating a New Release

Every time you want to push an update to all 200 sites:

**Step 1: Update Version Number**
```bash
# Edit google-reviews-slider.php
# Change: define('GRS_VERSION', '2.0.1');
# To: define('GRS_VERSION', '2.0.2');  (or whatever the new version is)
```

**Step 2: Commit and Push Changes**
```bash
git add .
git commit -m "Version 2.0.2 - Description of changes"
git push origin main
```

**Step 3: Create a GitHub Release**

**Via GitHub Web Interface:**
1. Go to https://github.com/CachoMX/GoogleReviewsSlider-WP-Plugin/releases
2. Click "Create a new release"
3. Click "Choose a tag" → Type `v2.0.2` (must match plugin version with 'v' prefix)
4. Click "Create new tag: v2.0.2 on publish"
5. Release title: `Version 2.0.2`
6. Description (this becomes the changelog):
```markdown
## What's New

### Added
- New feature description
- Another feature

### Fixed
- Bug fix description
- Another fix

### Changed
- Changed behavior description
```
7. Click "Publish release"

**Via GitHub CLI (Faster):**
```bash
# Install GitHub CLI if you haven't: https://cli.github.com/

# Create release with tag
gh release create v2.0.2 \
  --title "Version 2.0.2" \
  --notes "## What's New
- Feature 1
- Feature 2
- Bug fixes"
```

**Step 4: Wait for Auto-Updates**
- WordPress sites check for updates every 12 hours automatically
- Users will see update notification in their WordPress admin
- They can click "Update Now" to install

### 3. Testing Before Release

**Test on one site first:**
1. Create a release as `v2.0.2-beta`
2. Install on one test site
3. If working, delete beta and create `v2.0.2` final release

## Workflow Example

```bash
# 1. Make your code changes
# Edit files as needed...

# 2. Update version in main plugin file
# Change GRS_VERSION from '2.0.1' to '2.0.2'

# 3. Commit and push
git add .
git commit -m "Version 2.0.2 - Fixed mobile slider issue"
git push origin main

# 4. Create GitHub release
gh release create v2.0.2 \
  --title "Version 2.0.2 - Mobile Fix" \
  --notes "## Fixed
- Mobile slider display issue
- Arrow navigation on tablets"

# 5. Done! All sites will receive update notification within 12 hours
```

## Version Numbering

Follow semantic versioning:
- **Major (2.0.0)**: Breaking changes
- **Minor (2.1.0)**: New features, backwards compatible
- **Patch (2.0.1)**: Bug fixes

Examples:
- `v2.0.1` - Bug fix
- `v2.1.0` - New feature added
- `v3.0.0` - Major rewrite

## How Sites Get Updates

1. **Automatic Check**: WordPress checks for plugin updates every 12 hours
2. **Notification**: Site admin sees update badge in WordPress dashboard
3. **Update**: Admin clicks "Update Now" button
4. **Download**: WordPress downloads latest release from GitHub
5. **Install**: WordPress installs new version automatically
6. **Done**: Plugin updated, all files replaced

## Force Immediate Update Check

If you want a site to check immediately instead of waiting 12 hours:

1. Go to WordPress admin → Plugins
2. Click "Check for updates" (appears near top of page)
3. Or use this code snippet in functions.php temporarily:
```php
delete_site_transient('update_plugins');
```

## Troubleshooting

### Updates Not Showing
- Verify repository is public
- Check version number in `google-reviews-slider.php` matches release tag
- Release tag must start with 'v' (e.g., `v2.0.2` not `2.0.2`)
- Wait 12 hours or force update check

### Download Fails
- Check GitHub repository is accessible
- Verify release was published (not draft)
- Check WordPress server can access github.com (firewall/security)

### Wrong Version After Update
- Clear WordPress cache
- Deactivate and reactivate plugin
- Check file permissions (WordPress must be able to write to plugin folder)

## Security Notes

- Updates are served over HTTPS from GitHub
- WordPress verifies package integrity
- Only admins can install updates
- No sensitive data transmitted

## Rollback

If an update causes issues:

1. **Via WordPress:**
   - Go to Plugins → Delete plugin
   - Reinstall previous version manually

2. **Via GitHub:**
   - Create new release with old version number incremented
   - E.g., if v2.0.3 is bad, create v2.0.4 with v2.0.2 code

## Monitoring Updates

To see which sites have updated:
- Check each site's plugin page (shows current version)
- Or use a management tool like ManageWP, MainWP, or InfiniteWP

## Advanced: Pre-release Versions

For beta testing:

```bash
# Create pre-release
gh release create v2.1.0-beta \
  --title "Version 2.1.0 Beta" \
  --notes "Test version" \
  --prerelease

# Only shows to sites that opt-in to pre-releases
```

## Files Modified for Auto-Update

- `google-reviews-slider.php` - Added version constants and updater initialization
- `includes/plugin-updater.php` - **NEW** - Handles GitHub API and WordPress update hooks
- `DEPLOYMENT.md` - **NEW** - This guide

## Support

If you have issues with the auto-update system:
1. Check GitHub repository settings
2. Verify version numbers match
3. Test on one site first
4. Check WordPress error logs

---

**Next Steps:**
1. Make repository public (if not already)
2. Create your first release with `v2.0.1`
3. Test on one WordPress installation
4. Once confirmed working, all 200 sites will auto-update!
