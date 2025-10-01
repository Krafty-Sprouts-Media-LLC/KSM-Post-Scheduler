<?php
/**
 * Plugin Name: KSM Post Scheduler
 * Plugin URI: https://kraftysprouts.com
 * Description: Automatically schedules posts from a specific status to publish at random times
 * Version: 1.4.0
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
 * @version 1.4.0
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
define('KSM_PS_VERSION', '1.4.0');
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
            
            // Force update time values to 12-hour format (since no existing users)
            $updated_options['start_time'] = '9:00 AM';
            $updated_options['end_time'] = '6:00 PM';
            
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
                
                if ($required_minutes >= $available_minutes) {
                    $validation_errors[] = __('Not enough time between start and end time for the specified number of posts with minimum intervals.', 'ksm-post-scheduler');
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
        
        $this->schedule_posts();
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
     * Schedule posts function
     * 
     * @return array Result array with success status and message
     * @since 1.0.0
     */
    private function schedule_posts() {
        $options = get_option($this->option_name, array());
        $posts_per_day = $options['posts_per_day'] ?? 5;
        
        // Get posts to schedule - limit to a reasonable batch size to prevent overwhelming
        $posts = get_posts(array(
            'post_status' => $options['post_status'] ?? 'draft',
            'numberposts' => $posts_per_day * 7, // Schedule up to 7 days worth of posts at a time
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
        error_log("KSM DEBUG - Total Posts to Schedule: " . count($posts));
        
        $scheduled_count = 0;
        $current_day_offset = 0;
        $posts_scheduled_for_current_day = 0;
        
        // Determine if we can schedule posts today
        $can_schedule_today = false;
        $today_name = strtolower(current_time('l'));
        
        if (in_array($today_name, $days_active)) {
            // Check if we're still within scheduling hours and have buffer time
            $buffer_minutes = 30; // 30-minute buffer
            $latest_scheduling_time = $end_minutes - $buffer_minutes;
            
            if ($current_time_minutes < $latest_scheduling_time) {
                // Check how many posts we could still fit today
                $remaining_time_today = $latest_scheduling_time - max($current_time_minutes, $start_minutes);
                $possible_posts_today = min($posts_per_day, floor($remaining_time_today / $min_interval) + 1);
                
                if ($possible_posts_today > 0) {
                    $can_schedule_today = true;
                    error_log("KSM DEBUG - Can schedule $possible_posts_today posts today (remaining time: $remaining_time_today minutes)");
                } else {
                    error_log("KSM DEBUG - Cannot schedule posts today - not enough time remaining");
                }
            } else {
                error_log("KSM DEBUG - Cannot schedule posts today - past scheduling hours");
            }
        } else {
            error_log("KSM DEBUG - Cannot schedule posts today - not an active day ($today_name)");
        }
        
        // If we can't schedule today, start from tomorrow
        if (!$can_schedule_today) {
            $current_day_offset = 1;
            error_log("KSM DEBUG - Starting from tomorrow (day offset: $current_day_offset)");
        } else {
            error_log("KSM DEBUG - Starting from today (day offset: $current_day_offset)");
        }
        
        // Pre-generate schedules for each day as needed
        $daily_schedules = array();
        
        foreach ($posts as $index => $post) {
            // Check if we need to move to the next day
            if ($posts_scheduled_for_current_day >= $posts_per_day) {
                $current_day_offset++;
                $posts_scheduled_for_current_day = 0;
                error_log("KSM DEBUG - Moving to next day (offset: $current_day_offset) - current day reached limit of $posts_per_day posts");
            }
            
            // Find the next valid scheduling day
            $target_timestamp = $this->get_next_valid_day($current_day_offset, $days_active, $can_schedule_today);
            $target_date = wp_date('Y-m-d', $target_timestamp);
            
            // Generate times for this day if not already done
            if (!isset($daily_schedules[$target_date])) {
                // For today, adjust start time if needed
                $day_start_time = $start_time;
                if ($current_day_offset === 0 && $can_schedule_today) {
                    // For today, start from current time + buffer if it's later than start time
                    $current_plus_buffer = $current_time_minutes + 30; // 30-minute buffer
                    if ($current_plus_buffer > $start_minutes) {
                        $adjusted_hour = floor($current_plus_buffer / 60);
                        $adjusted_minute = $current_plus_buffer % 60;
                        $day_start_time = sprintf('%d:%02d %s', 
                            $adjusted_hour > 12 ? $adjusted_hour - 12 : ($adjusted_hour == 0 ? 12 : $adjusted_hour),
                            $adjusted_minute,
                            $adjusted_hour >= 12 ? 'PM' : 'AM'
                        );
                        error_log("KSM DEBUG - Adjusted start time for today: $day_start_time");
                    }
                }
                
                $daily_schedules[$target_date] = $this->generate_random_times(
                    $posts_per_day,
                    $day_start_time,
                    $end_time,
                    $min_interval
                );
                error_log("KSM DEBUG - Generated " . count($daily_schedules[$target_date]) . " times for $target_date");
            }
            
            // Get the next available time slot for this day
            if (!isset($daily_schedules[$target_date][$posts_scheduled_for_current_day])) {
                error_log("KSM DEBUG - ERROR: No time slot available for post index $posts_scheduled_for_current_day on $target_date");
                continue;
            }
            
            $scheduled_time_str = $daily_schedules[$target_date][$posts_scheduled_for_current_day];
            
            // Create full scheduled datetime
            $scheduled_datetime_str = $target_date . ' ' . $scheduled_time_str;
            
            // Convert to timestamp using WordPress timezone
            $scheduled_timestamp = wp_date('U', strtotime($scheduled_datetime_str . ' ' . wp_timezone_string()));
            
            if (!$scheduled_timestamp) {
                error_log("KSM DEBUG - ERROR: Failed to parse datetime: $scheduled_datetime_str");
                continue;
            }
            
            // Final safety check: ensure we're scheduling in the future
            if ($scheduled_timestamp <= $current_wp_timestamp) {
                error_log("KSM DEBUG - ERROR: Calculated time is in the past! Scheduled: $scheduled_timestamp, Current: $current_wp_timestamp");
                continue;
            }
            
            // Convert to MySQL format for WordPress
            $scheduled_time_mysql = wp_date('Y-m-d H:i:s', $scheduled_timestamp);
            $scheduled_time_gmt = get_gmt_from_date($scheduled_time_mysql);
            
            error_log("KSM DEBUG - Post {$post->ID} '{$post->post_title}': Scheduling for $target_date at $scheduled_time_str");
            error_log("KSM DEBUG - Local time: $scheduled_time_mysql, GMT: $scheduled_time_gmt");
            
            // Update the post
            $result = wp_update_post(array(
                'ID' => $post->ID,
                'post_status' => 'future',
                'post_date' => $scheduled_time_mysql,
                'post_date_gmt' => $scheduled_time_gmt
            ));
            
            if (is_wp_error($result)) {
                error_log("KSM DEBUG - ERROR updating post {$post->ID}: " . $result->get_error_message());
                continue;
            }
            
            // Verify the post was updated correctly
            $updated_post = get_post($post->ID);
            if ($updated_post && $updated_post->post_status === 'future') {
                $scheduled_count++;
                $posts_scheduled_for_current_day++;
                error_log("KSM DEBUG - Successfully scheduled post {$post->ID} for $scheduled_time_mysql");
            } else {
                error_log("KSM DEBUG - ERROR: Post {$post->ID} status verification failed. Status: " . ($updated_post ? $updated_post->post_status : 'unknown'));
            }
        }
        
        $message = "Successfully scheduled $scheduled_count posts.";
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
            // Otherwise, calculate from tomorrow + offset
            $base_offset = $can_schedule_today ? $day_offset : ($day_offset);
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
     * AJAX handler for run now button
     * 
     * @since 1.0.0
     */
    public function ajax_run_now() {
        check_ajax_referer('ksm_ps_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'ksm-post-scheduler'));
        }
        
        $result = $this->schedule_posts();
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
        
        $data = array(
            'monitored_count' => $this->get_monitored_posts_count(),
            'upcoming_posts' => $this->get_upcoming_scheduled_posts()
        );
        
        wp_send_json_success($data);
    }
}

// Initialize the plugin
KSM_PS_Main::get_instance();