=== Google Reviews Slider ===
Contributors: carlosaragon
Tags: google reviews, reviews slider, testimonials, google places, reviews carousel
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display Google Reviews in an attractive slider format with easy configuration and responsive design.

== Description ==

Google Reviews Slider allows you to easily display your Google Reviews in a beautiful, responsive slider on your WordPress website. Perfect for showcasing customer testimonials and building trust with potential customers.

**Key Features:**

* 🎯 **Easy Setup** - Simple configuration with Google Places API
* 📱 **Responsive Design** - Looks great on all devices
* ⚡ **Fast Performance** - Cached reviews for optimal loading speed
* 🎨 **Customizable** - Multiple display options and styling
* 🔍 **Smart Filtering** - Show only reviews above a certain rating
* 📊 **Review Summary** - Display overall rating and review count
* 🖼️ **Profile Photos** - Show reviewer profile pictures
* 📅 **Recent Reviews** - Automatically sorted by date

**What's New in Version 1.1:**

* ✅ Improved cache management with manual clear option
* ✅ Better error handling and user feedback
* ✅ Enhanced admin interface with status indicators
* ✅ Performance optimizations
* ✅ Better WordPress compatibility
* ✅ Updated documentation and help text

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/google-reviews-slider/` or install through WordPress admin
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to the Google Cloud Console and create a Google Places API key
4. Navigate to 'Google Reviews' in your WordPress admin menu
5. Enter your API key and find your business using the map search
6. Use the shortcode `[google_reviews_slider]` on any page or post

== Frequently Asked Questions ==

= Do I need a Google API key? =

Yes, you need a Google Places API key to fetch reviews from Google. The plugin provides instructions on how to get one for free.

= How often are reviews updated? =

Reviews are cached for 24 hours to improve performance and reduce API calls. You can manually clear the cache from the admin panel.

= Can I filter which reviews are shown? =

Yes, you can set a minimum rating filter to only show reviews above a certain star rating.

= Is the slider responsive? =

Yes, the slider automatically adjusts to different screen sizes and works perfectly on mobile devices.

= How many reviews will be displayed? =

The Google Places API typically returns the 5 most recent reviews for a location.

== Screenshots ==

1. Admin configuration page with map search
2. Reviews slider on frontend with summary box
3. Mobile responsive design
4. Cache management interface

== Changelog ==

= 1.1 =
* Added cache management with manual clear option
* Improved error handling and user feedback
* Enhanced admin interface with status indicators
* Performance optimizations for faster loading
* Better WordPress compatibility and code standards
* Updated documentation and help text
* Added version tracking and update notifications

= 1.0 =
* Initial release
* Basic Google Reviews slider functionality
* Responsive design
* Configurable minimum rating filter
* Google Places API integration
* Review caching for performance

== Upgrade Notice ==

= 1.1 =
This update includes improved cache management, better error handling, and enhanced admin interface. Recommended for all users.

== Support ==

For support and feature requests, please visit: https://carlosaragon.online/contact/

== Credits ==

* Uses Slick Carousel for slider functionality
* Google Places API for review data
* WordPress best practices and coding standards