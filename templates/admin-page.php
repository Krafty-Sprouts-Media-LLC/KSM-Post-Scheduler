<?php
/**
 * Admin Page Template
 * 
 * @package KSM_Post_Scheduler
 * @version 1.0.0
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php 
    // WordPress automatically handles success messages for settings pages
    // Only display settings_errors() if there are actual validation errors
    if (get_settings_errors()) {
        $errors = get_settings_errors();
        foreach ($errors as $error) {
            if ($error['type'] === 'error') {
                settings_errors();
                break;
            }
        }
    }
    ?>
    
    <div class="ksm-ps-admin-container">
        <div class="ksm-ps-main-content">
            <form method="post" action="options.php">
                <?php
                settings_fields('ksm_ps_settings_group');
                do_settings_sections('ksm_ps_settings_group');
                ?>
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <!-- Enable/Disable Toggle -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_enabled"><?php _e('Enable Scheduler', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <label class="ksm-ps-toggle">
                                    <input type="checkbox" 
                                           id="ksm_ps_enabled" 
                                           name="<?php echo esc_attr($this->option_name); ?>[enabled]" 
                                           value="1" 
                                           <?php checked(isset($options['enabled']) ? $options['enabled'] : false, true); ?>>
                                    <span class="ksm-ps-toggle-slider"></span>
                                </label>
                                <p class="description"><?php _e('Enable or disable the automatic post scheduler.', 'ksm-post-scheduler'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Post Status to Monitor -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_post_status"><?php _e('Post Status to Monitor', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <select id="ksm_ps_post_status" name="<?php echo esc_attr($this->option_name); ?>[post_status]">
                                    <?php foreach ($post_statuses as $status_key => $status_obj): ?>
                                        <option value="<?php echo esc_attr($status_key); ?>" 
                                                <?php selected($options['post_status'] ?? 'draft', $status_key); ?>>
                                            <?php echo esc_html($status_obj->label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="draft" <?php selected($options['post_status'] ?? 'draft', 'draft'); ?>>
                                        <?php _e('Draft', 'ksm-post-scheduler'); ?>
                                    </option>
                                </select>
                                <p class="description"><?php _e('Select which post status to monitor for scheduling.', 'ksm-post-scheduler'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Posts Per Day -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_posts_per_day"><?php _e('Posts Per Day', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="ksm_ps_posts_per_day" 
                                       name="<?php echo esc_attr($this->option_name); ?>[posts_per_day]" 
                                       value="<?php echo esc_attr($options['posts_per_day'] ?? 5); ?>" 
                                       min="1" 
                                       max="50" 
                                       class="small-text">
                                <p class="description"><?php _e('Maximum number of posts to schedule per day.', 'ksm-post-scheduler'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Start Time -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_start_time"><?php _e('Start Time', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <?php
                                $start_time_12 = $options['start_time'];
                                ?>
                                <input type="text" 
                                       id="ksm_ps_start_time" 
                                       name="<?php echo esc_attr($this->option_name); ?>[start_time]" 
                                       value="<?php echo esc_attr($start_time_12); ?>"
                                       placeholder="9:00 AM"
                                       pattern="^(1[0-2]|[1-9]):[0-5][0-9]\s?(AM|PM|am|pm)$"
                                       title="Enter time in 12-hour format (e.g., 9:00 AM)">
                                <p class="description"><?php _e('Earliest time to schedule posts (12-hour format, e.g., 9:00 AM).', 'ksm-post-scheduler'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- End Time -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_end_time"><?php _e('End Time', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <?php
                                $end_time_12 = $options['end_time'];
                                ?>
                                <input type="text" 
                                       id="ksm_ps_end_time" 
                                       name="<?php echo esc_attr($this->option_name); ?>[end_time]" 
                                       value="<?php echo esc_attr($end_time_12); ?>"
                                       placeholder="6:00 PM"
                                       pattern="^(1[0-2]|[1-9]):[0-5][0-9]\s?(AM|PM|am|pm)$"
                                       title="Enter time in 12-hour format (e.g., 6:00 PM)">
                                <p class="description"><?php _e('Latest time to schedule posts (12-hour format, e.g., 6:00 PM).', 'ksm-post-scheduler'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Days Active -->
                        <tr>
                            <th scope="row"><?php _e('Days Active', 'ksm-post-scheduler'); ?></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php _e('Days Active', 'ksm-post-scheduler'); ?></legend>
                                    <?php
                                    $days = array(
                                        'monday' => __('Monday', 'ksm-post-scheduler'),
                                        'tuesday' => __('Tuesday', 'ksm-post-scheduler'),
                                        'wednesday' => __('Wednesday', 'ksm-post-scheduler'),
                                        'thursday' => __('Thursday', 'ksm-post-scheduler'),
                                        'friday' => __('Friday', 'ksm-post-scheduler'),
                                        'saturday' => __('Saturday', 'ksm-post-scheduler'),
                                        'sunday' => __('Sunday', 'ksm-post-scheduler')
                                    );
                                    
                                    $active_days = $options['days_active'] ?? array('monday', 'tuesday', 'wednesday', 'thursday', 'friday');
                                    
                                    foreach ($days as $day_key => $day_label):
                                    ?>
                                        <label>
                                            <input type="checkbox" 
                                                   name="<?php echo esc_attr($this->option_name); ?>[days_active][]" 
                                                   value="<?php echo esc_attr($day_key); ?>" 
                                                   <?php checked(in_array($day_key, $active_days), true); ?>>
                                            <?php echo esc_html($day_label); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                    <p class="description"><?php _e('Select which days the scheduler should be active.', 'ksm-post-scheduler'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <!-- Minimum Interval -->
                        <tr>
                            <th scope="row">
                                <label for="ksm_ps_min_interval"><?php _e('Minimum Interval Between Posts', 'ksm-post-scheduler'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="ksm_ps_min_interval" 
                                       name="<?php echo esc_attr($this->option_name); ?>[min_interval]" 
                                       value="<?php echo esc_attr($options['min_interval'] ?? 30); ?>" 
                                       min="5" 
                                       max="1440" 
                                       class="small-text">
                                <span><?php _e('minutes', 'ksm-post-scheduler'); ?></span>
                                <p class="description"><?php _e('Minimum time between scheduled posts in minutes.', 'ksm-post-scheduler'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <!-- Manual Scheduling Section -->
            <div class="ksm-ps-manual-run">
                <h2><?php _e('Manual Scheduling', 'ksm-post-scheduler'); ?></h2>
                <p><?php _e('Use this button to manually run the scheduler and schedule all pending draft posts immediately.', 'ksm-post-scheduler'); ?></p>
                <button type="button" id="ksm-ps-run-now" class="button button-primary">
                    <?php _e('Schedule Posts Now', 'ksm-post-scheduler'); ?>
                </button>
                <div id="ksm-ps-run-result" class="ksm-ps-result"></div>
            </div>
        </div>
        
        <!-- Status Sidebar -->
        <div class="ksm-ps-sidebar">
            <!-- Unified Scheduling Overview -->
            <div class="ksm-ps-overview-box">
                <h3><?php _e('Scheduling Overview', 'ksm-post-scheduler'); ?></h3>
                
                <?php if (!empty($scheduling_preview['warnings'])): ?>
                    <div class="ksm-ps-warnings">
                        <?php foreach ($scheduling_preview['warnings'] as $warning): ?>
                            <div class="ksm-ps-warning"><?php echo esc_html($warning); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Status & Timing Section -->
                <div class="ksm-ps-overview-section">
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Scheduler Status:', 'ksm-post-scheduler'); ?></strong>
                        <span class="ksm-ps-status-indicator <?php echo ($options['enabled'] ?? false) ? 'enabled' : 'disabled'; ?>">
                            <?php echo ($options['enabled'] ?? false) ? __('Enabled', 'ksm-post-scheduler') : __('Disabled', 'ksm-post-scheduler'); ?>
                        </span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Last Cron Run:', 'ksm-post-scheduler'); ?></strong>
                        <span class="ksm-ps-time">
                            <?php
                            $last_run = $options['last_cron_run'] ?? null;
                            if ($last_run) {
                                echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_run)));
                            } else {
                                echo '<em>' . __('Never', 'ksm-post-scheduler') . '</em>';
                            }
                            ?>
                        </span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Next Cron Run:', 'ksm-post-scheduler'); ?></strong>
                        <span class="ksm-ps-time">
                            <?php
                            $next_cron = wp_next_scheduled('ksm_ps_daily_cron');
                            if ($next_cron) {
                                echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next_cron));
                            } else {
                                echo '<em>' . __('Not scheduled', 'ksm-post-scheduler') . '</em>';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                
                <!-- Visual Separator -->
                <hr class="ksm-ps-section-divider">
                
                <!-- Queue & Configuration Section -->
                <div class="ksm-ps-overview-section">
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Posts waiting to be scheduled:', 'ksm-post-scheduler'); ?></strong>
                        <span class="ksm-ps-count"><?php echo esc_html($scheduling_preview['posts_waiting']); ?> <?php _e('posts', 'ksm-post-scheduler'); ?></span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Posts per day limit:', 'ksm-post-scheduler'); ?></strong>
                        <span><?php echo esc_html($scheduling_preview['posts_per_day']); ?> <?php _e('posts', 'ksm-post-scheduler'); ?></span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Estimated days needed:', 'ksm-post-scheduler'); ?></strong>
                        <span><?php echo esc_html($scheduling_preview['estimated_days']); ?> <?php _e('days', 'ksm-post-scheduler'); ?></span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Time window:', 'ksm-post-scheduler'); ?></strong>
                        <span><?php echo esc_html($scheduling_preview['time_window']); ?></span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Minimum spacing:', 'ksm-post-scheduler'); ?></strong>
                        <span><?php echo esc_html($scheduling_preview['min_interval']); ?> <?php _e('minutes', 'ksm-post-scheduler'); ?></span>
                    </div>
                    
                    <div class="ksm-ps-overview-item">
                        <strong><?php _e('Active days:', 'ksm-post-scheduler'); ?></strong>
                        <span><?php echo esc_html($scheduling_preview['active_days']); ?></span>
                    </div>
                </div>
                
                <!-- Visual Separator -->
                <hr class="ksm-ps-section-divider">
                
                <!-- Schedule Preview Section -->
                <?php if (!empty($scheduling_preview['daily_preview'])): ?>
                    <div class="ksm-ps-overview-section">
                        <h4 class="ksm-ps-section-title"><?php _e('5-Day Scheduling Preview:', 'ksm-post-scheduler'); ?></h4>
                        <div class="ksm-ps-daily-preview">
                            <?php foreach ($scheduling_preview['daily_preview'] as $day_info): ?>
                                <div class="ksm-ps-day-preview">
                                    <strong><?php echo esc_html($day_info['day']); ?>:</strong>
                                    <span>
                                        <?php echo esc_html($day_info['posts_count']); ?> <?php _e('posts', 'ksm-post-scheduler'); ?>
                                        <?php if (!empty($day_info['time_window'])): ?>
                                            (<?php echo esc_html($day_info['time_window']); ?>)
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($upcoming_posts)): ?>
            <div class="ksm-ps-upcoming-box">
                <h3><?php _e('Upcoming Scheduled Posts', 'ksm-post-scheduler'); ?></h3>
                <div class="ksm-ps-upcoming-list">
                    <?php foreach ($upcoming_posts as $post): ?>
                        <div class="ksm-ps-upcoming-item">
                            <div class="ksm-ps-post-title"><?php echo esc_html($post['title']); ?></div>
                            <div class="ksm-ps-post-time"><?php echo esc_html(date('M j, Y \a\t g:i A', strtotime($post['scheduled_time']))); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="ksm-ps-upcoming-box">
                <h3><?php _e('Upcoming Scheduled Posts', 'ksm-post-scheduler'); ?></h3>
                <p class="ksm-ps-no-posts"><?php _e('No posts currently scheduled.', 'ksm-post-scheduler'); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Refresh Status Button -->
            <button type="button" id="ksm-ps-refresh-status" class="button button-secondary">
                <?php _e('Refresh Status', 'ksm-post-scheduler'); ?>
            </button>
        </div>
    </div>
</div>