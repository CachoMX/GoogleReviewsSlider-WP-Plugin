<?php
/**
 * Force Update Check - Temporary Debug Tool
 *
 * This file helps test and debug the automatic update system.
 * Upload to your WordPress site and access via browser to force an update check.
 *
 * URL: http://yoursite.com/wp-content/plugins/google-reviews-slider/force-update-check.php
 *
 * DELETE THIS FILE AFTER TESTING FOR SECURITY
 */

// Load WordPress - try multiple possible paths
$wp_load_found = false;
$possible_paths = array(
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../../../wp-load.php',
    dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php',
);

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_load_found = true;
        break;
    }
}

if (!$wp_load_found) {
    die('<h1>Error</h1><p>Could not locate wp-load.php. Tried paths:</p><ul><li>' . implode('</li><li>', $possible_paths) . '</li></ul>');
}

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. You must be logged in as an administrator.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Force Update Check - Google Reviews Slider</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
            margin-top: 0;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #0073aa;
            padding: 15px;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
        }
        .code {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            overflow-x: auto;
        }
        button {
            background: #0073aa;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover {
            background: #005a87;
        }
        .version-info {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 10px;
            margin: 10px 0;
        }
        .version-info dt {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Force Update Check</h1>

        <?php
        // Get current plugin info
        $plugin_data = get_plugin_data(plugin_dir_path(__FILE__) . 'google-reviews-slider.php');
        $current_version = defined('GRS_VERSION') ? GRS_VERSION : $plugin_data['Version'];

        echo '<div class="version-info">';
        echo '<dt>Current Version:</dt><dd>' . esc_html($current_version) . '</dd>';
        echo '<dt>Plugin Name:</dt><dd>' . esc_html($plugin_data['Name']) . '</dd>';
        echo '</div>';

        // Action handling
        if (isset($_GET['action'])) {
            if ($_GET['action'] === 'clear_cache') {
                // Clear update cache
                delete_transient('grs_github_release_' . md5('https://api.github.com/repos/CachoMX/GoogleReviewsSlider-WP-Plugin/releases/latest'));
                delete_site_transient('update_plugins');

                echo '<div class="success">✅ Update cache cleared! WordPress will check for updates on next load.</div>';
            }

            if ($_GET['action'] === 'check_now') {
                // Force check GitHub
                $github_api_url = 'https://api.github.com/repos/CachoMX/GoogleReviewsSlider-WP-Plugin/releases/latest';

                $response = wp_remote_get($github_api_url, array(
                    'timeout' => 15,
                    'headers' => array(
                        'Accept' => 'application/vnd.github.v3+json'
                    )
                ));

                if (is_wp_error($response)) {
                    echo '<div class="error">❌ Error connecting to GitHub: ' . esc_html($response->get_error_message()) . '</div>';
                } else {
                    $response_code = wp_remote_retrieve_response_code($response);
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);

                    if ($response_code === 200 && isset($data['tag_name'])) {
                        $latest_version = ltrim($data['tag_name'], 'v');

                        echo '<div class="success">';
                        echo '<h3>✅ Successfully Connected to GitHub!</h3>';
                        echo '<div class="version-info">';
                        echo '<dt>Latest GitHub Release:</dt><dd>' . esc_html($latest_version) . '</dd>';
                        echo '<dt>Current Installed:</dt><dd>' . esc_html($current_version) . '</dd>';
                        echo '<dt>Update Available:</dt><dd>' . (version_compare($current_version, $latest_version, '<') ? '✅ Yes!' : '❌ No') . '</dd>';
                        echo '</div>';

                        if (version_compare($current_version, $latest_version, '<')) {
                            echo '<p><strong>An update is available!</strong> Go to <a href="' . admin_url('plugins.php') . '">Plugins page</a> to update.</p>';
                        } else {
                            echo '<p>You already have the latest version installed.</p>';
                        }
                        echo '</div>';

                        echo '<h3>📦 Release Information:</h3>';
                        echo '<div class="code">';
                        echo '<strong>Tag:</strong> ' . esc_html($data['tag_name']) . '<br>';
                        echo '<strong>Published:</strong> ' . esc_html($data['published_at']) . '<br>';
                        echo '<strong>Download URL:</strong> ' . esc_html($data['zipball_url']) . '<br>';
                        echo '</div>';

                    } else {
                        echo '<div class="error">❌ Invalid response from GitHub API (Code: ' . $response_code . ')</div>';
                        echo '<div class="code"><pre>' . esc_html($body) . '</pre></div>';
                    }
                }
            }

            if ($_GET['action'] === 'debug_info') {
                echo '<h3>🔍 Debug Information</h3>';

                // Check if updater class exists
                echo '<div class="info-box">';
                echo '<strong>Updater Class:</strong> ' . (class_exists('GRS_Plugin_Updater') ? '✅ Loaded' : '❌ Not Found') . '<br>';
                echo '<strong>Plugin File:</strong> ' . plugin_basename(__FILE__) . '<br>';
                echo '<strong>Update Transient:</strong> ';

                $update_plugins = get_site_transient('update_plugins');
                if ($update_plugins && isset($update_plugins->response)) {
                    $plugin_basename = plugin_basename(plugin_dir_path(__FILE__) . 'google-reviews-slider.php');
                    if (isset($update_plugins->response[$plugin_basename])) {
                        echo '✅ Update available!<br>';
                        echo '<div class="code"><pre>' . print_r($update_plugins->response[$plugin_basename], true) . '</pre></div>';
                    } else {
                        echo '❌ No update in transient<br>';
                    }
                } else {
                    echo '❌ No transient data<br>';
                }
                echo '</div>';
            }
        }
        ?>

        <div class="info-box">
            <h3>🎯 Testing Instructions</h3>
            <p>To test the automatic update system, you need to simulate having an older version:</p>
            <ol>
                <li><strong>Temporarily downgrade version:</strong> In <code>google-reviews-slider.php</code>, change line 25 to:
                    <div class="code">define('GRS_VERSION', '2.0');</div>
                </li>
                <li><strong>Clear cache:</strong> Click button below</li>
                <li><strong>Check for updates:</strong> Click "Check Now" button</li>
                <li><strong>Go to Plugins:</strong> You should see update available</li>
                <li><strong>After testing:</strong> Change version back to <code>2.0.1</code></li>
            </ol>
        </div>

        <h3>🛠️ Actions</h3>

        <p>
            <a href="?action=clear_cache"><button>Clear Update Cache</button></a>
            <a href="?action=check_now"><button>Check GitHub Now</button></a>
            <a href="?action=debug_info"><button>Show Debug Info</button></a>
        </p>

        <p>
            <a href="<?php echo admin_url('plugins.php'); ?>"><button>Go to Plugins Page</button></a>
            <a href="<?php echo admin_url('update-core.php'); ?>"><button>Go to Updates Page</button></a>
        </p>

        <div class="info-box">
            <h3>⚠️ Security Warning</h3>
            <p><strong>DELETE THIS FILE after testing!</strong> It should not remain on production servers.</p>
        </div>

        <hr>
        <p style="color: #666; font-size: 12px;">
            This tool is for testing the GitHub auto-update system.<br>
            Current URL: <?php echo esc_html($_SERVER['REQUEST_URI']); ?>
        </p>
    </div>
</body>
</html>
