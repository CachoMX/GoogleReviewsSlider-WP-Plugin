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
    <?php
}

function grs_min_rating_render() {
    $options = get_option('grs_settings');
    $current = isset($options['grs_min_rating']) ? $options['grs_min_rating'] : '1';
    ?>
    <select name='grs_settings[grs_min_rating]'>
        <option value='1' <?php selected($current, '1'); ?>>1 Star</option>
        <option value='2' <?php selected($current, '2'); ?>>2 Stars</option>
        <option value='3' <?php selected($current, '3'); ?>>3 Stars</option>
        <option value='4' <?php selected($current, '4'); ?>>4 Stars</option>
        <option value='5' <?php selected($current, '5'); ?>>5 Stars</option>
    </select>
    <?php
}

function grs_options_page() {
    $options = get_option('grs_settings');
    $api_key = isset($options['grs_api_key']) ? $options['grs_api_key'] : '';
    ?>
    <style>
        .grs-admin-page input[type="text"] {
            width: 400px !important;
        }
        #pac-input {
            width: 300px !important; /* Keep search box smaller */
        }
    </style>
    <div class="wrap">
        <h2>Google Reviews Slider</h2>
        
        <form action='options.php' method='post'>
            <?php
            settings_fields('pluginPage');
            do_settings_sections('pluginPage');
            ?>
            
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
    </div>

    <script>
    function initMap() {
        const map = new google.maps.Map(document.getElementById("map"), {
            center: { lat: 37.0902, lng: -95.7129 }, // Center of USA
            zoom: 4,
            mapTypeId: "roadmap",
        });

        // Create the search box and link it to the UI element.
        const input = document.getElementById("pac-input");
        const searchBox = new google.maps.places.SearchBox(input);

        // Bias the SearchBox results towards current map's viewport.
        map.addListener("bounds_changed", () => {
            searchBox.setBounds(map.getBounds());
        });

        let markers = [];

        // Listen for the event fired when the user selects a prediction and retrieve
        // more details for that place.
        searchBox.addListener("places_changed", () => {
            const places = searchBox.getPlaces();
        
            if (places.length == 0) {
                return;
            }
        
            // Clear out the old markers.
            markers.forEach((marker) => {
                marker.setMap(null);
            });
            markers = [];
        
            // For each place, get the icon, name and location.
            const bounds = new google.maps.LatLngBounds();
        
            places.forEach((place) => {
                if (!place.geometry || !place.geometry.location) {
                    console.log("Returned place contains no geometry");
                    return;
                }
        
                // Update the place ID field - Adding console.log to debug
                console.log("Place ID:", place.place_id);
                
                // Use the correct selector for the place ID input field
                const placeIdInput = document.querySelector('input[name="grs_settings[grs_place_id]"]');
                if (placeIdInput) {
                    placeIdInput.value = place.place_id;
                    console.log("Updated Place ID field");
                } else {
                    console.log("Could not find Place ID input field");
                }
        
                // Create a marker for each place.
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