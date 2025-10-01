/**
 * KSM Post Scheduler Admin JavaScript
 * 
 * @package KSM_Post_Scheduler
 * @version 1.1.5
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Admin object
     */
    var KSM_PS_Admin = {
        
        // Validation state
        validationErrors: [],
        
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
            
            // Form submission validation
            $('form').on('submit', this.validateForm);
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
         * Handle time input validation for 12-hour format
         */
        handleTimeInput: function() {
            var $input = $(this);
            var value = $input.val().trim();
            
            if (value) {
                // Validate 12-hour format
                var match = value.match(/^(\d{1,2}):(\d{2})\s*(AM|PM|am|pm)$/i);
                if (match) {
                    var hours = parseInt(match[1], 10);
                    var minutes = parseInt(match[2], 10);
                    
                    // Validate hours and minutes
                    if (hours >= 1 && hours <= 12 && minutes >= 0 && minutes <= 59) {
                        // Format consistently
                        var period = match[3].toUpperCase();
                        var formattedTime = hours + ':' + String(minutes).padStart(2, '0') + ' ' + period;
                        $input.val(formattedTime);
                        
                        // Remove error styling
                        $input.removeClass('error');
                    } else {
                        // Invalid time values
                        $input.addClass('error');
                        KSM_PS_Admin.showValidationError(ksm_ps_ajax.strings.time_format_error);
                    }
                } else {
                    // Invalid format - show error
                    $input.addClass('error');
                    KSM_PS_Admin.showValidationError(ksm_ps_ajax.strings.time_format_error);
                }
            }
        },
        

        
        /**
         * Validate time inputs
         */
        validateTimeInputs: function() {
            var startTime = $('#ksm_ps_start_time').val();
            var endTime = $('#ksm_ps_end_time').val();
            var postsPerDay = parseInt($('#ksm_ps_posts_per_day').val()) || 5;
            var minInterval = parseInt($('#ksm_ps_min_interval').val()) || 60;
            
            // Remove previous time validation errors
            KSM_PS_Admin.removeValidationError('time_window');
            KSM_PS_Admin.removeValidationError('end_time_after_start');
            
            if (startTime && endTime) {
                var startMinutes = KSM_PS_Admin.time12ToMinutes(startTime);
                var endMinutes = KSM_PS_Admin.time12ToMinutes(endTime);
                
                // Check if end time is after start time
                if (endMinutes <= startMinutes) {
                    KSM_PS_Admin.addValidationError('end_time_after_start', 'End time must be after start time.');
                    return false;
                }
                
                // Calculate available time window in minutes
                var availableMinutes = endMinutes - startMinutes;
                
                // Calculate required time for posts with intervals
                var requiredMinutes = (postsPerDay - 1) * minInterval;
                
                // Check if there's enough time
                if (requiredMinutes >= availableMinutes) {
                    KSM_PS_Admin.addValidationError('time_window', ksm_ps_ajax.strings.time_window_error);
                    return false;
                }
            }
            
            return true;
        },
        
        /**
         * Validate posts per day
         */
        validatePostsPerDay: function() {
            var $input = $(this);
            var value = parseInt($input.val());
            
            // Remove previous posts per day validation errors
            KSM_PS_Admin.removeValidationError('posts_per_day');
            
            if (value < 1 || value > 50) {
                KSM_PS_Admin.addValidationError('posts_per_day', ksm_ps_ajax.strings.posts_per_day_error);
                $input.val(Math.min(Math.max(value, 1), 50));
                return false;
            }
            
            // Re-validate time inputs when posts per day changes
            KSM_PS_Admin.validateTimeInputs();
            
            return true;
        },
        
        /**
         * Validate minimum interval
         */
        validateMinInterval: function() {
            var $input = $(this);
            var value = parseInt($input.val());
            
            // Remove previous min interval validation errors
            KSM_PS_Admin.removeValidationError('min_interval');
            
            if (value < 5 || value > 1440) {
                KSM_PS_Admin.addValidationError('min_interval', ksm_ps_ajax.strings.min_interval_error);
                $input.val(Math.min(Math.max(value, 5), 1440));
                return false;
            }
            
            // Re-validate time inputs when interval changes
            KSM_PS_Admin.validateTimeInputs();
            
            return true;
        },
        
        /**
         * Convert 12-hour time string to minutes since midnight
         */
        time12ToMinutes: function(time12) {
            var match = time12.match(/^(\d{1,2}):(\d{2})\s*(AM|PM|am|pm)$/i);
            if (!match) {
                return 0;
            }
            
            var hours = parseInt(match[1], 10);
            var minutes = parseInt(match[2], 10);
            var period = match[3].toUpperCase();
            
            // Convert to 24-hour format for calculation
            if (period === 'AM') {
                if (hours === 12) {
                    hours = 0;
                }
            } else { // PM
                if (hours !== 12) {
                    hours += 12;
                }
            }
            
            return hours * 60 + minutes;
        },

        /**
         * Convert time string to minutes (legacy function for compatibility)
         */
        timeStringToMinutes: function(timeString) {
            // Check if it's 12-hour format
            if (timeString.match(/AM|PM|am|pm/)) {
                return this.time12ToMinutes(timeString);
            }
            
            // Handle 24-hour format (legacy)
            var parts = timeString.split(':');
            return parseInt(parts[0]) * 60 + parseInt(parts[1]);
        },
        
        /**
         * Add validation error
         */
        addValidationError: function(key, message) {
            // Remove existing error with same key
            this.removeValidationError(key);
            
            // Add new error
            this.validationErrors.push({
                key: key,
                message: message
            });
        },
        
        /**
         * Remove validation error by key
         */
        removeValidationError: function(key) {
            this.validationErrors = this.validationErrors.filter(function(error) {
                return error.key !== key;
            });
        },
        
        /**
         * Show validation error using SweetAlert2
         */
        showValidationError: function(message) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: ksm_ps_ajax.strings.validation_error,
                    text: message,
                    confirmButtonColor: '#d33'
                });
            } else {
                // Fallback to alert if SweetAlert2 is not loaded
                alert(message);
            }
        },
        
        /**
         * Show success message using SweetAlert2
         */
        showSuccessMessage: function(message) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: message,
                    confirmButtonColor: '#28a745',
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        },
        
        /**
         * Validate form before submission
         */
        validateForm: function(e) {
            // Clear previous errors
            KSM_PS_Admin.validationErrors = [];
            
            // Run all validations
            KSM_PS_Admin.validateTimeInputs();
            KSM_PS_Admin.validatePostsPerDay.call($('#ksm_ps_posts_per_day')[0]);
            KSM_PS_Admin.validateMinInterval.call($('#ksm_ps_min_interval')[0]);
            
            // Check if there are any validation errors
            if (KSM_PS_Admin.validationErrors.length > 0) {
                e.preventDefault();
                
                // Show all validation errors
                var errorMessages = KSM_PS_Admin.validationErrors.map(function(error) {
                    return error.message;
                }).join('\n\n');
                
                KSM_PS_Admin.showValidationError(errorMessages);
                return false;
            }
            
            return true;
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