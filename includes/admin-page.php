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
    register_setting('pluginPage', 'grs_settings', array(
        'sanitize_callback' => 'grs_sanitize_settings',
    ));

    add_settings_section(
        'grs_pluginPage_section',
        __('Settings', 'google-reviews-slider'),
        'grs_settings_section_callback',
        'pluginPage'
    );

    add_settings_field(
        'grs_api_key',
        __('Google API Key', 'google-reviews-slider'),
        'grs_api_key_render',
        'pluginPage',
        'grs_pluginPage_section'
    );

    add_settings_field(
        'grs_place_id',
        __('Google Place ID', 'google-reviews-slider'),
        'grs_place_id_render',
        'pluginPage',
        'grs_pluginPage_section'
    );

    add_settings_field(
        'grs_min_rating',
        __('Minimum Rating', 'google-reviews-slider'),
        'grs_min_rating_render',
        'pluginPage',
        'grs_pluginPage_section'
    );

    add_settings_field(
        'grs_serpapi_key',
        __('SerpAPI Key', 'google-reviews-slider'),
        'grs_serpapi_key_render',
        'pluginPage',
        'grs_pluginPage_section'
    );

    add_settings_field(
        'grs_data_id',
        __('SerpAPI Data ID', 'google-reviews-slider'),
        'grs_data_id_render',
        'pluginPage',
        'grs_pluginPage_section'
    );
}
add_action('admin_init', 'grs_settings_init');

/**
 * Sanitize the settings array and reconcile place changes: a new Place ID
 * invalidates the derived data_id and the stored business identity, and
 * queues a near-immediate sync. Until that sync completes, visitors may
 * briefly see no slider for the new place; the old place's rows are kept
 * only so reverting to it is instant.
 */
function grs_sanitize_settings($input) {
    $old = get_option('grs_settings', array());

    $clean = array(
        'grs_api_key' => isset($input['grs_api_key']) ? sanitize_text_field($input['grs_api_key']) : '',
        'grs_place_id' => isset($input['grs_place_id']) ? sanitize_text_field($input['grs_place_id']) : '',
        'grs_min_rating' => isset($input['grs_min_rating']) ? (string) max(1, min(5, intval($input['grs_min_rating']))) : '1',
        'grs_serpapi_key' => isset($input['grs_serpapi_key']) ? sanitize_text_field($input['grs_serpapi_key']) : '',
        'grs_data_id' => isset($input['grs_data_id']) ? sanitize_text_field($input['grs_data_id']) : '',
    );

    // '::' is reserved for the review swap's synthetic place_ids
    // (see GRS_Database::replace_reviews); real Google ids never contain it.
    $clean['grs_place_id'] = str_replace('::', '', $clean['grs_place_id']);
    $clean['grs_data_id'] = str_replace('::', '', $clean['grs_data_id']);

    $old_place = isset($old['grs_place_id']) ? $old['grs_place_id'] : '';

    if ($old_place !== '' && $clean['grs_place_id'] !== $old_place) {
        if (!empty($old['grs_data_id']) && $clean['grs_data_id'] === $old['grs_data_id']) {
            $clean['grs_data_id'] = '';
        }
        delete_option('grs_business_info');

        // Cached pages keep serving the old place's reviews even if the
        // next sync fails, so they must die at place-change time.
        require_once(GRS_PLUGIN_PATH . 'includes/sync-handler.php');
        GRS_Sync::purge_page_caches();
        GRS_Sync::reset_for_new_place();
    }

    $old_rating = isset($old['grs_min_rating']) ? $old['grs_min_rating'] : '1';
    if ($clean['grs_min_rating'] !== $old_rating && $clean['grs_place_id'] !== '') {
        require_once(GRS_PLUGIN_PATH . 'includes/sync-handler.php');
        GRS_Sync::purge_page_caches();
        // Storage only holds reviews >= the old minimum; a re-fetch is
        // needed before a lower minimum can actually show more reviews.
        GRS_Sync::request_resync();
    }

    return $clean;
}

// Add these callback functions
function grs_settings_section_callback() {
    echo esc_html__('Configure your Google Reviews Slider settings below.', 'google-reviews-slider');
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
    <p class="description">Applies to sync and display: only reviews with this rating or higher are fetched, stored and shown.</p>
    <?php
}

function grs_serpapi_key_render() {
    $options = get_option('grs_settings');
    $current_key = isset($options['grs_serpapi_key']) ? $options['grs_serpapi_key'] : '';
    ?>
    <input type='text' name='grs_settings[grs_serpapi_key]' style="width: 400px;"
           value='<?php echo esc_attr($current_key); ?>'>
    <p class="description">SerpAPI key for extracting Google reviews. <a href="https://serpapi.com/manage-api-key" target="_blank">Get your API key</a></p>
    <?php
}

function grs_data_id_render() {
    $options = get_option('grs_settings');
    $current_id = isset($options['grs_data_id']) ? $options['grs_data_id'] : '';
    ?>
    <input type='text' name='grs_settings[grs_data_id]' id='grs_data_id' style="width: 400px; background: #f0f0f0;"
           value='<?php echo esc_attr($current_id); ?>'
           readonly
           placeholder="Auto-generated from Place ID">
    <p class="description">
        <em>Auto-calculated from Place ID.</em> You don't need to edit this field.
    </p>
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
            <span class="grs-version-info">Version <?php echo esc_html(GRS_VERSION); ?></span>
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
            
            <div class="grs-cache-section" style="margin-top: 20px;">
                <h3>🔄 Plugin Updates</h3>
                <p><strong>Current Version:</strong> <?php echo esc_html(GRS_VERSION); ?></p>
                <p>This plugin updates automatically from GitHub. Click below to check for updates immediately.</p>
                <button type="button" id="check-updates-btn" class="button button-primary">
                    <span class="dashicons dashicons-update"></span> Check for Updates Now
                </button>
                <span id="update-message" style="margin-left: 10px;"></span>
                <div id="update-info" style="margin-top: 15px; display: none;"></div>
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
            <h3>🎉 What's New in Version 2.8.0</h3>
            <ul>
                <li>✅ Reliable monthly auto-sync with lock and last-success guard (never double-bills the API)</li>
                <li>✅ Reviews are never deleted unless a fresh set is already stored (API failures keep the slider intact)</li>
                <li>✅ Minimum Rating now applies to fetch, storage and display</li>
                <li>✅ Hard cap of 10 newest reviews per place across request, storage and render</li>
                <li>✅ Review dates on the slider always reflect the real review timestamp</li>
                <li>✅ Page caches are purged automatically after every data change</li>
                <li>✅ Per-request SerpAPI call log for spend auditing</li>
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
    jQuery(document).ready(function($) {
        // Check for updates functionality
        $('#check-updates-btn').on('click', function() {
            var button = $(this);
            var message = $('#update-message');
            var infoDiv = $('#update-info');

            button.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> Checking...');
            message.html('');
            infoDiv.hide();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'grs_check_for_updates',
                    nonce: '<?php echo wp_create_nonce("grs_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;

                        if (data.update_available) {
                            message.html('<span style="color: #00a32a;">✓ Update available: Version ' + data.latest_version + '</span>');
                            infoDiv.html(
                                '<div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; border-radius: 4px;">' +
                                '<h4 style="margin-top: 0;">🎉 New Version Available!</h4>' +
                                '<p><strong>Current:</strong> ' + data.current_version + '</p>' +
                                '<p><strong>Latest:</strong> ' + data.latest_version + '</p>' +
                                '<p><strong>What to do:</strong> The plugin will update automatically in the background. Or you can go to <a href="' + data.plugins_url + '">Plugins page</a> to update now.</p>' +
                                '</div>'
                            ).show();
                        } else {
                            message.html('<span style="color: #00a32a;">✓ You have the latest version!</span>');
                            infoDiv.html(
                                '<div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; border-radius: 4px;">' +
                                '<p><strong>Current Version:</strong> ' + data.current_version + '</p>' +
                                '<p>You are running the latest version of Google Reviews Slider.</p>' +
                                '</div>'
                            ).show();
                        }
                    } else {
                        message.html('<span style="color: #d63638;">✗ Error: ' + $('<span/>').text(response.data || 'Could not check for updates').html() + '</span>');
                    }
                },
                error: function() {
                    message.html('<span style="color: #d63638;">✗ Error checking for updates</span>');
                },
                complete: function() {
                    button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Check for Updates Now');
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

        // Enter would submit the surrounding options.php form mid-edit,
        // saving a half-updated place config.
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

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
        
                const placeIdInput = document.getElementById('grs_place_id');
                if (placeIdInput) {
                    placeIdInput.value = place.place_id;
                }
                // Clear stale Data ID so backend re-derives it
                const dataIdInput = document.getElementById('grs_data_id');
                if (dataIdInput) {
                    dataIdInput.value = '';
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
        wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . urlencode($api_key) . '&libraries=places&callback=initMap', array(), null, true);
    }
}