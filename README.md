# Google Reviews Slider - WordPress Plugin

Display Google Reviews in an attractive, responsive slider on your WordPress website with advanced review extraction and management capabilities.

![Version](https://img.shields.io/badge/version-2.2.2-blue.svg)
![WordPress](https://img.shields.io/badge/wordpress-5.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg)

## 🚀 Features

### Core Features
- **Beautiful Slider Display** - Responsive carousel showing Google reviews with Slick Carousel
- **Advanced Review Extraction** - Extract up to 500 reviews using Outscraper API (vs. 5 from Google API)
- **Database Storage** - All reviews stored locally for instant access and better performance
- **Smart Filtering** - Show only reviews above certain ratings (perfect for displaying 5-star reviews)
- **Review Management** - Admin interface to view, filter, and manage extracted reviews
- **Statistics Dashboard** - See total reviews, average rating, and breakdown by stars
- **Automatic Updates** - Get updates automatically from GitHub to all your installations
- **Mobile Optimized** - Extensive mobile compatibility and responsive design

### Technical Features
- Transient caching for improved performance
- Google Places API integration
- Outscraper API integration for bulk review extraction
- Extraction history tracking
- Duplicate review detection and removal
- API usage monitoring
- Shortcode-based implementation

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Google Places API key (free tier available)
- Outscraper API token (included with plugin)

## 🔧 Installation

### Via WordPress Admin
1. Download the latest release from [GitHub Releases](https://github.com/CachoMX/GoogleReviewsSlider-WP-Plugin/releases)
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Choose the downloaded ZIP file
4. Click "Install Now" and then "Activate"

### Via FTP
1. Download and extract the plugin
2. Upload the `google-reviews-slider` folder to `/wp-content/plugins/`
3. Activate through the WordPress admin Plugins menu

### Via Git (For Development)
```bash
cd wp-content/plugins
git clone https://github.com/CachoMX/GoogleReviewsSlider-WP-Plugin.git google-reviews-slider
```

## ⚙️ Configuration

### 1. Get Google Places API Key
1. Go to [Google Cloud Console](https://console.cloud.google.com)
2. Create a new project or select existing one
3. Enable the "Places API"
4. Create credentials (API key)
5. Copy the API key

### 2. Configure Plugin
1. Go to WordPress Admin → Google Reviews
2. Paste your Google API Key
3. Use the map to search and select your business
4. The Place ID will auto-populate
5. Click "Save Changes"

### 3. Extract Reviews
1. In the admin panel, find "Reviews Management" section
2. Select number of reviews to extract (10-500)
3. Click "Extract Reviews"
4. Wait for extraction to complete
5. Reviews are now stored in your database

### 4. Add to Your Site
Add the shortcode to any page or post:
```
[google_reviews_slider]
```

## 🎨 Shortcode Options

### Basic Usage
```
[google_reviews_slider]
```

### With Options
```
[google_reviews_slider
    show_summary="true"
    min_rating="5"
    autoplay="true"
    autoplay_speed="4000"
    slides_desktop="3"
    slides_tablet="2"
    slides_mobile="1"
    arrows="true"]
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `show_summary` | boolean | `true` | Show/hide the summary box with overall rating |
| `min_rating` | integer | `1` | Minimum star rating to display (1-5) |
| `autoplay` | boolean | `true` | Enable/disable automatic sliding |
| `autoplay_speed` | integer | `4000` | Speed in milliseconds between slides |
| `slides_desktop` | integer | `3` | Number of reviews to show on desktop |
| `slides_tablet` | integer | `2` | Number of reviews to show on tablet |
| `slides_mobile` | integer | `1` | Number of reviews to show on mobile |
| `arrows` | boolean | `true` | Show/hide navigation arrows |

## 🔄 Automatic Updates

This plugin supports automatic updates from GitHub. Once installed:

1. WordPress automatically checks for updates every 12 hours
2. When a new version is released on GitHub, you'll see an update notification
3. Click "Update Now" to install the latest version
4. All 200+ sites can be updated without manual intervention

### For Plugin Developers/Maintainers
See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed instructions on:
- Creating releases
- Version management
- Update workflows
- Troubleshooting

## 📊 Admin Features

### Review Statistics
- Total review count
- Average rating
- Breakdown by star rating (1-5)

### Review Management
- View all extracted reviews
- Filter by rating
- Remove duplicates
- Delete all reviews
- Track extraction history

### API Management
- Check Outscraper API usage
- Monitor extraction success/failures
- View extraction timestamps

## 🗂️ File Structure

```
google-reviews-slider/
├── google-reviews-slider.php     # Main plugin file
├── includes/
│   ├── admin-page.php            # Admin settings page
│   ├── api-handler.php           # Google Places API handler
│   ├── database-handler.php      # Database operations
│   ├── outscraper-api.php        # Outscraper API integration
│   ├── plugin-updater.php        # GitHub auto-updater
│   ├── reviews-manager.php       # Review management interface
│   └── shortcode.php             # Shortcode implementation
├── css/
│   ├── admin-styles.css          # Admin panel styles
│   ├── grs-direct.css            # Frontend slider styles
│   └── style.css                 # General styles
├── js/
│   └── script.js                 # Frontend JavaScript
├── assets/
│   ├── google-logo.svg           # Google logo
│   └── default-avatar.png        # Default profile image
├── readme.txt                     # WordPress.org readme
├── README.md                      # This file
└── DEPLOYMENT.md                  # Deployment guide
```

## 🔌 API Integration

### Google Places API
- Used for: Place search and basic review data
- Rate limit: Varies by plan
- Free tier: Available
- Required: Yes

### Outscraper API
- Used for: Bulk review extraction (10-500 reviews)
- Rate limit: Based on credits
- Free tier: Limited
- Required: Optional (token pre-configured)

## 🎯 Use Cases

### Show Only 5-Star Reviews
```
[google_reviews_slider min_rating="5"]
```

### Auto-rotating Testimonials
```
[google_reviews_slider autoplay="true" autoplay_speed="3000" slides_desktop="2"]
```

### Mobile-Only Single Review
```
[google_reviews_slider slides_desktop="3" slides_mobile="1"]
```

## 🐛 Troubleshooting

### Reviews Not Displaying
1. Check Place ID is configured correctly
2. Ensure reviews have been extracted
3. Clear cache (Settings → Cache Management)
4. Check minimum rating filter

### Extraction Fails
1. Verify Outscraper API token is valid
2. Check API usage limits
3. Review error message in extraction history
4. Try extracting fewer reviews

### Update Not Showing
1. Wait 12 hours for automatic check
2. Or go to Plugins → Check for updates
3. Verify repository is public
4. Check version number matches release tag

### Mobile Display Issues
1. Clear browser cache
2. Check theme CSS conflicts
3. Disable other slider plugins temporarily
4. Review browser console for errors

## 🔒 Security

- Nonce verification on all AJAX requests
- Capability checks for admin actions
- Sanitized user inputs
- Prepared SQL statements
- HTTPS for all API calls

## 🌐 Browser Support

- Chrome (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Edge (latest 2 versions)
- Mobile browsers (iOS Safari, Chrome Mobile)

## 📝 Changelog

### Version 2.2.2 (2026-01-25) - iOS/iPhone Critical Fixes
- 🔴 **CRITICAL FIX**: Arrow buttons now properly aligned/centered on mobile devices
- 🔴 **CRITICAL FIX**: Autoplay now works on real iPhone/iOS devices
- 🔴 **CRITICAL FIX**: Arrow buttons now respond to taps on iPhone/iOS
- **Fixed**: Added `position: absolute` + `top: 50%` + `transform` for mobile arrows
- **Fixed**: iOS-specific autoplay initialization with user interaction trigger
- **Fixed**: Touch event handlers (`touchend`) for iOS arrow buttons
- **Added**: iOS-specific CSS optimizations (`-webkit-tap-highlight`, `pointer-events`)
- **Added**: Better error handling for autoplay with try-catch blocks
- **Improved**: Longer initialization delays for iOS (500ms vs 200ms)
- **Note**: Always test on real iOS device, not just desktop responsive view

### Version 2.2.1 (2026-01-25) - CRITICAL HOTFIX
- 🔴 **CRITICAL FIX**: Star ratings now save correctly from Outscraper API
- **Fixed**: Field mapping changed from `rating` to `review_rating` (Outscraper's actual field name)
- **Fixed**: Removed `fields` parameter that was causing async mode issues
- **Added**: Test script to verify rating mapping (`test-outscraper-mapping.php`)
- **Impact**: ALL reviews were saving as 5 stars - now saves actual ratings (1-5)
- **Action Required**: Re-extract reviews to update existing data with correct ratings

### Version 2.2.0 (2026-01-25)
- **Added**: Navigation arrows now visible on mobile devices for better UX
- **Fixed**: Resolved merge conflicts in multiple files
- **Fixed**: Inconsistent summary box background colors
- **Improved**: Mobile touch targets and arrow styling
- **Verified**: Star rating system working correctly (all ratings preserved, filter only affects display)

### Version 2.0.1 (2024)
- Added automatic update system from GitHub
- Improved deployment workflow
- Enhanced documentation

### Version 2.0.0 (2024)
- Major update: Outscraper API integration
- Database storage for all reviews
- Review Manager interface
- Statistics dashboard
- Extraction history tracking
- Enhanced filtering system
- API usage monitoring

### Version 1.3
- Fixed mobile display issues
- Improved Avada theme compatibility
- Enhanced review text visibility

### Version 1.2
- Fixed "Read More" functionality
- Added visible pagination dots
- Improved navigation arrows

### Version 1.1
- Cache management with manual clear
- Better error handling
- Enhanced admin interface

### Version 1.0
- Initial release

## 👨‍💻 Development

### Setting Up Development Environment
```bash
# Clone repository
git clone https://github.com/CachoMX/GoogleReviewsSlider-WP-Plugin.git

# Navigate to WordPress plugins directory
cd wp-content/plugins/google-reviews-slider

# Make changes
# Test on local WordPress installation
```

### Running in Local WordPress
1. Install Local by Flywheel or similar
2. Clone plugin to `wp-content/plugins/`
3. Activate in WordPress admin
4. Configure API keys
5. Test functionality

## 📄 License

GPL v2 or later

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 💬 Support

- **Issues**: [GitHub Issues](https://github.com/CachoMX/GoogleReviewsSlider-WP-Plugin/issues)
- **Documentation**: [DEPLOYMENT.md](DEPLOYMENT.md)
- **Contact**: [https://carlosaragon.online/contact/](https://carlosaragon.online/contact/)

## 🙏 Credits

- **Slick Carousel**: Ken Wheeler
- **Google Places API**: Google
- **Outscraper API**: Outscraper
- **Author**: Carlos Aragon

## 📊 Stats

- **Active Installations**: 200+
- **WordPress Version**: 5.0+
- **PHP Version**: 7.4+
- **Last Updated**: 2024

---

**Made with ❤️ by [Carlos Aragon](https://carlosaragon.online)**
