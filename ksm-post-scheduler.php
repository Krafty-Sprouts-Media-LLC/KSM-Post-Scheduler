<?php
/**
 * Plugin Name: KSM Post Scheduler
 * Plugin URI: https://kraftysprouts.com
 * Description: Automatically schedules posts from a specific status to publish at random times
 * Version: 1.6.3
 * Author: Krafty Sprouts Media, LLC
 * Author URI: https://kraftysprouts.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ksm-post-scheduler
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * 
 * @package KSM_Post_Scheduler
 * @version 1.6.3
 * @author KraftySpoutsMedia, LLC
 * @copyright 2025 KraftySpouts
 * @license GPL-2.0-or-later
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KSM_PS_VERSION', '1.4.4');
define('KSM_PS_PLUGIN_FILE', __FILE__);
define('KSM_PS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KSM_PS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KSM_PS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main KSM Post Scheduler Class
 * 
 * @class KSM_PS_Main
 * @version 1.0.0
 * @since 1.0.0
 */
class KSM_PS_Main {
    
    /**
     * Plugin instance
     * 
     * @var KSM_PS_Main
     * @since 1.0.0
     */
    private static $instance = null;
    
    /**
     * Cron hook name
     * 
     * @var string
     * @since 1.0.0
     */
    private $cron_hook = 'ksm_ps_daily_cron';
    
    /**
     * Settings option name
     * 
     * @var string
     * @since 1.0.0
     */
    private $option_name = 'ksm_ps_settings';
    
    /**
     * Get plugin instance
     * 
     * @return KSM_PS_Main
     * @since 1.0.0
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }
    

    
    /**
     * Initialize WordPress hooks
     * 
     * @since 1.0.0
     */
    private function init_hooks() {
        // Activation, deactivation, and uninstall hooks
        register_activation_hook(KSM_PS_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(KSM_PS_PLUGIN_FILE, array($this, 'deactivate'));
        register_uninstall_hook(KSM_PS_PLUGIN_FILE, 'KSM_PS_Main::uninstall');
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Cron hooks
        add_action($this->cron_hook, array($this, 'random_post_scheduler_daily_cron'));
        
        // Dynamic publication hooks (registered for each scheduled post)
        add_action('init', array($this, 'register_publication_hooks'));
        
        // AJAX hooks
        add_action('wp_ajax_ksm_ps_run_now', array($this, 'ajax_run_now'));
        add_action('wp_ajax_ksm_ps_get_status', array($this, 'ajax_get_status'));
    }
    
    /**
     * Plugin activation
     * 
     * @since 1.0.0
     */
    public function activate() {
        // Check WordPress version compatibility
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(KSM_PS_PLUGIN_BASENAME);
            wp_die(__('KSM Post Scheduler requires WordPress 5.0 or higher.', 'ksm-post-scheduler'));
        }
        
        // Check PHP version compatibility
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(KSM_PS_PLUGIN_BASENAME);
            wp_die(__('KSM Post Scheduler requires PHP 7.4 or higher.', 'ksm-post-scheduler'));
        }
        
        // Set default options
        $default_options = array(
            'enabled' => false,
            'post_status' => 'draft',
            'posts_per_day' => 5,
            'start_time' => '9:00 AM',
            'end_time' => '6:00 PM',
            'days_active' => array('monday', 'tuesday', 'wednesday', 'thursday', 'friday'),
            'min_interval' => 30,
            'version' => KSM_PS_VERSION,
            'installed_date' => current_time('mysql')
        );
        
        // Get existing options
        $existing_options = get_option($this->option_name);
        
        if (!$existing_options) {
            // First time installation
            add_option($this->option_name, $default_options);
        } else {
            // Plugin update - merge new options with existing ones
            $updated_options = array_merge($default_options, $existing_options);
            $updated_options['version'] = KSM_PS_VERSION;
            
            // Only set default times if they don't exist or are invalid
            if (empty($updated_options['start_time']) || !preg_match('/^(1[0-2]|[1-9]):[0-5][0-9]\s?(AM|PM|am|pm)$/i', $updated_options['start_time'])) {
                $updated_options['start_time'] = '9:00 AM';
            }
            if (empty($updated_options['end_time']) || !preg_match('/^(1[0-2]|[1-9]):[0-5][0-9]\s?(AM|PM|am|pm)$/i', $updated_options['end_time'])) {
                $updated_options['end_time'] = '6:00 PM';
            }
            
            update_option($this->option_name, $updated_options);
        }
        
        // Schedule cron job
        if (!wp_next_scheduled($this->cron_hook)) {
            wp_schedule_event(strtotime('tomorrow midnight'), 'daily', $this->cron_hook);
        }
        
        // Set activation flag for admin notice
        set_transient('ksm_ps_activation_notice', true, 30);
        
        // Flush rewrite rules if needed
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     * 
     * @since 1.0.0
     */
    public function deactivate() {
        // Clear scheduled cron job
        $timestamp = wp_next_scheduled($this->cron_hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->cron_hook);
        }
        
        // Clear all scheduled hooks for this plugin
        wp_clear_scheduled_hook($this->cron_hook);
        
        // Clear all publication hooks for scheduled posts
        $future_posts = get_posts(array(
            'post_status' => 'future',
            'numberposts' => -1,
            'post_type' => 'post'
        ));
        
        foreach ($future_posts as $post) {
            $hook_name = 'ksm_ps_publish_post_' . $post->ID;
            wp_clear_scheduled_hook($hook_name);
        }
        
        // Clear any transients
        delete_transient('ksm_ps_status_cache');
        delete_transient('ksm_ps_upcoming_posts');
        delete_transient('ksm_ps_activation_notice');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall (static method)
     * 
     * @since 1.0.0
     */
    public static function uninstall() {
        // This method is called when the plugin is deleted
        // The actual cleanup is handled by uninstall.php
        // This method exists for compatibility and logging
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('KSM Post Scheduler: Uninstall hook called.');
        }
    }
    
    /**
     * Display admin notices
     * 
     * @since 1.0.0
     */
    public function admin_notices() {
        // Show activation notice
        if (get_transient('ksm_ps_activation_notice')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e('KSM Post Scheduler', 'ksm-post-scheduler'); ?></strong> 
                    <?php _e('has been activated successfully!', 'ksm-post-scheduler'); ?>
                    <a href="<?php echo admin_url('options-general.php?page=ksm-post-scheduler'); ?>">
                        <?php _e('Configure settings', 'ksm-post-scheduler'); ?>
                    </a>
                </p>
            </div>
            <?php
            delete_transient('ksm_ps_activation_notice');
        }
        
        // Check for version updates
        $options = get_option($this->option_name);
        if ($options && isset($options['version']) && version_compare($options['version'], KSM_PS_VERSION, '<')) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong><?php _e('KSM Post Scheduler', 'ksm-post-scheduler'); ?></strong> 
                    <?php printf(__('has been updated to version %s.', 'ksm-post-scheduler'), KSM_PS_VERSION); ?>
                    <a href="<?php echo admin_url('options-general.php?page=ksm-post-scheduler'); ?>">
                        <?php _e('View settings', 'ksm-post-scheduler'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
        
        // TEMPORARY DEBUG: Show current settings on plugin page
        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_ksm-post-scheduler') {
            $current_options = get_option($this->option_name, array());
            $current_wp_time = current_time('Y-m-d H:i:s');
            $current_server_time = date('Y-m-d H:i:s');
            $timezone = get_option('timezone_string');
            $gmt_offset = get_option('gmt_offset');
            ?>
            <div class="notice notice-warning">
                <h3>🐛 DEBUG INFO (Temporary)</h3>
                <p><strong>Current WordPress Time:</strong> <?php echo esc_html($current_wp_time); ?></p>
                <p><strong>Current Server Time:</strong> <?php echo esc_html($current_server_time); ?></p>
                <p><strong>WordPress Timezone:</strong> <?php echo esc_html($timezone ?: 'Not set'); ?></p>
                <p><strong>GMT Offset:</strong> <?php echo esc_html($gmt_offset); ?></p>
                <p><strong>Plugin Settings:</strong></p>
                <pre style="background: #f1f1f1; padding: 10px; overflow: auto;"><?php echo esc_html(print_r($current_options, true)); ?></pre>
            </div>
            <?php
        }
    }
    
    /**
     * Add admin menu
     * 
     * @since 1.0.0
     */
    public function add_admin_menu() {
        add_options_page(
            __('KSM Post Scheduler', 'ksm-post-scheduler'),
            __('Post Scheduler', 'ksm-post-scheduler'),
            'manage_options',
            'ksm-post-scheduler',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Initialize admin settings
     * 
     * @since 1.0.0
     */
    public function admin_init() {
        register_setting('ksm_ps_settings_group', $this->option_name, array($this, 'sanitize_settings'));
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function admin_enqueue_scripts($hook) {
        if ('settings_page_ksm-post-scheduler' !== $hook) {
            return;
        }
        
        // Enqueue SweetAlert2 from CDN
        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11.23.0/dist/sweetalert2.all.min.js',
            array(),
            '11.23.0',
            true
        );
        
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'ksm-ps-admin',
            KSM_PS_PLUGIN_URL . 'assets/admin.js',
            array('jquery', 'sweetalert2'),
            KSM_PS_VERSION,
            true
        );
        
        wp_localize_script('ksm-ps-admin', 'ksm_ps_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ksm_ps_nonce'),
            'strings' => array(
                'running' => __('Running...', 'ksm-post-scheduler'),
                'success' => __('Scheduler executed successfully!', 'ksm-post-scheduler'),
                'error' => __('Error occurred while running scheduler.', 'ksm-post-scheduler'),
                'validation_error' => __('Validation Error', 'ksm-post-scheduler'),
                'time_format_error' => __('Please enter time in 12-hour format (e.g., 9:00 AM or 6:30 PM)', 'ksm-post-scheduler'),
                'posts_per_day_error' => __('Posts per day must be between 1 and 50', 'ksm-post-scheduler'),
                'min_interval_error' => __('Minimum interval must be between 5 and 1440 minutes', 'ksm-post-scheduler'),
                'time_window_error' => __('Not enough time between start and end time for the specified number of posts with minimum intervals', 'ksm-post-scheduler'),
                'settings_saved' => __('Settings saved successfully!', 'ksm-post-scheduler'),
                'settings_error' => __('Error saving settings. Please check your input and try again.', 'ksm-post-scheduler')
            )
        ));
        
        wp_enqueue_style(
            'ksm-ps-admin',
            KSM_PS_PLUGIN_URL . 'assets/admin.css',
            array(),
            KSM_PS_VERSION
        );
    }
    
    /**
     * Sanitize settings
     * 
     * @param array $input Raw input data
     * @return array Sanitized settings
     * @since 1.0.0
     */
    public function sanitize_settings($input) {
        // Get current options to compare for changes
        $current_options = get_option($this->option_name, array());
        $current_posts_per_day = $current_options['posts_per_day'] ?? 5;
        
        $sanitized = array();
        $validation_errors = array();
        
        $sanitized['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : false;
        $sanitized['post_status'] = sanitize_text_field($input['post_status'] ?? 'draft');
        
        // Validate and sanitize posts per day
        $posts_per_day = absint($input['posts_per_day'] ?? 5);
        if ($posts_per_day < 1 || $posts_per_day > 50) {
            $validation_errors[] = __('Posts per day must be between 1 and 50.', 'ksm-post-scheduler');
            $posts_per_day = max(1, min(50, $posts_per_day)); // Clamp to valid range
        }
        $sanitized['posts_per_day'] = $posts_per_day;
        
        // Handle time fields - use 12-hour format for storage and display
        $sanitized['start_time'] = sanitize_text_field($input['start_time'] ?? '9:00 AM');
        $sanitized['end_time'] = sanitize_text_field($input['end_time'] ?? '6:00 PM');
        
        // Validate 12-hour time format
        if (!empty($sanitized['start_time'])) {
            $time_test = DateTime::createFromFormat('g:i A', $sanitized['start_time']);
            if (!$time_test) {
                $time_test = DateTime::createFromFormat('h:i A', $sanitized['start_time']);
            }
            if (!$time_test) {
                $validation_errors[] = __('Invalid start time format. Please use 12-hour format (e.g., 9:00 AM).', 'ksm-post-scheduler');
                $sanitized['start_time'] = '9:00 AM';
            }
        }
        
        if (!empty($sanitized['end_time'])) {
            $time_test = DateTime::createFromFormat('g:i A', $sanitized['end_time']);
            if (!$time_test) {
                $time_test = DateTime::createFromFormat('h:i A', $sanitized['end_time']);
            }
            if (!$time_test) {
                $validation_errors[] = __('Invalid end time format. Please use 12-hour format (e.g., 6:00 PM).', 'ksm-post-scheduler');
                $sanitized['end_time'] = '6:00 PM';
            }
        }
        
        // Validate and sanitize minimum interval
        $min_interval = absint($input['min_interval'] ?? 30);
        if ($min_interval < 5 || $min_interval > 1440) {
            $validation_errors[] = __('Minimum interval must be between 5 and 1440 minutes.', 'ksm-post-scheduler');
            $min_interval = max(5, min(1440, $min_interval)); // Clamp to valid range
        }
        $sanitized['min_interval'] = $min_interval;
        
        // Validate time window
        if (!empty($sanitized['start_time']) && !empty($sanitized['end_time'])) {
            $start_minutes = $this->time_to_minutes($sanitized['start_time']);
            $end_minutes = $this->time_to_minutes($sanitized['end_time']);
            
            if ($end_minutes <= $start_minutes) {
                $validation_errors[] = __('End time must be after start time.', 'ksm-post-scheduler');
            } else {
                // Check if there's enough time for the specified posts with intervals
                $available_minutes = $end_minutes - $start_minutes;
                $required_minutes = ($posts_per_day - 1) * $min_interval;
                
                if ($required_minutes > $available_minutes) {
                    $available_hours = floor($available_minutes / 60);
                    $available_mins = $available_minutes % 60;
                    $required_hours = floor($required_minutes / 60);
                    $required_mins = $required_minutes % 60;
                    
                    $error_message = sprintf(
                        __('Not enough time! You need %d hours %d minutes (%d minutes total) but only have %d hours %d minutes (%d minutes total) available. Suggestions: Reduce posts to %d per day, OR extend end time by %d minutes, OR reduce interval to %d minutes.', 'ksm-post-scheduler'),
                        $required_hours,
                        $required_mins,
                        $required_minutes,
                        $available_hours,
                        $available_mins,
                        $available_minutes,
                        max(1, floor($available_minutes / $min_interval) + 1),
                        $required_minutes - $available_minutes,
                        max(5, floor($available_minutes / ($posts_per_day - 1)))
                    );
                    
                    $validation_errors[] = $error_message;
                }
            }
        }
        
        // Sanitize days active
        $valid_days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $sanitized['days_active'] = array();
        if (isset($input['days_active']) && is_array($input['days_active'])) {
            foreach ($input['days_active'] as $day) {
                if (in_array($day, $valid_days)) {
                    $sanitized['days_active'][] = $day;
                }
            }
        }
        
        // If there are validation errors, add them as settings errors
        if (!empty($validation_errors)) {
            foreach ($validation_errors as $error) {
                add_settings_error(
                    $this->option_name,
                    'validation_error',
                    $error,
                    'error'
                );
            }
        }
        
        // Check if posts_per_day has changed and there are scheduled posts
        if ($current_posts_per_day !== $sanitized['posts_per_day']) {
            error_log("KSM DEBUG - Posts per day changed from $current_posts_per_day to {$sanitized['posts_per_day']}");
            
            // Schedule the re-adjustment to run after the settings are saved
            add_action('updated_option_' . $this->option_name, array($this, 'handle_posts_per_day_change'), 10, 2);
        }
        
        return $sanitized;
    }
    
    /**
     * Convert 12-hour time string to minutes since midnight
     * 
     * @param string $time_string Time in 12-hour format (e.g., "9:00 AM")
     * @return int Minutes since midnight
     * @since 1.1.5
     */
    private function time_to_minutes($time_string) {
        $time = DateTime::createFromFormat('g:i A', $time_string);
        if (!$time) {
            $time = DateTime::createFromFormat('h:i A', $time_string);
        }
        if ($time) {
            return (int)$time->format('H') * 60 + (int)$time->format('i');
        }
        return 0; // Default fallback
    }
    
    /**
     * Handle posts per day setting change
     * 
     * @param array $old_value Previous option value
     * @param array $new_value New option value
     * @since 1.1.5
     */
    public function handle_posts_per_day_change($old_value, $new_value) {
        error_log("KSM DEBUG - Handling posts per day change - re-adjusting scheduled posts");
        
        // Re-adjust existing scheduled posts with new posts per day setting
        $result = $this->readjust_scheduled_posts();
        
        if ($result['success']) {
            add_settings_error(
                $this->option_name,
                'posts_readjusted',
                __('Settings saved and existing scheduled posts have been re-adjusted based on the new posts per day setting.', 'ksm-post-scheduler'),
                'updated'
            );
        } else {
            add_settings_error(
                $this->option_name,
                'readjust_failed',
                __('Settings saved, but failed to re-adjust existing scheduled posts: ' . $result['message'], 'ksm-post-scheduler'),
                'error'
            );
        }
    }
    
    /**
     * Admin page content
     * 
     * @since 1.0.0
     */
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ksm-post-scheduler'));
        }
        
        $options = get_option($this->option_name, array());
        $post_statuses = get_post_stati(array('public' => false), 'objects');
        
        // Get current status
        $monitored_count = $this->get_monitored_posts_count();
        $upcoming_posts = $this->get_upcoming_scheduled_posts();
        $scheduling_preview = $this->get_scheduling_preview();
        
        include KSM_PS_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    /**
     * Get count of posts in monitored status
     * 
     * @return int Number of posts
     * @since 1.0.0
     */
    private function get_monitored_posts_count() {
        $options = get_option($this->option_name, array());
        $post_status = $options['post_status'] ?? 'draft';
        
        $posts = get_posts(array(
            'post_status' => $post_status,
            'numberposts' => -1,
            'post_type' => 'post'
        ));
        
        return count($posts);
    }
    
    /**
     * Get upcoming scheduled posts
     * 
     * @return array Array of scheduled posts
     * @since 1.0.0
     */
    private function get_upcoming_scheduled_posts() {
        $posts = get_posts(array(
            'post_status' => 'future',
            'numberposts' => 10,
            'post_type' => 'post',
            'orderby' => 'date',
            'order' => 'ASC'
        ));
        
        $upcoming = array();
        foreach ($posts as $post) {
            $upcoming[] = array(
                'title' => $post->post_title,
                'scheduled_time' => get_the_date('Y-m-d H:i:s', $post->ID)
            );
        }
        
        return $upcoming;
    }
    
    /**
     * Daily cron job function
     * 
     * @since 1.0.0
     */
    public function random_post_scheduler_daily_cron() {
        $options = get_option($this->option_name, array());
        
        // Check if scheduler is enabled
        if (empty($options['enabled'])) {
            return;
        }
        
        // Check if today is an active day
        $today = strtolower(date('l'));
        if (!in_array($today, $options['days_active'] ?? array())) {
            return;
        }
        
        // Update last cron run time
        $options['last_cron_run'] = current_time('mysql');
        update_option($this->option_name, $options);
        
        $this->schedule_posts(true); // Pass true to indicate this is a cron run
    }
    
    /**
     * Re-adjust existing scheduled posts when posts per day setting changes
     * 
     * @return array Result array with success status and message
     * @since 1.1.5
     */
    private function readjust_scheduled_posts() {
        $options = get_option($this->option_name, array());
        $posts_per_day = $options['posts_per_day'] ?? 5;
        
        // Get all currently scheduled posts (future status)
        $scheduled_posts = get_posts(array(
            'post_status' => 'future',
            'numberposts' => -1,
            'post_type' => 'post',
            'orderby' => 'post_date',
            'order' => 'ASC'
        ));
        
        if (empty($scheduled_posts)) {
            return array('success' => true, 'message' => 'No scheduled posts to re-adjust.');
        }
        
        error_log("KSM DEBUG - Re-adjusting " . count($scheduled_posts) . " scheduled posts with new posts_per_day: $posts_per_day");
        
        // Reset all scheduled posts to draft status first
        foreach ($scheduled_posts as $post) {
            wp_update_post(array(
                'ID' => $post->ID,
                'post_status' => $options['post_status'] ?? 'draft'
            ));
        }
        
        // Now reschedule them with the new posts per day setting
        return $this->schedule_posts();
    }
    
    /**
     * Register publication hooks for scheduled posts
     * 
     * @since 1.6.1
     */
    public function register_publication_hooks() {
        // Get all future posts to register their publication hooks
        $future_posts = get_posts(array(
            'post_status' => 'future',
            'numberposts' => -1,
            'post_type' => 'post'
        ));
        
        foreach ($future_posts as $post) {
            $hook_name = 'ksm_ps_publish_post_' . $post->ID;
            if (!has_action($hook_name)) {
                add_action($hook_name, array($this, 'publish_scheduled_post'));
            }
        }
    }
    
    /**
     * Publish a scheduled post
     * 
     * @param int $post_id Post ID to publish
     * @since 1.6.1
     */
    public function publish_scheduled_post($post_id) {
        global $wpdb;
        
        error_log("KSM DEBUG - Publishing scheduled post $post_id");
        
        // Update post status to published directly in database
        $update_result = $wpdb->update(
            $wpdb->posts,
            array(
                'post_status' => 'publish',
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', 1)
            ),
            array('ID' => $post_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($update_result !== false) {
            // Clear post cache
            clean_post_cache($post_id);
            
            // Trigger WordPress publish actions
            do_action('publish_post', $post_id, get_post($post_id));
            
            error_log("KSM DEBUG - Successfully published post $post_id");
        } else {
            error_log("KSM DEBUG - ERROR: Failed to publish post $post_id");
        }
    }

    /**
     * Schedule posts function
     * 
     * @param bool $is_cron_run Whether this is being called from cron (true) or manual testing (false)
     * @return array Result array with success status and message
     * @since 1.0.0
     */
    private function schedule_posts($is_cron_run = false) {
        $options = get_option($this->option_name, array());
        $posts_per_day = $options['posts_per_day'] ?? 5;
        
        // For manual testing, limit to current day's posts only
        // For cron runs, schedule up to 7 days worth
        $max_posts_to_schedule = $is_cron_run ? ($posts_per_day * 7) : $posts_per_day;
        
        // Get posts to schedule
        $posts = get_posts(array(
            'post_status' => $options['post_status'] ?? 'draft',
            'numberposts' => $max_posts_to_schedule,
            'post_type' => 'post',
            'orderby' => 'date',
            'order' => 'ASC'
        ));
        
        if (empty($posts)) {
            return array('success' => false, 'message' => 'No posts found to schedule.');
        }
        
        // Get time settings
        $start_time = $options['start_time'] ?? '9:00 AM';
        $end_time = $options['end_time'] ?? '6:00 PM';
        $min_interval = $options['min_interval'] ?? 30;
        $days_active = $options['days_active'] ?? array('monday', 'tuesday', 'wednesday', 'thursday', 'friday');
        
        // Initialize progress tracking for manual runs
        $progress_report = array();
        $total_posts_to_schedule = count($posts);
        
        // Convert start and end times to minutes for easier calculation
        $start_minutes = $this->time_to_minutes($start_time);
        $end_minutes = $this->time_to_minutes($end_time);
        
        // Get current WordPress time (respects site timezone)
        $current_wp_timestamp = current_time('timestamp');
        $current_wp_time = current_time('Y-m-d H:i:s');
        $current_date = current_time('Y-m-d');
        $current_time_minutes = (int)current_time('H') * 60 + (int)current_time('i');
        
        // DEBUG: Log current settings and time
        error_log("KSM DEBUG - Current WP Time: $current_wp_time");
        error_log("KSM DEBUG - Current Date: $current_date");
        error_log("KSM DEBUG - Current Time Minutes: $current_time_minutes");
        error_log("KSM DEBUG - Start Time: $start_time ($start_minutes minutes)");
        error_log("KSM DEBUG - End Time: $end_time ($end_minutes minutes)");
        error_log("KSM DEBUG - Posts Per Day: $posts_per_day");
        error_log("KSM DEBUG - Is Cron Run: " . ($is_cron_run ? 'Yes' : 'No'));
        error_log("KSM DEBUG - Max Posts to Schedule: $max_posts_to_schedule");
        error_log("KSM DEBUG - Total Posts Available: " . count($posts));
        
        $scheduled_count = 0;
        $current_day_offset = 0;
        $posts_scheduled_for_current_day = 0;
        
        // For manual scheduling, bypass daily limits and schedule immediately
        if (!$is_cron_run) {
            error_log("KSM DEBUG - Manual scheduling mode: bypassing daily limits");
            error_log("KSM DEBUG - Will schedule all " . count($posts) . " posts regardless of daily limits");
            
            // In manual mode, we don't check daily limits - schedule all requested posts
            // This allows administrators to override automatic scheduling constraints
        } else {
            // For automatic cron runs, check daily limits
            $today_scheduled = get_posts(array(
                'post_status' => 'future',
                'numberposts' => -1,
                'post_type' => 'post',
                'date_query' => array(
                    array(
                        'year'  => (int)current_time('Y'),
                        'month' => (int)current_time('n'),
                        'day'   => (int)current_time('j'),
                    ),
                ),
            ));
            
            $posts_already_scheduled_today = count($today_scheduled);
            error_log("KSM DEBUG - Posts already scheduled for today: $posts_already_scheduled_today");
            
            if ($posts_already_scheduled_today >= $posts_per_day) {
                return array('success' => false, 'message' => "Daily limit reached. $posts_already_scheduled_today posts already scheduled for today (limit: $posts_per_day).");
            }
            
            // Adjust the number of posts we can schedule today
            $remaining_slots_today = $posts_per_day - $posts_already_scheduled_today;
            $posts = array_slice($posts, 0, $remaining_slots_today);
            error_log("KSM DEBUG - Can schedule $remaining_slots_today more posts today");
        }
        
        // Determine if we can schedule posts today
        $can_schedule_today = false;
        $today_name = strtolower(current_time('l'));
        
        if (in_array($today_name, $days_active)) {
            // Check if we're still within scheduling hours
            $latest_scheduling_time = $end_minutes;
            
            // We can schedule today if:
            // 1. Current time is before the latest scheduling time (end time)
            // 2. We still have time for at least one post (considering start time and minimum interval)
            if ($current_time_minutes < $latest_scheduling_time) {
                // Calculate the effective start time for today
                $effective_start_time = max($current_time_minutes, $start_minutes);
                
                // Check if we have enough time between effective start and latest scheduling time
                $remaining_time_today = $latest_scheduling_time - $effective_start_time;
                $possible_posts_today = min($posts_per_day, floor($remaining_time_today / $min_interval) + 1);
                
                if ($possible_posts_today > 0 && $effective_start_time <= $latest_scheduling_time) {
                    $can_schedule_today = true;
                    error_log("KSM DEBUG - Can schedule $possible_posts_today posts today");
                    error_log("KSM DEBUG - Effective start time today: " . $this->minutes_to_time($effective_start_time));
                    error_log("KSM DEBUG - Latest scheduling time: " . $this->minutes_to_time($latest_scheduling_time));
                    error_log("KSM DEBUG - Remaining time: $remaining_time_today minutes");
                } else {
                    error_log("KSM DEBUG - Cannot schedule posts today - not enough time remaining after considering start time");
                    error_log("KSM DEBUG - Effective start: " . $this->minutes_to_time($effective_start_time) . ", Latest: " . $this->minutes_to_time($latest_scheduling_time));
                }
            } else {
                error_log("KSM DEBUG - Cannot schedule posts today - current time ($current_time_minutes min) is past latest scheduling time ($latest_scheduling_time min)");
            }
        } else {
            error_log("KSM DEBUG - Cannot schedule posts today - not an active day ($today_name)");
        }
        
        // For manual testing, if we can't schedule today, return an error
        if (!$is_cron_run && !$can_schedule_today) {
            return array('success' => false, 'message' => 'Cannot schedule posts today. Either it\'s not an active day or the scheduling window has passed.');
        }
        
        // Log the starting point
        if (!$can_schedule_today) {
            error_log("KSM DEBUG - Starting from tomorrow (day offset: $current_day_offset, will be calculated by get_next_valid_day)");
        } else {
            error_log("KSM DEBUG - Starting from today (day offset: $current_day_offset)");
        }
        
        // Pre-generate schedules for each day as needed
        $daily_schedules = array();
        
        foreach ($posts as $index => $post) {
            error_log("KSM DEBUG - Processing post {$post->ID} '{$post->post_title}' (index: $index)");
            error_log("KSM DEBUG - Current state: day_offset=$current_day_offset, posts_for_current_day=$posts_scheduled_for_current_day/$posts_per_day");
            
            // CONSOLIDATED DAY-CHANGE LOGIC: Check if we need to move to the next day
            if ($posts_scheduled_for_current_day >= $posts_per_day) {
                error_log("KSM DEBUG - Daily limit reached ($posts_scheduled_for_current_day/$posts_per_day), moving to next day");
                
                // Add progress report for manual runs
                if (!$is_cron_run && isset($target_date)) {
                    $progress_report[] = "✓ Daily limit reached for " . wp_date('l, M j', strtotime($target_date)) . " - Moving to next day";
                }
                
                // Move to next valid day
                $current_day_offset++;
                $posts_scheduled_for_current_day = 0;
                error_log("KSM DEBUG - Advanced to day_offset=$current_day_offset, reset posts_for_current_day=0");
            }
            
            // Find the target scheduling day
            $target_timestamp = $this->get_next_valid_day($current_day_offset, $days_active, $can_schedule_today);
            $target_date = wp_date('Y-m-d', $target_timestamp);
            
            error_log("KSM DEBUG - Target date: $target_date (timestamp: $target_timestamp)");
            
            // Generate times for this day if not already done
            if (!isset($daily_schedules[$target_date])) {
                $day_start_time = $start_time;
                $posts_to_generate = $posts_per_day;
                
                // Add progress report for manual runs - new day started
                if (!$is_cron_run) {
                    $remaining_posts = $total_posts_to_schedule - $index;
                    $posts_for_this_day = min($posts_per_day, $remaining_posts);
                    $progress_report[] = "📅 " . wp_date('l, M j', $target_timestamp) . " - Can schedule {$posts_for_this_day} posts today";
                }
                
                // For today, adjust start time and calculate available slots
                if ($current_day_offset === 0 && $can_schedule_today) {
                    $effective_start_time = max($current_time_minutes, $start_minutes);
                    
                    // CRITICAL FIX: Ensure start time is always in the future for today
                    if ($current_time_minutes > $start_minutes) {
                        // Add buffer time to ensure we're definitely in the future
                        $buffer_minutes = 5; // 5-minute buffer
                        $safe_start_time = $current_time_minutes + $buffer_minutes;
                        
                        $adjusted_hour = floor($safe_start_time / 60);
                        $adjusted_minute = $safe_start_time % 60;
                        
                        // Handle hour overflow (past midnight)
                        if ($adjusted_hour >= 24) {
                            error_log("KSM DEBUG - Time calculation would go past midnight, skipping today");
                            $can_schedule_today = false;
                            $current_day_offset = 1; // Move to tomorrow
                            $posts_scheduled_for_current_day = 0;
                            // Recalculate target for tomorrow
                            $target_timestamp = $this->get_next_valid_day($current_day_offset, $days_active, $can_schedule_today);
                            $target_date = wp_date('Y-m-d', $target_timestamp);
                            $day_start_time = $start_time; // Reset to original start time for tomorrow
                            $posts_to_generate = $posts_per_day;
                            error_log("KSM DEBUG - Moved to tomorrow: $target_date");
                        }
                        
                        $day_start_time = sprintf('%d:%02d %s', 
                            $adjusted_hour > 12 ? $adjusted_hour - 12 : ($adjusted_hour == 0 ? 12 : $adjusted_hour),
                            $adjusted_minute,
                            $adjusted_hour >= 12 ? 'PM' : 'AM'
                        );
                        
                        $effective_start_time = $safe_start_time;
                        error_log("KSM DEBUG - Adjusted start time for today with buffer: $day_start_time (minutes: $safe_start_time)");
                    }
                    
                    $remaining_time_today = $end_minutes - $effective_start_time;
                    
                    // Ensure we have enough time for at least one post
                    if ($remaining_time_today < $min_interval) {
                        error_log("KSM DEBUG - Not enough time remaining today ($remaining_time_today minutes), moving to next day");
                        $can_schedule_today = false;
                        $current_day_offset = 1; // Move to tomorrow
                        $posts_scheduled_for_current_day = 0;
                        // Recalculate target for tomorrow
                        $target_timestamp = $this->get_next_valid_day($current_day_offset, $days_active, $can_schedule_today);
                        $target_date = wp_date('Y-m-d', $target_timestamp);
                        $day_start_time = $start_time; // Reset to original start time for tomorrow
                        $posts_to_generate = $posts_per_day;
                        error_log("KSM DEBUG - Moved to tomorrow due to insufficient time: $target_date");
                    }
                    
                    $posts_to_generate = min($posts_per_day, floor($remaining_time_today / $min_interval) + 1);
                    error_log("KSM DEBUG - Today can fit $posts_to_generate posts (remaining time: $remaining_time_today minutes)");
                }
                
                $daily_schedules[$target_date] = $this->generate_random_times(
                    $posts_to_generate,
                    $day_start_time,
                    $end_time,
                    $min_interval
                );
                error_log("KSM DEBUG - Generated " . count($daily_schedules[$target_date]) . " times for $target_date");
            }
            
            // Check if we have a time slot available for this post
            if (!isset($daily_schedules[$target_date][$posts_scheduled_for_current_day])) {
                error_log("KSM DEBUG - ERROR: No time slot available for post index $posts_scheduled_for_current_day on $target_date");
                error_log("KSM DEBUG - Available slots: " . count($daily_schedules[$target_date]) . ", Requested index: $posts_scheduled_for_current_day");
                continue;
            }
            
            $scheduled_time_str = $daily_schedules[$target_date][$posts_scheduled_for_current_day];
            error_log("KSM DEBUG - Assigned time slot: $scheduled_time_str");
            
            // FIXED TIMESTAMP CALCULATION: Create proper datetime string and convert to timestamp
            $scheduled_datetime_str = $target_date . ' ' . $scheduled_time_str;
            error_log("KSM DEBUG - Full datetime string: $scheduled_datetime_str");
            
            // Use WordPress timezone-aware conversion
            $wp_timezone = wp_timezone();
            $scheduled_datetime = DateTime::createFromFormat('Y-m-d g:i A', $scheduled_datetime_str, $wp_timezone);
            
            if (!$scheduled_datetime) {
                error_log("KSM DEBUG - ERROR: Failed to parse datetime: $scheduled_datetime_str");
                continue;
            }
            
            $scheduled_timestamp = $scheduled_datetime->getTimestamp();
            error_log("KSM DEBUG - Parsed timestamp: $scheduled_timestamp (" . wp_date('Y-m-d H:i:s', $scheduled_timestamp) . ")");
            
            // Convert to MySQL format for WordPress
            $scheduled_time_mysql = $scheduled_datetime->format('Y-m-d H:i:s');
            $scheduled_time_gmt = get_gmt_from_date($scheduled_time_mysql);
            
            error_log("KSM DEBUG - Final scheduling data for post {$post->ID}:");
            error_log("KSM DEBUG - Local time: $scheduled_time_mysql");
            error_log("KSM DEBUG - GMT time: $scheduled_time_gmt");
            error_log("KSM DEBUG - Timestamp: $scheduled_timestamp");
            error_log("KSM DEBUG - Current WP timestamp: $current_wp_timestamp");
            error_log("KSM DEBUG - Time difference: " . ($scheduled_timestamp - $current_wp_timestamp) . " seconds in future");
            
            // PROPER WORDPRESS SCHEDULING: Use WordPress functions with proper future timestamps
            error_log("KSM DEBUG - Using WordPress functions for proper post scheduling");
            error_log("KSM DEBUG - Scheduling data: ID={$post->ID}, status=future, date=$scheduled_time_mysql, date_gmt=$scheduled_time_gmt");
            
            // Use wp_update_post with proper future timestamps (no hook workarounds needed)
            $post_data = array(
                'ID' => $post->ID,
                'post_status' => 'future',
                'post_date' => $scheduled_time_mysql,
                'post_date_gmt' => $scheduled_time_gmt,
                'edit_date' => true // This tells WordPress we're explicitly setting the date
            );
            
            $update_result = wp_update_post($post_data, true);
            
            if (is_wp_error($update_result)) {
                error_log("KSM DEBUG - ERROR: wp_update_post failed for post {$post->ID}: " . $update_result->get_error_message());
                continue;
            }
            
            if ($update_result === 0) {
                error_log("KSM DEBUG - ERROR: wp_update_post returned 0 for post {$post->ID}");
                continue;
            }
            
            // Schedule the actual publication using WordPress cron
            $publish_hook = 'ksm_ps_publish_post_' . $post->ID;
            wp_schedule_single_event($scheduled_timestamp, $publish_hook, array($post->ID));
            
            error_log("KSM DEBUG - Scheduled publication hook '$publish_hook' for timestamp $scheduled_timestamp");
            
            // Verify the post was updated correctly
            $updated_post = get_post($post->ID);
            error_log("KSM DEBUG - Post update result for {$post->ID}: " . ($updated_post ? $updated_post->post_status : 'NULL'));
            
            if ($updated_post && $updated_post->post_status === 'future') {
                $scheduled_count++;
                $posts_scheduled_for_current_day++;
                
                // Add progress tracking for manual runs
                if (!$is_cron_run) {
                    $post_title = strlen($post->post_title) > 30 ? substr($post->post_title, 0, 30) . '...' : $post->post_title;
                    $progress_report[] = "  ✓ Assigned post #{$scheduled_count}: \"{$post_title}\" to {$scheduled_time_str}";
                }
                
                error_log("KSM DEBUG - ✓ Successfully scheduled post {$post->ID} for $scheduled_time_mysql");
                error_log("KSM DEBUG - Updated counters: scheduled_count=$scheduled_count, posts_for_current_day=$posts_scheduled_for_current_day");
            } else {
                error_log("KSM DEBUG - ✗ FAILED: Post {$post->ID} status verification failed");
                error_log("KSM DEBUG - Expected: 'future', Actual: " . ($updated_post ? $updated_post->post_status : 'unknown'));
                if ($updated_post) {
                    error_log("KSM DEBUG - Post date: " . $updated_post->post_date);
                    error_log("KSM DEBUG - Post date GMT: " . $updated_post->post_date_gmt);
                }
            }
        }
        
        $message = "Successfully scheduled $scheduled_count posts.";
        if (!$is_cron_run) {
            $message .= " (Manual scheduling - daily limits bypassed)";
            
            // Add detailed progress report for manual runs
            if (!empty($progress_report)) {
                $message .= "\n\n📊 SCHEDULING PROGRESS REPORT:\n";
                $message .= "Total posts processed: {$total_posts_to_schedule}\n";
                $message .= "Posts successfully scheduled: {$scheduled_count}\n\n";
                $message .= "Day-by-day breakdown:\n";
                $message .= implode("\n", $progress_report);
                $message .= "\n\n✅ All posts have been scheduled according to your daily limits and time windows.";
            }
        } else {
            $message .= " (Automatic scheduling - daily limits applied)";
        }
        error_log("KSM DEBUG - Final result: $message");
        
        return array('success' => true, 'message' => $message);
    }
    
    /**
     * Get the next valid scheduling day based on active days
     * 
     * @param int $day_offset Number of days from the starting point (0 = today if can_schedule_today, otherwise tomorrow)
     * @param array $days_active Array of active day names
     * @param bool $can_schedule_today Whether we can schedule posts today
     * @return int Timestamp for the target day
     * @since 1.1.8
     */
    private function get_next_valid_day($day_offset, $days_active, $can_schedule_today = false) {
        // Use WordPress current time consistently
        $current_wp_timestamp = current_time('timestamp');
        
        // Calculate the base timestamp
        if ($can_schedule_today && $day_offset === 0) {
            // If we can schedule today and offset is 0, use today
            $target_timestamp = $current_wp_timestamp;
        } else {
            // Calculate the correct offset:
            // - If can_schedule_today is true: day_offset represents days from today
            // - If can_schedule_today is false: day_offset represents days from tomorrow
            $base_offset = $can_schedule_today ? $day_offset : ($day_offset + 1);
            $target_timestamp = $current_wp_timestamp + ($base_offset * 24 * 60 * 60);
        }
        
        // If no active days specified, use the calculated day
        if (empty($days_active)) {
            return $target_timestamp;
        }
        
        // Find the next valid day that matches active days
        $attempts = 0;
        while ($attempts < 14) { // Prevent infinite loop, check up to 2 weeks
            // Use WordPress timezone for day calculation
            $day_name = strtolower(wp_date('l', $target_timestamp));
            
            if (in_array($day_name, $days_active)) {
                return $target_timestamp;
            }
            
            // Move to next day
            $target_timestamp += 24 * 60 * 60;
            $attempts++;
        }
        
        // Fallback: return the original calculated day
        return $current_wp_timestamp + ($day_offset * 24 * 60 * 60);
    }
    
    /**
     * Generate random times within range
     * 
     * @param int $count Number of times to generate
     * @param string $start_time Start time (HH:MM format)
     * @param string $end_time End time (HH:MM format)
     * @param int $min_interval Minimum interval in minutes
     * @return array Array of times in HH:MM format
     * @since 1.0.0
     */
    private function generate_random_times($count, $start_time, $end_time, $min_interval) {
        $start_minutes = $this->time_to_minutes($start_time);
        $end_minutes = $this->time_to_minutes($end_time);
        
        $available_minutes = $end_minutes - $start_minutes;
        $total_interval_time = ($count - 1) * $min_interval;
        
        if ($total_interval_time >= $available_minutes) {
            // Not enough time for all posts with minimum interval
            return array();
        }
        
        $times = array();
        $used_times = array();
        
        for ($i = 0; $i < $count; $i++) {
            $attempts = 0;
            do {
                $random_minutes = rand($start_minutes, $end_minutes - 1);
                $attempts++;
            } while ($this->is_time_too_close($random_minutes, $used_times, $min_interval) && $attempts < 100);
            
            if ($attempts < 100) {
                $used_times[] = $random_minutes;
                $times[] = $this->minutes_to_time($random_minutes);
            }
        }
        
        return $times;
    }
    
    /**
     * Convert minutes since midnight to 12-hour time string
     * 
     * @param int $minutes Minutes since midnight
     * @return string Time in 12-hour format (e.g., "9:00 AM")
     * @since 1.0.0
     */
    private function minutes_to_time($minutes) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        // Convert to 12-hour format
        $period = ($hours >= 12) ? 'PM' : 'AM';
        $display_hours = ($hours == 0) ? 12 : (($hours > 12) ? $hours - 12 : $hours);
        
        return sprintf('%d:%02d %s', $display_hours, $mins, $period);
    }
    
    /**
     * Check if time is too close to existing times
     * 
     * @param int $time_minutes Time in minutes
     * @param array $used_times Array of used times in minutes
     * @param int $min_interval Minimum interval in minutes
     * @return bool True if too close
     * @since 1.0.0
     */
    private function is_time_too_close($time_minutes, $used_times, $min_interval) {
        foreach ($used_times as $used_time) {
            if (abs($time_minutes - $used_time) < $min_interval) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get scheduling preview data for admin display
     * 
     * @return array Scheduling preview data
     * @since 1.4.8
     */
    private function get_scheduling_preview() {
        $options = get_option($this->option_name, array());
        $posts_per_day = $options['posts_per_day'] ?? 5;
        $start_time = $options['start_time'] ?? '9:00 AM';
        $end_time = $options['end_time'] ?? '6:00 PM';
        $min_interval = $options['min_interval'] ?? 30;
        $days_active = $options['days_active'] ?? array('monday', 'tuesday', 'wednesday', 'thursday', 'friday');
        
        // Get posts waiting to be scheduled
        $monitored_count = $this->get_monitored_posts_count();
        
        $preview = array(
            'posts_waiting' => $monitored_count,
            'posts_per_day' => $posts_per_day,
            'estimated_days' => $monitored_count > 0 ? ceil($monitored_count / $posts_per_day) : 0,
            'time_window' => $start_time . ' - ' . $end_time,
            'min_interval' => $min_interval,
            'active_days' => ucwords(implode(', ', $days_active)),
            'daily_preview' => array(),
            'warnings' => array()
        );
        
        // Calculate time window validation
        $start_minutes = $this->time_to_minutes($start_time);
        $end_minutes = $this->time_to_minutes($end_time);
        $window_minutes = $end_minutes - $start_minutes;
        $required_minutes = ($posts_per_day - 1) * $min_interval;
        
        // Add warnings
        if ($window_minutes < $required_minutes) {
            $preview['warnings'][] = sprintf(
                __('⚠️ Warning: Your time window (%d minutes) may be too narrow for %d posts with %d minute intervals (requires %d minutes)', 'ksm-post-scheduler'),
                $window_minutes,
                $posts_per_day,
                $min_interval,
                $required_minutes
            );
        }
        
        if ($monitored_count === 0) {
            $preview['warnings'][] = __('✓ No posts currently need scheduling', 'ksm-post-scheduler');
        }
        
        if (!($options['enabled'] ?? false)) {
            $preview['warnings'][] = __('⚠️ Scheduler is disabled - posts will not be automatically scheduled', 'ksm-post-scheduler');
        }
        
        // Generate 5-day preview if there are posts to schedule
        if ($monitored_count > 0 && ($options['enabled'] ?? false)) {
            $current_day_offset = 0;
            $posts_remaining = $monitored_count;
            $can_schedule_today = true; // Assume we can schedule today for preview
            
            for ($day = 0; $day < 5 && $posts_remaining > 0; $day++) {
                $target_timestamp = $this->get_next_valid_day($current_day_offset, $days_active, $can_schedule_today);
                $target_date = wp_date('l, M j', $target_timestamp);
                
                $posts_for_day = min($posts_per_day, $posts_remaining);
                
                $preview['daily_preview'][] = array(
                    'day' => $day === 0 ? __('Today', 'ksm-post-scheduler') : $target_date,
                    'posts_count' => $posts_for_day,
                    'time_window' => $start_time . ' - ' . $end_time
                );
                
                $posts_remaining -= $posts_for_day;
                $current_day_offset++;
                $can_schedule_today = false; // After first day, we're not scheduling for "today"
            }
            
            if ($posts_remaining > 0) {
                $preview['daily_preview'][] = array(
                    'day' => sprintf(__('... and %d more days', 'ksm-post-scheduler'), ceil($posts_remaining / $posts_per_day)),
                    'posts_count' => $posts_remaining,
                    'time_window' => ''
                );
            }
        }
        
        return $preview;
    }

    
    /**
     * AJAX handler for run now button
     * 
     * @since 1.0.0
     */
    public function ajax_run_now() {
        check_ajax_referer('ksm_ps_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'ksm-post-scheduler'));
        }
        
        $result = $this->schedule_posts(false); // Pass false to indicate manual testing
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for getting current status
     * 
     * @since 1.0.0
     */
    public function ajax_get_status() {
        check_ajax_referer('ksm_ps_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'ksm-post-scheduler'));
        }
        
        $options = get_option($this->option_name);
        $scheduling_preview = $this->get_scheduling_preview();
        
        $data = array(
            'monitored_count' => $this->get_monitored_posts_count(),
            'upcoming_posts' => $this->get_upcoming_scheduled_posts(),
            'options' => $options,
            'scheduling_preview' => $scheduling_preview,
            'last_cron_run' => $options['last_cron_run'] ?? null,
            'next_cron_run' => wp_next_scheduled('ksm_ps_daily_cron')
        );
        
        wp_send_json_success($data);
    }
}

// Initialize the plugin
KSM_PS_Main::get_instance();