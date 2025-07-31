<?php
// Enqueue admin styles
add_action('admin_enqueue_scripts', 'grs_admin_enqueue_scripts');
function grs_admin_enqueue_scripts($hook) {
    // Only load on our admin page
    if ($hook !== 'toplevel_page_google_reviews_slider') {
        return;
    }
    
    wp_enqueue_style('grs-admin-styles', GRS_PLUGIN_URL . 'css/admin-styles.css', array(), GRS_VERSION);
}

function grs_add_admin_menu() {
    add_menu_page(
        'Google Reviews Slider', // Page title
        'Google Reviews', // Menu title
        'manage_options', // Capability
        'google_reviews_slider', // Menu slug
        'grs_options_page', // Function to output the page content
        'dashicons-star-filled', // Icon
        30 // Position
    );
}
add_action('admin_menu', 'grs_add_admin_menu');

// Register settings
function grs_settings_init() {
    register_setting('pluginPage', 'grs_settings');

    add_settings_section(
        'grs_pluginPage_section', 
        __('Settings', 'grs'), 
        'grs_settings_section_callback', 
        'pluginPage'
    );

    add_settings_field(
        'grs_api_key', 
        __('Google API Key', 'grs'), 
        'grs_api_key_render', 
        'pluginPage', 
        'grs_pluginPage_section'
    );

    add_settings_field(
        'grs_place_id', 
        __('Google Place ID', 'grs'), 
        'grs_place_id_render', 
        'pluginPage', 
        'grs_pluginPage_section'
    );

    add_settings_field(
        'grs_min_rating', 
        __('Minimum Rating', 'grs'), 
        'grs_min_rating_render', 
        'pluginPage', 
        'grs_pluginPage_section'
    );

    add_settings_field(
        'grs_outscraper_token', 
        __('Outscraper API Token', 'grs'), 
        'grs_outscraper_token_render', 
        'pluginPage', 
        'grs_pluginPage_section'
    );
}
add_action('admin_init', 'grs_settings_init');

// Add these callback functions
function grs_settings_section_callback() {
    echo __('Configure your Google Reviews Slider settings below.', 'grs');
}

function grs_api_key_render() {
    $options = get_option('grs_settings');
    ?>
    <input type='text' name='grs_settings[grs_api_key]' style="width: 400px;" 
           value='<?php echo isset($options['grs_api_key']) ? esc_attr($options['grs_api_key']) : ''; ?>'>
    <p class="description">Enter your Google Places API key. <a href="https://console.cloud.google.com" target="_blank">Get API Key</a></p>
    <?php
}

function grs_place_id_render() {
    $options = get_option('grs_settings');
    ?>
    <input type='text' 
           name='grs_settings[grs_place_id]' 
           id='grs_place_id'
           style="width: 400px;" 
           value='<?php echo isset($options['grs_place_id']) ? esc_attr($options['grs_place_id']) : ''; ?>'>
    <p class="description">Use the map below to find and select your business location.</p>
    <?php
}

function grs_min_rating_render() {
    $options = get_option('grs_settings');
    $current = isset($options['grs_min_rating']) ? $options['grs_min_rating'] : '1';
    ?>
    <select name='grs_settings[grs_min_rating]'>
        <option value='1' <?php selected($current, '1'); ?>>1 Star and above</option>
        <option value='2' <?php selected($current, '2'); ?>>2 Stars and above</option>
        <option value='3' <?php selected($current, '3'); ?>>3 Stars and above</option>
        <option value='4' <?php selected($current, '4'); ?>>4 Stars and above</option>
        <option value='5' <?php selected($current, '5'); ?>>5 Stars only</option>
    </select>
    <p class="description">Only show reviews with this rating or higher.</p>
    <?php
}

function grs_outscraper_token_render() {
    $options = get_option('grs_settings');
    $default_token = 'ODJhYTBmZjFkMmY5NGQ1Nzk0MGYwZmI0Y2JhMWZhYWZ8ODhmZDYxYmI3Yg';
    $current_token = isset($options['grs_outscraper_token']) ? $options['grs_outscraper_token'] : $default_token;
    ?>
    <input type='text' name='grs_settings[grs_outscraper_token]' style="width: 400px;" 
           value='<?php echo esc_attr($current_token); ?>'>
    <p class="description">Outscraper API token for extracting more reviews. Default token is pre-filled.</p>
    <?php
}

function grs_options_page() {
    $options = get_option('grs_settings');
    $api_key = isset($options['grs_api_key']) ? $options['grs_api_key'] : '';
    $cached_reviews = get_transient('grs_reviews');
    $cache_status = $cached_reviews !== false ? 'Active' : 'Empty';
    ?>
    <style>
        .grs-admin-page input[type="text"] {
            width: 400px !important;
        }
        #pac-input {
            width: 300px !important;
        }
        .grs-cache-section {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .grs-version-info {
            float: right;
            color: #666;
            font-size: 12px;
        }
        .grs-changelog {
            background: #e8f4f8;
            border: 1px solid #c3dce8;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .grs-changelog h3 {
            margin-top: 0;
            color: #0073aa;
        }
        .grs-changelog ul {
            margin-bottom: 0;
        }
    </style>
    <div class="wrap">
        <h1>
            Google Reviews Slider
            <span class="grs-version-info">Version <?php echo GRS_VERSION; ?></span>
        </h1>
        
        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) : ?>
            <div class="notice notice-success is-dismissible">
                <p>Settings saved successfully!</p>
            </div>
        <?php endif; ?>
        
        <form action='options.php' method='post'>
            <?php
            settings_fields('pluginPage');
            do_settings_sections('pluginPage');
            ?>
            
            <div class="grs-cache-section">
                <h3>Cache Management</h3>
                <p><strong>Cache Status:</strong> <span id="cache-status"><?php echo $cache_status; ?></span></p>
                <p>Reviews are cached for 1 month to improve performance and reduce API calls.</p>
                <button type="button" id="clear-cache-btn" class="button button-secondary">Clear Cache Now</button>
                <span id="cache-message" style="margin-left: 10px;"></span>
            </div>
            
            <h3>Find Your Place ID</h3>
            <?php if (!$api_key) : ?>
                <div class="notice notice-warning">
                    <p>Please enter your Google API Key first to enable the map search.</p>
                </div>
            <?php else : ?>
                <div class="place-finder">
                    <input id="pac-input" class="controls" type="text" placeholder="Search for your business">
                    <div id="map" style="height: 400px; margin: 20px 0;"></div>
                </div>
            <?php endif; ?>

            <?php submit_button(); ?>
            <?php 
            // Show reviews manager if we have a place ID
            if (!empty($options['grs_place_id'])) : 
                require_once(GRS_PLUGIN_PATH . 'includes/reviews-manager.php');
                GRS_Reviews_Manager::display_interface($options['grs_place_id']);
            endif;
            ?>
        </form>

        <h2>How to Use the Google Reviews Slider</h2>
        <ol>
            <li>
                <strong>Enter your API Key:</strong> This key is required to fetch reviews from Google.
                <ul>
                    <li>Go to the <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a></li>
                    <li>Create a new project or select an existing one</li>
                    <li>Enable the Places API for your project</li>
                    <li>Create credentials (API key)</li>
                    <li>Enter the API key in the field above</li>
                </ul>
            </li>
            <li>
                <strong>Find your Place ID:</strong>
                <ul>
                    <li>Use the map above to search for your business</li>
                    <li>The Place ID will be automatically filled in when you select your business</li>
                </ul>
            </li>
            <li>
                <strong>Add to your page:</strong> Use the shortcode <code>[google_reviews_slider]</code> on any page or post.
            </li>
        </ol>

        <button type="button" onclick="testAPI()" class="button">Test API Connection</button>
        <script>
        function testAPI() {
            jQuery.post(ajaxurl, {
                action: 'grs_test_api',
                nonce: '<?php echo wp_create_nonce("grs_nonce"); ?>'
            }, function(response) {
                console.log('API Test Response:', response);
                alert('Check console for API response');
            });
        }
        </script>

        <div class="grs-changelog">
            <h3>🎉 What's New in Version 2.0</h3>
            <ul>
                <li>✅ <strong>Outscraper Integration</strong> - Extract up to 500 reviews from Google!</li>
                <li>✅ <strong>Review Database</strong> - All reviews stored locally for instant access</li>
                <li>✅ <strong>Review Manager</strong> - View, filter, and manage all your reviews</li>
                <li>✅ <strong>Statistics Dashboard</strong> - See review counts by rating breakdown</li>
                <li>✅ <strong>Extraction History</strong> - Track when and how many reviews were extracted</li>
                <li>✅ <strong>Enhanced Filtering</strong> - Show only 5-star reviews with plenty to display</li>
                <li>✅ <strong>API Usage Tracking</strong> - Monitor your Outscraper API usage</li>
                <li>✅ <strong>Performance Boost</strong> - Database caching for lightning-fast loading</li>
            </ul>
        </div>

        <h3>Previous Updates</h3>
        <details>
            <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">Version 1.3</summary>
            <ul style="margin-top: 10px;">
                <li>✅ Fixed mobile display issues</li>
                <li>✅ Improved Avada theme compatibility</li>
                <li>✅ Enhanced review text visibility</li>
                <li>✅ Better responsive behavior</li>
            </ul>
        </details>
        <details>
            <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">Version 1.2</summary>
            <ul style="margin-top: 10px;">
                <li>✅ Fixed "Read More" functionality</li>
                <li>✅ Added visible pagination dots</li>
                <li>✅ Improved navigation arrows</li>
                <li>✅ Better layout handling</li>
                <li>✅ Enhanced responsive design</li>
            </ul>
        </details>
        <details>
            <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">Version 1.1</summary>
            <ul style="margin-top: 10px;">
                <li>✅ Improved cache management with manual clear option</li>
                <li>✅ Better error handling and user feedback</li>
                <li>✅ Enhanced admin interface with status indicators</li>
                <li>✅ Performance optimizations</li>
                <li>✅ Better WordPress compatibility</li>
            </ul>
        </details>

        <h3>Support</h3>
        <p>Need help? Visit our <a href="https://carlosaragon.online/contact/" target="_blank">support page</a> or check out the <a href="https://wordpress.org/support/plugin/google-reviews-slider/" target="_blank">WordPress forum</a>.</p>
    </div>

    <script>
    // Cache clearing functionality
    jQuery(document).ready(function($) {
        $('#clear-cache-btn').on('click', function() {
            var button = $(this);
            var message = $('#cache-message');
            var status = $('#cache-status');
            
            button.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'grs_clear_cache',
                    nonce: '<?php echo wp_create_nonce("grs_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        message.html('<span style="color: green;">✓ Cache cleared successfully!</span>');
                        status.text('Empty');
                        setTimeout(function() {
                            message.html('');
                        }, 3000);
                    } else {
                        message.html('<span style="color: red;">✗ Error clearing cache</span>');
                    }
                },
                error: function() {
                    message.html('<span style="color: red;">✗ Error clearing cache</span>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Clear Cache Now');
                }
            });
        });
    });

    // Google Maps functionality
    function initMap() {
        if (!document.getElementById("map")) return;
        
        const map = new google.maps.Map(document.getElementById("map"), {
            center: { lat: 37.0902, lng: -95.7129 },
            zoom: 4,
            mapTypeId: "roadmap",
        });

        const input = document.getElementById("pac-input");
        const searchBox = new google.maps.places.SearchBox(input);

        map.addListener("bounds_changed", () => {
            searchBox.setBounds(map.getBounds());
        });

        let markers = [];

        searchBox.addListener("places_changed", () => {
            const places = searchBox.getPlaces();
        
            if (places.length == 0) {
                return;
            }
        
            markers.forEach((marker) => {
                marker.setMap(null);
            });
            markers = [];
        
            const bounds = new google.maps.LatLngBounds();
        
            places.forEach((place) => {
                if (!place.geometry || !place.geometry.location) {
                    console.log("Returned place contains no geometry");
                    return;
                }
        
                const placeIdInput = document.querySelector('input[name="grs_settings[grs_place_id]"]');
                if (placeIdInput) {
                    placeIdInput.value = place.place_id;
                }
        
                markers.push(
                    new google.maps.Marker({
                        map,
                        title: place.name,
                        position: place.geometry.location,
                    })
                );
        
                if (place.geometry.viewport) {
                    bounds.union(place.geometry.viewport);
                } else {
                    bounds.extend(place.geometry.location);
                }
            });
            map.fitBounds(bounds);
        });
    }
    </script>
    <?php
    // Add the Google Maps JavaScript API with Places library
    if ($api_key) {
        wp_enqueue_script('google-maps', "https://maps.googleapis.com/maps/api/js?key={$api_key}&libraries=places&callback=initMap", array(), null, true);
    }
}