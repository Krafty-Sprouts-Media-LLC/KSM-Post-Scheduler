<?php
/**
 * Plugin Name: KSM Post Scheduler
 * Plugin URI: https://kraftysprouts.com
 * Description: Automatically schedules posts from a specific status to publish at random times
 * Version: 1.1.4
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
 * @version 1.1.4
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
define('KSM_PS_VERSION', '1.1.4');
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
            'start_time' => '09:00',
            'end_time' => '18:00',
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
        
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'ksm-ps-admin',
            KSM_PS_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            KSM_PS_VERSION,
            true
        );
        
        wp_localize_script('ksm-ps-admin', 'ksm_ps_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ksm_ps_nonce'),
            'strings' => array(
                'running' => __('Running...', 'ksm-post-scheduler'),
                'success' => __('Scheduler executed successfully!', 'ksm-post-scheduler'),
                'error' => __('Error occurred while running scheduler.', 'ksm-post-scheduler')
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
        $sanitized = array();
        
        $sanitized['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : false;
        $sanitized['post_status'] = sanitize_text_field($input['post_status'] ?? 'draft');
        $sanitized['posts_per_day'] = absint($input['posts_per_day'] ?? 5);
        
        // Handle time fields - use 24-hour format for storage
        $sanitized['start_time'] = sanitize_text_field($input['start_time'] ?? '09:00');
        $sanitized['end_time'] = sanitize_text_field($input['end_time'] ?? '18:00');
        
        // If 12-hour display format was provided, convert from display format
        if (isset($input['start_time_display']) && !empty($input['start_time_display'])) {
            $converted_start = $this->convert_12_to_24($input['start_time_display']);
            if ($converted_start) {
                $sanitized['start_time'] = $converted_start;
            }
        }
        
        if (isset($input['end_time_display']) && !empty($input['end_time_display'])) {
            $converted_end = $this->convert_12_to_24($input['end_time_display']);
            if ($converted_end) {
                $sanitized['end_time'] = $converted_end;
            }
        }
        
        $sanitized['min_interval'] = absint($input['min_interval'] ?? 30);
        
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
        
        return $sanitized;
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
     * Schedule posts function
     * 
     * @return array Result array with success status and message
     * @since 1.0.0
     */
    private function schedule_posts() {
        $options = get_option($this->option_name, array());
        
        // Get posts to schedule
        $posts = get_posts(array(
            'post_status' => $options['post_status'] ?? 'draft',
            'numberposts' => $options['posts_per_day'] ?? 5,
            'post_type' => 'post',
            'orderby' => 'date',
            'order' => 'ASC'
        ));
        
        if (empty($posts)) {
            return array('success' => false, 'message' => 'No posts found to schedule.');
        }
        
        // Get time settings
        $start_time = $options['start_time'] ?? '09:00';
        $end_time = $options['end_time'] ?? '18:00';
        
        // DEBUG: Log current settings and time
        $current_wp_time = current_time('Y-m-d H:i:s');
        $current_server_time = date('Y-m-d H:i:s');
        $current_timestamp = current_time('timestamp');
        error_log("KSM DEBUG - Current WP Time: $current_wp_time");
        error_log("KSM DEBUG - Current Server Time: $current_server_time");
        error_log("KSM DEBUG - Current Timestamp: $current_timestamp");
        error_log("KSM DEBUG - Start Time Setting: $start_time");
        error_log("KSM DEBUG - End Time Setting: $end_time");
        
        // Generate random times
        $times = $this->generate_random_times(
            count($posts),
            $start_time,
            $end_time,
            $options['min_interval'] ?? 30
        );
        error_log("KSM DEBUG - Generated Times: " . implode(', ', $times));
        
        $scheduled_count = 0;
        foreach ($posts as $index => $post) {
            if (isset($times[$index])) {
                // Get current time components
                $current_time_24 = current_time('H:i');
                $current_timestamp = current_time('timestamp');
                
                // Create scheduled datetime for today
                $today = current_time('Y-m-d');
                $scheduled_time_today = $today . ' ' . $times[$index] . ':00';
                $scheduled_timestamp_today = strtotime($scheduled_time_today);
                
                // If the scheduled time for today has already passed, schedule for tomorrow
                if ($scheduled_timestamp_today <= $current_timestamp) {
                    $tomorrow = date('Y-m-d', strtotime('+1 day', $current_timestamp));
                    $scheduled_time = $tomorrow . ' ' . $times[$index] . ':00';
                    $use_today = false;
                } else {
                    $scheduled_time = $scheduled_time_today;
                    $use_today = true;
                }
                
                error_log("KSM DEBUG - Post {$post->ID}: Generated time {$times[$index]}, Current time $current_time_24, Use today: " . ($use_today ? 'YES' : 'NO') . ", Final time: $scheduled_time");
                
                // Convert to GMT for WordPress using the proper WordPress function
                $scheduled_time_gmt = get_gmt_from_date($scheduled_time);
                
                // Verify the scheduled time is in the future
                $scheduled_timestamp = strtotime($scheduled_time);
                if ($scheduled_timestamp <= $current_timestamp) {
                    error_log("KSM DEBUG - WARNING: Post {$post->ID} scheduled time ($scheduled_time) is not in the future! Current: $current_wp_time");
                    // Force schedule for tomorrow if there's still an issue
                    $tomorrow = date('Y-m-d', strtotime('+1 day', $current_timestamp));
                    $scheduled_time = $tomorrow . ' ' . $times[$index] . ':00';
                    $scheduled_time_gmt = get_gmt_from_date($scheduled_time);
                    error_log("KSM DEBUG - Forced to tomorrow: $scheduled_time");
                }
                
                // Update post with proper scheduling
                $update_data = array(
                    'ID' => $post->ID,
                    'post_status' => 'future',
                    'post_date' => $scheduled_time,
                    'post_date_gmt' => $scheduled_time_gmt
                );
                
                // Additional safety check: ensure we're not setting a past date
                $final_timestamp = strtotime($scheduled_time);
                if ($final_timestamp <= current_time('timestamp')) {
                    error_log("KSM DEBUG - CRITICAL: Attempting to schedule post {$post->ID} in the past! Skipping.");
                    continue;
                }
                
                error_log("KSM DEBUG - About to update post {$post->ID} with data: " . print_r($update_data, true));
                
                $result = wp_update_post($update_data, true); // Enable error return
                
                if (!is_wp_error($result) && $result !== 0) {
                    $scheduled_count++;
                    error_log("KSM DEBUG - Successfully scheduled post {$post->ID} for $scheduled_time (GMT: $scheduled_time_gmt)");
                    
                    // Double-check the post status after update
                    $updated_post = get_post($post->ID);
                    error_log("KSM DEBUG - Post {$post->ID} status after update: {$updated_post->post_status}, date: {$updated_post->post_date}");
                    
                    // If the post is not in 'future' status, something went wrong
                    if ($updated_post->post_status !== 'future') {
                        error_log("KSM DEBUG - ERROR: Post {$post->ID} was not set to 'future' status! Current status: {$updated_post->post_status}");
                    }
                } else {
                    $error_message = is_wp_error($result) ? $result->get_error_message() : 'Unknown error';
                    error_log("KSM DEBUG - Failed to schedule post {$post->ID}: " . $error_message);
                }
            }
        }
        
        return array(
            'success' => true,
            'message' => sprintf('Successfully scheduled %d posts.', $scheduled_count)
        );
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
     * Convert time string to minutes
     * 
     * @param string $time Time in HH:MM format
     * @return int Minutes since midnight
     * @since 1.0.0
     */
    private function time_to_minutes($time) {
        list($hours, $minutes) = explode(':', $time);
        return ($hours * 60) + $minutes;
    }
    
    /**
     * Convert minutes to time string
     * 
     * @param int $minutes Minutes since midnight
     * @return string Time in HH:MM format
     * @since 1.0.0
     */
    private function minutes_to_time($minutes) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
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
     * Convert 24-hour time to 12-hour format
     * 
     * @param string $time_24 Time in HH:MM format (24-hour)
     * @return string Time in 12-hour format (h:MM AM/PM)
     * @since 1.1.2
     */
    private function convert_24_to_12($time_24) {
        $time = DateTime::createFromFormat('H:i', $time_24);
        return $time ? $time->format('g:i A') : $time_24;
    }
    
    /**
     * Convert 12-hour time to 24-hour format
     * 
     * @param string $time_12 Time in 12-hour format (h:MM AM/PM)
     * @return string Time in HH:MM format (24-hour)
     * @since 1.1.2
     */
    private function convert_12_to_24($time_12) {
        $time = DateTime::createFromFormat('g:i A', $time_12);
        if (!$time) {
            // Try alternative format
            $time = DateTime::createFromFormat('h:i A', $time_12);
        }
        return $time ? $time->format('H:i') : $time_12;
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