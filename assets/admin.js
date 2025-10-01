/**
 * KSM Post Scheduler Admin JavaScript
 * 
 * @package KSM_Post_Scheduler
 * @version 1.0.0
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Admin object
     */
    var KSM_PS_Admin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.validateTimeInputs();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Run Now button
            $('#ksm-ps-run-now').on('click', this.runNow);
            
            // Refresh Status button
            $('#ksm-ps-refresh-status').on('click', this.refreshStatus);
            
            // Time validation and conversion
            $('#ksm_ps_start_time, #ksm_ps_end_time').on('blur', this.handleTimeInput);
            $('#ksm_ps_start_time, #ksm_ps_end_time').on('change', this.validateTimeInputs);
            
            // Posts per day validation
            $('#ksm_ps_posts_per_day').on('change', this.validatePostsPerDay);
            
            // Minimum interval validation
            $('#ksm_ps_min_interval').on('change', this.validateMinInterval);
        },
        
        /**
         * Run scheduler manually
         */
        runNow: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#ksm-ps-run-result');
            
            // Disable button and show loading
            $button.prop('disabled', true).text(ksm_ps_ajax.strings.running);
            $result.removeClass('success error info').hide();
            
            // Add spinner
            $button.prepend('<span class="ksm-ps-spinner"></span>');
            
            // Make AJAX request
            $.ajax({
                url: ksm_ps_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ksm_ps_run_now',
                    nonce: ksm_ps_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').text(response.message).show();
                        // Refresh status after successful run
                        KSM_PS_Admin.refreshStatus();
                    } else {
                        $result.addClass('error').text(response.message || ksm_ps_ajax.strings.error).show();
                    }
                },
                error: function(xhr, status, error) {
                    $result.addClass('error').text(ksm_ps_ajax.strings.error + ' ' + error).show();
                },
                complete: function() {
                    // Re-enable button and remove spinner
                    $button.prop('disabled', false).text('Run Now').find('.ksm-ps-spinner').remove();
                }
            });
        },
        
        /**
         * Refresh status information
         */
        refreshStatus: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            var $button = $('#ksm-ps-refresh-status');
            var originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true).text('Refreshing...').prepend('<span class="ksm-ps-spinner"></span>');
            
            // Make AJAX request
            $.ajax({
                url: ksm_ps_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ksm_ps_get_status',
                    nonce: ksm_ps_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        KSM_PS_Admin.updateStatusDisplay(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to refresh status:', error);
                },
                complete: function() {
                    // Re-enable button and remove spinner
                    $button.prop('disabled', false).text(originalText).find('.ksm-ps-spinner').remove();
                }
            });
        },
        
        /**
         * Update status display with new data
         */
        updateStatusDisplay: function(data) {
            // Update monitored posts count
            $('.ksm-ps-count').text(data.monitored_count);
            
            // Update upcoming posts
            var $upcomingBox = $('.ksm-ps-upcoming-box');
            var $upcomingList = $('.ksm-ps-upcoming-list');
            
            if (data.upcoming_posts && data.upcoming_posts.length > 0) {
                var html = '';
                $.each(data.upcoming_posts, function(index, post) {
                    var date = new Date(post.scheduled_time);
                    var formattedDate = date.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    }) + ' at ' + date.toLocaleTimeString('en-US', {
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });
                    
                    html += '<div class="ksm-ps-upcoming-item">';
                    html += '<div class="ksm-ps-post-title">' + KSM_PS_Admin.escapeHtml(post.title) + '</div>';
                    html += '<div class="ksm-ps-post-time">' + formattedDate + '</div>';
                    html += '</div>';
                });
                
                $upcomingList.html(html);
                $('.ksm-ps-no-posts').hide();
                $upcomingList.show();
            } else {
                $upcomingList.hide();
                if ($('.ksm-ps-no-posts').length === 0) {
                    $upcomingBox.append('<p class="ksm-ps-no-posts">No posts currently scheduled.</p>');
                } else {
                    $('.ksm-ps-no-posts').show();
                }
            }
        },
        
        /**
         * Handle time input conversion from 12-hour to 24-hour format
         */
        handleTimeInput: function() {
            var $input = $(this);
            var value = $input.val().trim();
            
            if (value) {
                var time24 = KSM_PS_Admin.convertTo24Hour(value);
                if (time24) {
                    // Update the hidden field with 24-hour format
                    var hiddenFieldId = $input.attr('id') + '_24';
                    $('#' + hiddenFieldId).val(time24);
                    
                    // Update the display field with properly formatted 12-hour time
                    var time12 = KSM_PS_Admin.convertTo12Hour(time24);
                    $input.val(time12);
                } else {
                    // Invalid format - show error
                    $input.addClass('error');
                    alert('Please enter time in 12-hour format (e.g., 9:00 AM or 6:30 PM)');
                }
            }
        },
        
        /**
         * Convert 12-hour time to 24-hour format
         */
        convertTo24Hour: function(time12) {
            // Clean up the input
            time12 = time12.replace(/\s+/g, ' ').trim();
            
            // Match various 12-hour formats
            var match = time12.match(/^(\d{1,2}):(\d{2})\s*(AM|PM|am|pm)$/i);
            if (!match) {
                return null;
            }
            
            var hours = parseInt(match[1], 10);
            var minutes = parseInt(match[2], 10);
            var period = match[3].toUpperCase();
            
            // Validate hours and minutes
            if (hours < 1 || hours > 12 || minutes < 0 || minutes > 59) {
                return null;
            }
            
            // Convert to 24-hour format
            if (period === 'AM') {
                if (hours === 12) {
                    hours = 0;
                }
            } else { // PM
                if (hours !== 12) {
                    hours += 12;
                }
            }
            
            // Format as HH:MM
            return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
        },
        
        /**
         * Convert 24-hour time to 12-hour format
         */
        convertTo12Hour: function(time24) {
            var match = time24.match(/^(\d{1,2}):(\d{2})$/);
            if (!match) {
                return time24;
            }
            
            var hours = parseInt(match[1], 10);
            var minutes = match[2];
            var period = 'AM';
            
            if (hours === 0) {
                hours = 12;
            } else if (hours === 12) {
                period = 'PM';
            } else if (hours > 12) {
                hours -= 12;
                period = 'PM';
            }
            
            return hours + ':' + minutes + ' ' + period;
        },
        
        /**
         * Validate time inputs
         */
        validateTimeInputs: function() {
            var startTime24 = $('#ksm_ps_start_time_24').val();
            var endTime24 = $('#ksm_ps_end_time_24').val();
            
            if (startTime24 && endTime24) {
                var start = KSM_PS_Admin.timeToMinutes(startTime24);
                var end = KSM_PS_Admin.timeToMinutes(endTime24);
                
                if (start >= end) {
                    alert('End time must be after start time.');
                    $('#ksm_ps_end_time').focus();
                    return false;
                }
                
                // Check if there's enough time for minimum posts
                var postsPerDay = parseInt($('#ksm_ps_posts_per_day').val()) || 1;
                var minInterval = parseInt($('#ksm_ps_min_interval').val()) || 30;
                var availableMinutes = end - start;
                var requiredMinutes = (postsPerDay - 1) * minInterval;
                
                if (requiredMinutes > availableMinutes) {
                    alert('Not enough time between start and end time for the specified number of posts with minimum interval.');
                    return false;
                }
            }
            
            return true;
        },
        
        /**
         * Validate posts per day
         */
        validatePostsPerDay: function() {
            var value = parseInt($(this).val());
            
            if (value < 1) {
                $(this).val(1);
            } else if (value > 50) {
                $(this).val(50);
                alert('Maximum 50 posts per day allowed.');
            }
            
            // Re-validate time inputs
            KSM_PS_Admin.validateTimeInputs();
        },
        
        /**
         * Validate minimum interval
         */
        validateMinInterval: function() {
            var value = parseInt($(this).val());
            
            if (value < 5) {
                $(this).val(5);
                alert('Minimum interval must be at least 5 minutes.');
            } else if (value > 1440) {
                $(this).val(1440);
                alert('Maximum interval is 1440 minutes (24 hours).');
            }
            
            // Re-validate time inputs
            KSM_PS_Admin.validateTimeInputs();
        },
        
        /**
         * Convert time string to minutes
         */
        timeToMinutes: function(timeStr) {
            var parts = timeStr.split(':');
            return parseInt(parts[0]) * 60 + parseInt(parts[1]);
        },
        
        /**
         * Escape HTML entities
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) {
                return map[m];
            });
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        KSM_PS_Admin.init();
    });
    
})(jQuery);