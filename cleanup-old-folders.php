<?php
/**
 * Cleanup Script for Old Plugin Folders
 *
 * This script removes old plugin folders created by previous buggy updates
 * Example folders to remove:
 * - GoogleReviewsSlider-WP-Plugin-2.2.2
 * - GoogleReviewsSlider-WP-Plugin-2.2.1
 * - GoogleReviewsSlider-WP-Plugin-2.2.0
 * - GoogleReviewsSlider-WP-Plugin-2.1.9
 *
 * USAGE:
 * 1. Upload this file to: wp-content/plugins/
 * 2. Access via browser: yoursite.com/wp-content/plugins/cleanup-old-folders.php
 * 3. Click "Clean Up Now"
 * 4. Delete this file after cleanup
 *
 * @package GoogleReviewsSlider
 * @since 2.2.3
 */

// Load WordPress
$wp_load_path = dirname(dirname(__DIR__)) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die('ERROR: Cannot find WordPress. Make sure this file is in wp-content/plugins/');
}

require_once($wp_load_path);

// Security check - must be admin
if (!current_user_can('manage_options')) {
    die('ERROR: You must be logged in as an administrator to run this script.');
}

// Get action
$action = isset($_GET['action']) ? $_GET['action'] : '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Google Reviews Slider - Cleanup Old Folders</title>
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
            border-bottom: 2px solid #2271b1;
            padding-bottom: 10px;
        }
        .info {
            background: #f0f6fc;
            border-left: 4px solid #2271b1;
            padding: 15px;
            margin: 20px 0;
        }
        .warning {
            background: #fcf8e3;
            border-left: 4px solid #f0b849;
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
        .folder-list {
            background: #f6f7f7;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            margin: 15px 0;
        }
        .folder-item {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .folder-item:last-child {
            border-bottom: none;
        }
        .button {
            background: #2271b1;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
        }
        .button:hover {
            background: #135e96;
        }
        .button-secondary {
            background: #dcdcde;
            color: #2c3338;
        }
        .button-secondary:hover {
            background: #c3c4c7;
        }
        .button-danger {
            background: #dc3545;
        }
        .button-danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 Google Reviews Slider - Cleanup Old Folders</h1>

        <div class="info">
            <strong>Why do I have multiple plugin folders?</strong><br>
            Previous versions of the auto-updater had a bug that created new folders instead of updating the existing one.
            This script will remove those old folders.
        </div>

        <?php
        // Get list of old folders
        $plugin_dir = WP_PLUGIN_DIR;
        $pattern = 'GoogleReviewsSlider-WP-Plugin-*';
        $folders = glob($plugin_dir . '/' . $pattern, GLOB_ONLYDIR);

        // Get current active plugin
        $active_plugins = get_option('active_plugins', array());
        $current_folder = null;
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'google-reviews-slider') !== false ||
                strpos($plugin, 'GoogleReviewsSlider') !== false) {
                $current_folder = dirname($plugin);
                break;
            }
        }

        if ($action === 'cleanup') {
            echo '<h2>Cleanup Results</h2>';

            if (empty($folders)) {
                echo '<div class="info">No old folders found. Your plugin is clean!</div>';
            } else {
                $deleted = 0;
                $failed = 0;

                foreach ($folders as $folder) {
                    $folder_name = basename($folder);

                    // Don't delete the current active folder
                    if ($current_folder && $folder_name === $current_folder) {
                        echo '<div class="warning">⚠️ Skipped (active): ' . esc_html($folder_name) . '</div>';
                        continue;
                    }

                    // Try to delete
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    WP_Filesystem();
                    global $wp_filesystem;

                    if ($wp_filesystem->delete($folder, true)) {
                        echo '<div class="success">✓ Deleted: ' . esc_html($folder_name) . '</div>';
                        $deleted++;
                    } else {
                        echo '<div class="error">✗ Failed to delete: ' . esc_html($folder_name) . '</div>';
                        $failed++;
                    }
                }

                echo '<div class="info">';
                echo '<strong>Summary:</strong><br>';
                echo 'Deleted: ' . $deleted . ' folder(s)<br>';
                if ($failed > 0) {
                    echo 'Failed: ' . $failed . ' folder(s)<br>';
                }
                echo '</div>';

                if ($deleted > 0) {
                    echo '<div class="success">';
                    echo '<strong>✓ Cleanup Complete!</strong><br>';
                    echo 'You can now delete this cleanup script file.';
                    echo '</div>';
                }
            }

            echo '<a href="?action=scan" class="button button-secondary">Scan Again</a>';
            echo '<a href="' . admin_url('plugins.php') . '" class="button">Go to Plugins</a>';

        } else {
            // Scan mode (default)
            echo '<h2>Scan Results</h2>';

            if (empty($folders)) {
                echo '<div class="success">✓ No old folders found! Your plugin installation is clean.</div>';
            } else {
                echo '<div class="warning">';
                echo '<strong>Found ' . count($folders) . ' old plugin folder(s):</strong>';
                echo '</div>';

                echo '<div class="folder-list">';
                foreach ($folders as $folder) {
                    $folder_name = basename($folder);
                    $is_active = ($current_folder && $folder_name === $current_folder);
                    $status = $is_active ? ' <strong>(ACTIVE - will not be deleted)</strong>' : '';

                    echo '<div class="folder-item">';
                    echo '📁 ' . esc_html($folder_name) . $status;
                    echo '</div>';
                }
                echo '</div>';

                if ($current_folder) {
                    echo '<div class="info">';
                    echo '<strong>Currently Active:</strong> ' . esc_html($current_folder) . '<br>';
                    echo 'This folder will be kept.';
                    echo '</div>';
                }

                echo '<div class="warning">';
                echo '<strong>⚠️ Warning:</strong> This will permanently delete the old folders. Make sure you have a backup!';
                echo '</div>';

                echo '<a href="?action=cleanup" class="button button-danger" onclick="return confirm(\'Are you sure you want to delete ' . count($folders) . ' folder(s)?\');">Clean Up Now</a>';
                echo '<a href="' . admin_url('plugins.php') . '" class="button button-secondary">Cancel</a>';
            }
        }
        ?>

        <div class="info" style="margin-top: 30px;">
            <strong>Note:</strong> After cleanup, future updates will work correctly and won't create duplicate folders.
            The auto-updater has been fixed in version 2.2.3.
        </div>

        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666;">
            <strong>Remember:</strong> Delete this cleanup script file after you're done!<br>
            File location: <code>wp-content/plugins/cleanup-old-folders.php</code>
        </div>
    </div>
</body>
</html>
