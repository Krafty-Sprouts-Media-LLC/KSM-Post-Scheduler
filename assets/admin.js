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
            
            // Smart suggestion buttons
            $(document).on('click', '.suggestion-btn', this.applySuggestion);
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
                        // Format the response message with HTML for better readability
                        var formattedMessage = KSM_PS_Admin.formatProgressReport(response.message);
                        $result.addClass('success').html(formattedMessage).show();
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
            // Update scheduler status indicator
            var $statusIndicator = $('.ksm-ps-status-indicator');
            if (data.options && data.options.enabled) {
                $statusIndicator.removeClass('disabled').addClass('enabled').text('Enabled');
            } else {
                $statusIndicator.removeClass('enabled').addClass('disabled').text('Disabled');
            }
            
            // Update posts waiting count
            $('.ksm-ps-count').text(data.scheduling_preview.posts_waiting + ' posts');
            
            // Update last cron run
            var lastRunText = 'Never';
            if (data.last_cron_run) {
                var lastRunDate = new Date(data.last_cron_run);
                lastRunText = lastRunDate.toLocaleDateString() + ' ' + lastRunDate.toLocaleTimeString();
            }
            $('.ksm-ps-time').first().html(data.last_cron_run ? lastRunText : '<em>Never</em>');
            
            // Update next cron run
            var nextRunText = 'Not scheduled';
            if (data.next_cron_run) {
                var nextRunDate = new Date(data.next_cron_run * 1000); // Convert from Unix timestamp
                nextRunText = nextRunDate.toLocaleDateString() + ' ' + nextRunDate.toLocaleTimeString();
            }
            $('.ksm-ps-time').last().html(data.next_cron_run ? nextRunText : '<em>Not scheduled</em>');
            
            // Update scheduling preview data if available
            if (data.scheduling_preview) {
                var preview = data.scheduling_preview;
                
                // Update all overview items with new data
                $('.ksm-ps-overview-item').each(function() {
                    var $item = $(this);
                    var $label = $item.find('strong');
                    var $value = $item.find('span').last();
                    
                    var labelText = $label.text();
                    
                    if (labelText.includes('Posts per day limit')) {
                        $value.text(preview.posts_per_day + ' posts');
                    } else if (labelText.includes('Estimated days needed')) {
                        $value.text(preview.estimated_days + ' days');
                    } else if (labelText.includes('Time window')) {
                        $value.text(preview.time_window);
                    } else if (labelText.includes('Minimum spacing')) {
                        $value.text(preview.min_interval + ' minutes');
                    } else if (labelText.includes('Active days')) {
                        $value.text(preview.active_days);
                    }
                });
                
                // Update daily preview if available
                if (preview.daily_preview && preview.daily_preview.length > 0) {
                    var $dailyPreview = $('.ksm-ps-daily-preview');
                    var dailyHtml = '';
                    
                    $.each(preview.daily_preview, function(index, dayInfo) {
                        dailyHtml += '<div class="ksm-ps-day-preview">';
                        dailyHtml += '<strong>' + KSM_PS_Admin.escapeHtml(dayInfo.day) + ':</strong>';
                        dailyHtml += '<span>' + dayInfo.posts_count + ' posts';
                        if (dayInfo.time_window) {
                            dailyHtml += ' (' + KSM_PS_Admin.escapeHtml(dayInfo.time_window) + ')';
                        }
                        dailyHtml += '</span>';
                        dailyHtml += '</div>';
                    });
                    
                    $dailyPreview.html(dailyHtml);
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
                if (requiredMinutes > availableMinutes) {
                    var availableHours = Math.floor(availableMinutes / 60);
                    var availableMins = availableMinutes % 60;
                    var requiredHours = Math.floor(requiredMinutes / 60);
                    var requiredMins = requiredMinutes % 60;
                    
                    var maxPosts = Math.max(1, Math.floor(availableMinutes / minInterval) + 1);
                    var extraMinutesNeeded = requiredMinutes - availableMinutes;
                    var suggestedInterval = Math.max(5, Math.floor(availableMinutes / (postsPerDay - 1)));
                    
                    var errorMessage = 'Not enough time! You need ' + requiredHours + 'h ' + requiredMins + 'm (' + requiredMinutes + ' minutes total) but only have ' + 
                                     availableHours + 'h ' + availableMins + 'm (' + availableMinutes + ' minutes total) available. ' +
                                     'Suggestions: Reduce posts to ' + maxPosts + ' per day, OR extend end time by ' + extraMinutesNeeded + ' minutes, OR reduce interval to ' + suggestedInterval + ' minutes.';
                    
                    KSM_PS_Admin.addValidationError('time_window', errorMessage);
                    
                    // Update the time calculator display
                    KSM_PS_Admin.updateTimeCalculator(availableMinutes, requiredMinutes, postsPerDay, minInterval);
                    return false;
                } else {
                    // Update the time calculator display for valid scenarios
                    KSM_PS_Admin.updateTimeCalculator(availableMinutes, requiredMinutes, postsPerDay, minInterval);
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
         * Update time calculator display
         */
        updateTimeCalculator: function(availableMinutes, requiredMinutes, postsPerDay, minInterval) {
            var $calculator = $('#ksm-time-calculator');
            if ($calculator.length === 0) {
                // Create calculator if it doesn't exist
                var calculatorHtml = '<div id="ksm-time-calculator" class="ksm-time-calculator">' +
                    '<h4>📊 Time Analysis</h4>' +
                    '<div class="time-stats">' +
                        '<div class="stat-item">' +
                            '<span class="stat-label">Available Time:</span>' +
                            '<span class="stat-value" id="available-time"></span>' +
                        '</div>' +
                        '<div class="stat-item">' +
                            '<span class="stat-label">Required Time:</span>' +
                            '<span class="stat-value" id="required-time"></span>' +
                        '</div>' +
                        '<div class="stat-item">' +
                            '<span class="stat-label">Status:</span>' +
                            '<span class="stat-value" id="time-status"></span>' +
                        '</div>' +
                    '</div>' +
                    '<div id="smart-suggestions" class="smart-suggestions"></div>' +
                '</div>';
                
                $('#ksm_ps_end_time').closest('.form-group').after(calculatorHtml);
                $calculator = $('#ksm-time-calculator');
            }
            
            // Update calculator values
            var availableHours = Math.floor(availableMinutes / 60);
            var availableMins = availableMinutes % 60;
            var requiredHours = Math.floor(requiredMinutes / 60);
            var requiredMins = requiredMinutes % 60;
            
            $('#available-time').text(availableHours + 'h ' + availableMins + 'm (' + availableMinutes + ' min)');
            $('#required-time').text(requiredHours + 'h ' + requiredMins + 'm (' + requiredMinutes + ' min)');
            
            var $status = $('#time-status');
            var $suggestions = $('#smart-suggestions');
            
            if (requiredMinutes > availableMinutes) {
                $status.html('<span class="status-error">❌ Insufficient Time</span>');
                $calculator.addClass('error');
                
                // Generate smart suggestions
                var suggestions = KSM_PS_Admin.generateSmartSuggestions(availableMinutes, requiredMinutes, postsPerDay, minInterval);
                $suggestions.html('<h5>💡 Smart Suggestions:</h5>' + suggestions);
            } else {
                var extraTime = availableMinutes - requiredMinutes;
                $status.html('<span class="status-success">✅ Perfect! ' + Math.floor(extraTime / 60) + 'h ' + (extraTime % 60) + 'm extra</span>');
                $calculator.removeClass('error');
                $suggestions.empty();
            }
        },
        
        /**
         * Generate smart suggestions for time conflicts
         */
        generateSmartSuggestions: function(availableMinutes, requiredMinutes, postsPerDay, minInterval) {
            var suggestions = '<div class="suggestion-options">';
            
            // Option 1: Reduce posts
            var maxPosts = Math.max(1, Math.floor(availableMinutes / minInterval) + 1);
            suggestions += '<div class="suggestion-option">' +
                '<button type="button" class="suggestion-btn" data-action="reduce-posts" data-value="' + maxPosts + '">' +
                '📉 Reduce to ' + maxPosts + ' posts/day</button></div>';
            
            // Option 2: Extend time
            var extraMinutesNeeded = requiredMinutes - availableMinutes;
            var newEndTime = KSM_PS_Admin.addMinutesToTime($('#ksm_ps_end_time').val(), extraMinutesNeeded);
            suggestions += '<div class="suggestion-option">' +
                '<button type="button" class="suggestion-btn" data-action="extend-time" data-value="' + newEndTime + '">' +
                '⏰ Extend end time to ' + newEndTime + '</button></div>';
            
            // Option 3: Reduce interval
            var suggestedInterval = Math.max(5, Math.floor(availableMinutes / (postsPerDay - 1)));
            suggestions += '<div class="suggestion-option">' +
                '<button type="button" class="suggestion-btn" data-action="reduce-interval" data-value="' + suggestedInterval + '">' +
                '⚡ Reduce interval to ' + suggestedInterval + ' minutes</button></div>';
            
            suggestions += '</div>';
            return suggestions;
        },
        
        /**
         * Add minutes to a time string
         */
        addMinutesToTime: function(timeStr, minutesToAdd) {
            var totalMinutes = KSM_PS_Admin.time12ToMinutes(timeStr) + minutesToAdd;
            return KSM_PS_Admin.minutesToTime12(totalMinutes);
        },
        
        /**
         * Convert minutes to 12-hour time format
         */
        minutesToTime12: function(minutes) {
            var hours = Math.floor(minutes / 60);
            var mins = minutes % 60;
            var period = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            if (hours === 0) hours = 12;
            return hours + ':' + (mins < 10 ? '0' : '') + mins + ' ' + period;
        },
        
        /**
         * Apply smart suggestion
         */
        applySuggestion: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var action = $btn.data('action');
            var value = $btn.data('value');
            
            switch(action) {
                case 'reduce-posts':
                    $('#ksm_ps_posts_per_day').val(value).trigger('change');
                    break;
                case 'extend-time':
                    $('#ksm_ps_end_time').val(value).trigger('change');
                    break;
                case 'reduce-interval':
                    $('#ksm_ps_min_interval').val(value).trigger('change');
                    break;
            }
            
            // Show feedback
            $btn.addClass('applied').text('✅ Applied!');
            setTimeout(function() {
                $btn.removeClass('applied');
            }, 2000);
        },
        
        /**
         * Format progress report for better display
         */
        formatProgressReport: function(message) {
            // Convert line breaks to HTML breaks and add proper formatting
            var formatted = this.escapeHtml(message);
            
            // Replace line breaks with HTML breaks
            formatted = formatted.replace(/\n/g, '<br>');
            
            // Format progress report sections with better styling
            formatted = formatted.replace(/📊 PROGRESS REPORT:/g, '<strong>📊 PROGRESS REPORT:</strong>');
            formatted = formatted.replace(/Total posts to schedule: (\d+)/g, '<strong>Total posts to schedule: $1</strong>');
            formatted = formatted.replace(/Successfully scheduled: (\d+)/g, '<strong style="color: #28a745;">Successfully scheduled: $1</strong>');
            formatted = formatted.replace(/Day-by-day breakdown:/g, '<strong>Day-by-day breakdown:</strong>');
            
            // Format day entries
            formatted = formatted.replace(/📅 ([^:]+): (\d+) posts? scheduled/g, '<div style="margin-left: 15px; margin-top: 5px;"><strong>📅 $1:</strong> <span style="color: #28a745;">$2 posts scheduled</span></div>');
            formatted = formatted.replace(/⏭️ Moving to next day \(([^)]+)\)/g, '<div style="margin-left: 15px; margin-top: 5px; color: #6c757d;"><em>⏭️ Moving to next day ($1)</em></div>');
            formatted = formatted.replace(/🎯 Starting ([^:]+): Can schedule (\d+) posts today/g, '<div style="margin-left: 15px; margin-top: 5px;"><strong>🎯 Starting $1:</strong> Can schedule $2 posts today</div>');
            
            return formatted;
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