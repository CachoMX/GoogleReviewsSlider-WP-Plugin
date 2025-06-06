<?php
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
                <p>Reviews are cached for 24 hours to improve performance and reduce API calls.</p>
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

        <h3>What's New in Version 1.1</h3>
        <ul>
            <li>✅ Improved cache management with manual clear option</li>
            <li>✅ Better error handling and user feedback</li>
            <li>✅ Enhanced admin interface with status indicators</li>
            <li>✅ Performance optimizations</li>
            <li>✅ Better WordPress compatibility</li>
        </ul>
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