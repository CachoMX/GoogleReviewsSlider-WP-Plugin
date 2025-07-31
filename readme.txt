=== Google Reviews Slider ===
Contributors: carlosaragon
Tags: google reviews, reviews slider, testimonials, google places, reviews carousel, outscraper, review management
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display Google Reviews in an attractive slider format with advanced review extraction and management capabilities.

== Description ==

Google Reviews Slider allows you to easily display your Google Reviews in a beautiful, responsive slider on your WordPress website. Now with powerful review extraction capabilities to ensure you always have enough reviews to display!

**🚀 NEW in Version 2.0: Advanced Review Extraction**

No more limitations! Extract up to 500 reviews from Google using the integrated Outscraper API. Perfect for businesses that want to showcase only 5-star reviews or need more than the standard 5 reviews from Google's API.

**Key Features:**

* 🎯 **Easy Setup** - Simple configuration with Google Places API
* 📱 **Responsive Design** - Looks great on all devices
* ⚡ **Fast Performance** - Database-cached reviews for optimal loading speed
* 🎨 **Customizable** - Multiple display options and styling
* 🔍 **Smart Filtering** - Show only reviews above a certain rating
* 📊 **Review Summary** - Display overall rating and review count
* 🖼️ **Profile Photos** - Show reviewer profile pictures
* 📅 **Recent Reviews** - Automatically sorted by date

**🆕 New Premium Features (v2.0):**

* 📥 **Extract 10-500 Reviews** - No more 5-review limitation!
* 💾 **Database Storage** - All reviews stored locally for instant access
* 📊 **Review Analytics** - See statistics by rating breakdown
* 🔄 **Bulk Review Management** - Extract and manage hundreds of reviews
* 📈 **Extraction History** - Track all your review extraction activities
* 🎯 **Advanced Filtering** - Filter by rating to always show enough reviews
* 🔌 **Outscraper Integration** - Powerful review extraction API included

== What's New in Version 2.0 ==

* ✅ **Outscraper API Integration** - Extract up to 500 reviews per location
* ✅ **Review Database** - Store all reviews locally for better performance
* ✅ **Review Manager Interface** - View, filter, and manage all your reviews
* ✅ **Statistics Dashboard** - See review counts by rating
* ✅ **Extraction History** - Track when and how many reviews were extracted
* ✅ **Enhanced Filtering** - Ensure enough reviews display even with 5-star filter
* ✅ **API Usage Tracking** - Monitor your Outscraper API usage
* ✅ **Improved Admin UI** - Better organization and user experience

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/google-reviews-slider/` or install through WordPress admin
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to the Google Cloud Console and create a Google Places API key
4. Navigate to 'Google Reviews' in your WordPress admin menu
5. Enter your API key and find your business using the map search
6. **NEW:** Click "Extract Reviews" to fetch 10-500 reviews from Google
7. Use the shortcode `[google_reviews_slider]` on any page or post

== Frequently Asked Questions ==

= Do I need a Google API key? =

Yes, you need a Google Places API key for the initial setup and place search. The plugin provides instructions on how to get one for free.

= What is Outscraper and do I need an API key for it? =

Outscraper is a service that allows extraction of more Google reviews. The plugin comes with a pre-configured API token, so you don't need to set up your own unless you want to use your personal account.

= How many reviews can I extract? =

You can extract between 10 to 500 reviews per location. The default Google API only provides 5 reviews, but with Outscraper integration, you can get much more.

= How often are reviews updated? =

Reviews are stored in your database permanently. You can manually extract new reviews anytime using the "Extract Reviews" button in the admin panel.

= Can I show only 5-star reviews? =

Yes! With the ability to extract up to 500 reviews, you can filter to show only 5-star reviews and still have plenty to display in your slider.

= Is the slider responsive? =

Yes, the slider automatically adjusts to different screen sizes and works perfectly on mobile devices.

= Where are reviews stored? =

Reviews are stored in your WordPress database, ensuring fast loading times and no repeated API calls.

= Can I see review statistics? =

Yes! The new Review Manager shows you total reviews, average rating, and breakdown by star rating.

== Screenshots ==

1. Admin configuration page with map search
2. NEW: Review Manager with statistics dashboard
3. NEW: Review extraction interface with options
4. NEW: Extraction history and review table
5. Reviews slider on frontend with summary box
6. Mobile responsive design
7. Review filtering options

== Changelog ==

= 2.0 =
* Major Update: Outscraper API integration for extracting 10-500 reviews
* Added database storage for all reviews
* New Review Manager interface in admin
* Review statistics dashboard
* Extraction history tracking
* Enhanced filtering to ensure enough reviews display
* API usage monitoring
* Improved review data handling
* Better support for 5-star only displays
* Performance improvements with database caching

= 1.3 =
* Fixed mobile display issues
* Improved Avada theme compatibility
* Enhanced review text visibility
* Better responsive behavior

= 1.2 =
* Fixed "Read More" functionality
* Added visible pagination dots
* Improved navigation arrows
* Better layout handling
* Enhanced responsive design

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

= 2.0 =
Major update! Now extract up to 500 reviews with Outscraper integration. Includes database storage, review manager, and statistics. Highly recommended for all users, especially those wanting to display only 5-star reviews.

== Support ==

For support and feature requests, please visit: https://carlosaragon.online/contact/

== Credits ==

* Uses Slick Carousel for slider functionality
* Google Places API for initial review data
* Outscraper API for advanced review extraction
* WordPress best practices and coding standards

== Privacy Policy ==

This plugin stores Google reviews data in your WordPress database. No personal data is sent to external servers except for:
- Google Places API calls (for place search and initial reviews)
- Outscraper API calls (for extended review extraction)

All data is stored locally on your WordPress installation.