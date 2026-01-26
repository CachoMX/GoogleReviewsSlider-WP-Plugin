<?php
/**
 * GitHub-based Plugin Updater for Google Reviews Slider
 *
 * This class checks GitHub for new releases and provides automatic updates
 *
 * @package GoogleReviewsSlider
 * @since 2.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class GRS_Plugin_Updater {

    /**
     * GitHub username
     */
    private $username;

    /**
     * GitHub repository name
     */
    private $repository;

    /**
     * Plugin slug
     */
    private $slug;

    /**
     * Plugin basename
     */
    private $basename;

    /**
     * Current plugin version
     */
    private $version;

    /**
     * GitHub API URL
     */
    private $github_api_url;

    /**
     * Constructor
     *
     * @param string $username GitHub username
     * @param string $repository GitHub repository name
     * @param string $plugin_file Main plugin file path
     * @param string $version Current plugin version
     */
    public function __construct($username, $repository, $plugin_file, $version) {
        $this->username = $username;
        $this->repository = $repository;
        $this->slug = plugin_basename(dirname($plugin_file));
        $this->basename = plugin_basename($plugin_file);
        $this->version = $version;
        $this->github_api_url = "https://api.github.com/repos/{$username}/{$repository}/releases/latest";

        // Hook into WordPress update system
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);

        // Add custom update message
        add_action('in_plugin_update_message-' . $this->basename, array($this, 'update_message'), 10, 2);

        // Enable automatic background updates
        add_filter('auto_update_plugin', array($this, 'enable_auto_update'), 10, 2);

        // Save and restore activation state during updates
        add_filter('upgrader_pre_install', array($this, 'save_activation_state'), 10, 2);
        add_action('upgrader_process_complete', array($this, 'restore_activation_state'), 10, 2);
    }

    /**
     * Check for plugin updates
     *
     * @param object $transient Update transient
     * @return object Modified transient
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get latest release from GitHub
        $release_info = $this->get_latest_release();

        if ($release_info === false) {
            return $transient;
        }

        // Check if there's a newer version
        if (version_compare($this->version, $release_info['version'], '<')) {
            $plugin = array(
                'slug' => $this->slug,
                'plugin' => $this->basename,
                'new_version' => $release_info['version'],
                'url' => "https://github.com/{$this->username}/{$this->repository}",
                'package' => $release_info['download_url'],
                'tested' => $release_info['tested_up_to'],
                'requires' => $release_info['requires_at_least'],
                'requires_php' => $release_info['requires_php']
            );

            $transient->response[$this->basename] = (object) $plugin;
        }

        return $transient;
    }

    /**
     * Get latest release information from GitHub
     *
     * @return array|false Release information or false on failure
     */
    private function get_latest_release() {
        // Check cache first (12 hour cache)
        $cache_key = 'grs_github_release_' . md5($this->github_api_url);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Fetch from GitHub API
        $response = wp_remote_get($this->github_api_url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['tag_name'])) {
            return false;
        }

        // Parse version from tag (remove 'v' prefix if present)
        $version = ltrim($data['tag_name'], 'v');

        // Get the zip download URL from release assets
        // Look for the uploaded ZIP file (e.g., google-reviews-slider-2.2.4.zip)
        // This ensures the ZIP contains the correct folder name: GoogleReviewsSlider-WP-Plugin
        $download_url = '';

        if (isset($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                // Look for .zip file in assets
                if (isset($asset['name']) && strpos($asset['name'], '.zip') !== false) {
                    $download_url = $asset['browser_download_url'];
                    error_log('GRS Updater: Found release asset: ' . $asset['name']);
                    break;
                }
            }
        }

        // Fallback to archive URL if no asset found (for backwards compatibility)
        if (empty($download_url)) {
            $download_url = "https://github.com/{$this->username}/{$this->repository}/archive/refs/tags/{$data['tag_name']}.zip";
            error_log('GRS Updater: No release asset found, using archive URL');
        }

        // Try to get plugin header info from release description
        $description = isset($data['body']) ? $data['body'] : '';

        $release_info = array(
            'version' => $version,
            'download_url' => $download_url,
            'name' => isset($data['name']) ? $data['name'] : 'Version ' . $version,
            'description' => $description,
            'published_at' => isset($data['published_at']) ? $data['published_at'] : '',
            'tested_up_to' => '6.7',
            'requires_at_least' => '5.0',
            'requires_php' => '7.4'
        );

        // Cache for 12 hours
        set_transient($cache_key, $release_info, 12 * HOUR_IN_SECONDS);

        return $release_info;
    }

    /**
     * Provide plugin information for the update screen
     *
     * @param false|object|array $result The result object
     * @param string $action The type of information being requested
     * @param object $args Plugin API arguments
     * @return object Plugin information
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if ($args->slug !== $this->slug) {
            return $result;
        }

        $release_info = $this->get_latest_release();

        if ($release_info === false) {
            return $result;
        }

        $plugin_info = new stdClass();
        $plugin_info->name = 'Google Reviews Slider';
        $plugin_info->slug = $this->slug;
        $plugin_info->version = $release_info['version'];
        $plugin_info->author = '<a href="https://carlosaragon.online">Carlos Aragon</a>';
        $plugin_info->homepage = "https://github.com/{$this->username}/{$this->repository}";
        $plugin_info->download_link = $release_info['download_url'];
        $plugin_info->requires = $release_info['requires_at_least'];
        $plugin_info->tested = $release_info['tested_up_to'];
        $plugin_info->requires_php = $release_info['requires_php'];
        $plugin_info->last_updated = $release_info['published_at'];
        $plugin_info->sections = array(
            'description' => $release_info['description'] ?: 'Display Google Reviews in an attractive slider format with advanced review extraction capabilities.',
            'changelog' => $this->parse_changelog($release_info['description'])
        );

        return $plugin_info;
    }

    /**
     * Parse changelog from release description
     *
     * @param string $description Release description
     * @return string HTML changelog
     */
    private function parse_changelog($description) {
        if (empty($description)) {
            return '<h4>See GitHub for changelog</h4><p><a href="https://github.com/' . $this->username . '/' . $this->repository . '/releases" target="_blank">View Releases</a></p>';
        }

        // Convert markdown to HTML (basic conversion)
        $html = wpautop($description);
        $html = str_replace('**', '<strong>', $html);
        $html = str_replace('##', '<h4>', $html);

        return $html;
    }

    /**
     * After installation hook
     *
     * @param bool $response Installation response
     * @param array $hook_extra Extra hook data
     * @param array $result Installation result
     * @return array Modified result
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        // Only run for our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->basename) {
            return $result;
        }

        error_log('GRS Updater: Running after_install hook');
        error_log('GRS Updater: Plugin basename: ' . $this->basename);
        error_log('GRS Updater: Destination: ' . $result['destination']);

        // Get the correct destination path (where plugin should be)
        $plugin_folder = WP_PLUGIN_DIR . '/' . dirname($this->basename);
        error_log('GRS Updater: Target plugin folder: ' . $plugin_folder);

        // Source is where WordPress extracted the ZIP
        $source = $result['destination'];

        // GitHub creates folders like: GoogleReviewsSlider-WP-Plugin-2.2.2
        // We need to rename it to: google-reviews-slider (or whatever dirname($this->basename) is)

        if ($wp_filesystem->is_dir($source)) {
            // List contents of extracted folder
            $source_files = $wp_filesystem->dirlist($source);
            error_log('GRS Updater: Source files count: ' . count($source_files));

            if ($source_files && count($source_files) === 1) {
                // Get the GitHub folder name (e.g., "GoogleReviewsSlider-WP-Plugin-2.2.2")
                $github_folder_name = array_keys($source_files)[0];
                $github_folder_path = trailingslashit($source) . $github_folder_name;

                error_log('GRS Updater: GitHub folder name: ' . $github_folder_name);
                error_log('GRS Updater: GitHub folder path: ' . $github_folder_path);

                // Remove existing plugin folder if it exists
                if ($wp_filesystem->is_dir($plugin_folder)) {
                    error_log('GRS Updater: Removing old plugin folder...');
                    $deleted = $wp_filesystem->delete($plugin_folder, true);
                    error_log('GRS Updater: Old folder deleted: ' . ($deleted ? 'yes' : 'no'));
                }

                // Move GitHub folder to correct location
                error_log('GRS Updater: Moving from ' . $github_folder_path . ' to ' . $plugin_folder);
                $moved = $wp_filesystem->move($github_folder_path, $plugin_folder);

                if ($moved) {
                    error_log('GRS Updater: Move successful!');

                    // Update result to point to correct destination
                    $result['destination'] = $plugin_folder;
                    $result['destination_name'] = dirname($this->basename);

                    error_log('GRS Updater: Updated result destination: ' . $result['destination']);

                    // Clean up temp directory
                    if ($wp_filesystem->is_dir($source)) {
                        $wp_filesystem->delete($source, true);
                        error_log('GRS Updater: Cleaned up temp directory');
                    }

                    // Clean up old version folders
                    $this->cleanup_old_folders();
                } else {
                    error_log('GRS Updater: ERROR - Move failed!');
                }
            } else {
                error_log('GRS Updater: ERROR - Expected 1 folder, found: ' . count($source_files));
            }
        } else {
            error_log('GRS Updater: ERROR - Source is not a directory: ' . $source);
        }

        return $result;
    }

    /**
     * Display update message
     *
     * @param array $plugin_data Plugin data
     * @param object $response Update response
     */
    public function update_message($plugin_data, $response) {
        if (empty($response->package)) {
            echo '<br><strong>Note:</strong> Automatic update available from GitHub.';
        }
    }

    /**
     * Enable automatic updates for this plugin
     *
     * @param bool $update Whether to update
     * @param object $item The update item
     * @return bool
     */
    public function enable_auto_update($update, $item) {
        // Only enable auto-update for our plugin
        if (isset($item->slug) && $item->slug === $this->slug) {
            return true;
        }

        return $update;
    }

    /**
     * Save plugin activation state before update
     *
     * @param bool $response
     * @param array $hook_extra
     * @return bool
     */
    public function save_activation_state($response, $hook_extra) {
        // Check if this is a plugin update
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->basename) {
            // Save current activation state
            $is_active = is_plugin_active($this->basename);
            update_option('grs_was_active_before_update', $is_active ? '1' : '0');
            error_log('Google Reviews Slider: Saved activation state before update - ' . ($is_active ? 'active' : 'inactive'));
        }

        return $response;
    }

    /**
     * Restore plugin activation state after update
     *
     * @param WP_Upgrader $upgrader_object
     * @param array $options
     */
    public function restore_activation_state($upgrader_object, $options) {
        // Only run for plugin updates
        if (!isset($options['action']) || $options['action'] !== 'update') {
            return;
        }

        if (!isset($options['type']) || $options['type'] !== 'plugin') {
            return;
        }

        // Check if our plugin was updated (handles both single and bulk updates)
        $updated_plugins = array();

        if (isset($options['plugins'])) {
            $updated_plugins = $options['plugins'];
        } elseif (isset($options['plugin'])) {
            $updated_plugins = array($options['plugin']);
        }

        // Check if our plugin is in the updated list
        if (in_array($this->basename, $updated_plugins)) {
            // Get saved activation state
            $was_active = get_option('grs_was_active_before_update', '0');

            // If it was active before update, reactivate it now
            if ($was_active === '1') {
                // Small delay to ensure files are fully in place
                usleep(100000); // 0.1 seconds

                // Activate plugin
                $result = activate_plugin($this->basename, '', false, true);

                // Log result
                if (!is_wp_error($result)) {
                    error_log('Google Reviews Slider: Plugin reactivated successfully after update');
                } else {
                    error_log('Google Reviews Slider: Failed to reactivate - ' . $result->get_error_message());
                }
            }

            // Clean up the saved state
            delete_option('grs_was_active_before_update');
        }
    }

    /**
     * Force check for updates (can be called manually)
     */
    public function force_update_check() {
        // Clear cache
        $cache_key = 'grs_github_release_' . md5($this->github_api_url);
        delete_transient($cache_key);

        // Delete update transient to force recheck
        delete_site_transient('update_plugins');
    }

    /**
     * Clean up old plugin folders from previous versions
     * This removes folders like GoogleReviewsSlider-WP-Plugin-2.2.1, etc.
     */
    public function cleanup_old_folders() {
        $plugin_dir = WP_PLUGIN_DIR;
        $current_folder = dirname($this->basename);

        // Pattern to match: GoogleReviewsSlider-WP-Plugin-x.x.x
        $pattern = 'GoogleReviewsSlider-WP-Plugin-*';

        $folders = glob($plugin_dir . '/' . $pattern, GLOB_ONLYDIR);

        foreach ($folders as $folder) {
            $folder_name = basename($folder);

            // Don't delete the current active folder
            if ($folder_name === $current_folder) {
                continue;
            }

            error_log('GRS Updater: Cleaning up old folder: ' . $folder_name);

            // Delete the old folder
            global $wp_filesystem;
            if (WP_Filesystem()) {
                $wp_filesystem->delete($folder, true);
                error_log('GRS Updater: Deleted old folder: ' . $folder_name);
            }
        }
    }
}
