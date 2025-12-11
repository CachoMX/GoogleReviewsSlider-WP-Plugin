<?php
/**
 * Force Update Check - Simple Version
 *
 * Upload this file to your WordPress site and access it directly
 * URL: http://yoursite.com/wp-content/plugins/google-reviews-slider/check-updates.php
 *
 * This will force WordPress to check for plugin updates immediately
 */

// Find WordPress root
$wp_root = dirname(dirname(dirname(dirname(__FILE__))));
if (file_exists($wp_root . '/wp-load.php')) {
    require_once($wp_root . '/wp-load.php');
} else {
    die('WordPress not found. Path: ' . $wp_root);
}

// Must be admin
if (!is_user_logged_in() || !current_user_can('update_plugins')) {
    wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
    exit;
}

// Force update check
delete_site_transient('update_plugins');
delete_transient('grs_github_release_' . md5('https://api.github.com/repos/CachoMX/GoogleReviewsSlider-WP-Plugin/releases/latest'));

// Trigger update check
wp_update_plugins();

// Get the update transient
$update_plugins = get_site_transient('update_plugins');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Force Update Check - Google Reviews Slider</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f0f0f1;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.13);
        }
        h1 { color: #1d2327; margin-bottom: 20px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
        .info { background: #cce5ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2271b1;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px 5px 5px 0;
        }
        .btn:hover { background: #135e96; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f5f5f5;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Force Update Check</h1>

        <?php
        $plugin_file = 'google-reviews-slider/google-reviews-slider.php';
        $plugin_data = get_plugin_data(__DIR__ . '/google-reviews-slider.php');
        $current_version = defined('GRS_VERSION') ? GRS_VERSION : $plugin_data['Version'];

        echo '<table>';
        echo '<tr><th>Current Installed Version</th><td><strong>' . esc_html($current_version) . '</strong></td></tr>';
        echo '<tr><th>Plugin File</th><td>' . esc_html($plugin_file) . '</td></tr>';
        echo '</table>';

        // Check if update is available
        if ($update_plugins && isset($update_plugins->response[$plugin_file])) {
            $update = $update_plugins->response[$plugin_file];

            echo '<div class="success">';
            echo '<h2>✅ Update Available!</h2>';
            echo '<table>';
            echo '<tr><th>New Version</th><td><strong>' . esc_html($update->new_version) . '</strong></td></tr>';
            echo '<tr><th>Package URL</th><td>' . esc_html($update->package) . '</td></tr>';
            if (isset($update->tested)) {
                echo '<tr><th>Tested up to</th><td>WordPress ' . esc_html($update->tested) . '</td></tr>';
            }
            echo '</table>';
            echo '</div>';

            // Get update URL
            $update_url = wp_nonce_url(
                self_admin_url('update.php?action=upgrade-plugin&plugin=' . $plugin_file),
                'upgrade-plugin_' . $plugin_file
            );

            echo '<div class="info">';
            echo '<h3>Ready to Update</h3>';
            echo '<p><a href="' . esc_url($update_url) . '" class="btn btn-success">🚀 Update Now to Version ' . esc_html($update->new_version) . '</a></p>';
            echo '</div>';

        } else {
            // Check GitHub directly
            $github_response = wp_remote_get('https://api.github.com/repos/CachoMX/GoogleReviewsSlider-WP-Plugin/releases/latest', array(
                'timeout' => 15,
                'headers' => array('Accept' => 'application/vnd.github.v3+json')
            ));

            if (!is_wp_error($github_response)) {
                $github_data = json_decode(wp_remote_retrieve_body($github_response), true);
                $latest_version = isset($github_data['tag_name']) ? ltrim($github_data['tag_name'], 'v') : null;

                if ($latest_version) {
                    if (version_compare($current_version, $latest_version, '<')) {
                        echo '<div class="warning">';
                        echo '<h3>⚠️ Update Available on GitHub but not in WordPress</h3>';
                        echo '<p>Latest version on GitHub: <strong>' . esc_html($latest_version) . '</strong></p>';
                        echo '<p>Your current version: <strong>' . esc_html($current_version) . '</strong></p>';
                        echo '<p><strong>The updater is not detecting the update yet.</strong></p>';
                        echo '<h4>Troubleshooting:</h4>';
                        echo '<ol>';
                        echo '<li>Wait a few minutes for GitHub API to propagate</li>';
                        echo '<li>Check that the release was published (not draft)</li>';
                        echo '<li>Verify repository is public</li>';
                        echo '<li><a href="?force=1">Click here to force check again</a></li>';
                        echo '</ol>';
                        echo '</div>';

                        echo '<div class="info">';
                        echo '<h4>GitHub Release Info:</h4>';
                        echo '<pre>' . esc_html(print_r($github_data, true)) . '</pre>';
                        echo '</div>';

                    } else {
                        echo '<div class="info">';
                        echo '<h3>✅ You have the latest version</h3>';
                        echo '<p>GitHub latest: <strong>' . esc_html($latest_version) . '</strong></p>';
                        echo '<p>Your version: <strong>' . esc_html($current_version) . '</strong></p>';
                        echo '</div>';
                    }
                }
            } else {
                echo '<div class="error">';
                echo '<h3>❌ Could not connect to GitHub</h3>';
                echo '<p>' . esc_html($github_response->get_error_message()) . '</p>';
                echo '</div>';
            }
        }
        ?>

        <div class="info">
            <h3>Actions</h3>
            <p>
                <a href="?force=1" class="btn">🔄 Force Check Again</a>
                <a href="<?php echo admin_url('plugins.php'); ?>" class="btn">📦 Go to Plugins Page</a>
                <a href="<?php echo admin_url('update-core.php'); ?>" class="btn">🔧 Go to Updates Page</a>
            </p>
        </div>

        <?php if (isset($_GET['debug'])): ?>
        <div class="info">
            <h3>🔍 Debug Information</h3>
            <h4>Update Transient:</h4>
            <pre><?php print_r($update_plugins); ?></pre>

            <h4>Plugin Data:</h4>
            <pre><?php print_r($plugin_data); ?></pre>
        </div>
        <?php endif; ?>

        <div class="warning">
            <p><strong>⚠️ DELETE THIS FILE after testing!</strong> Don't leave it on production servers.</p>
            <p>File location: <code><?php echo __FILE__; ?></code></p>
        </div>
    </div>
</body>
</html>
