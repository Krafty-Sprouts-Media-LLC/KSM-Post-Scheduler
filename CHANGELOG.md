# Changelog

All notable changes to the KSM Post Scheduler plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.6] - 01/10/2025

### Added
- **SweetAlert2 Integration**: Replaced browser alerts with modern SweetAlert2 notifications for better user experience
  - Added SweetAlert2 library from CDN for beautiful, customizable notifications
  - Implemented proper error, success, and warning message displays
  - Enhanced visual feedback for validation errors and form submissions

### Enhanced
- **Improved Validation System**: Comprehensive client-side and server-side validation
  - Real-time validation for time inputs, posts per day, and minimum interval settings
  - Proper form validation that prevents submission when errors are present
  - Enhanced time window validation to ensure sufficient time for scheduled posts with minimum intervals
  - Better error messaging with specific validation rules and constraints

### Fixed
- **Error Handling**: Replaced intrusive browser prompts with user-friendly notifications
  - Fixed issue where users could save invalid settings despite validation errors
  - Improved server-side validation in `sanitize_settings()` function
  - Added proper error display using WordPress settings errors API
  - Enhanced validation for time format conversion and range checking

### Technical
- Added comprehensive validation helper functions in admin.js
- Implemented `addValidationError()`, `removeValidationError()`, and `showValidationError()` methods
- Enhanced server-side validation with proper error collection and display
- Added `time_to_minutes()` helper function for time calculations
- Improved form submission handling with validation state tracking

## [1.1.5] - 01/10/2025

### Fixed
- **Posts Per Day Distribution**: Fixed critical issue where all posts were being scheduled for the same day instead of being distributed across multiple days
  - Modified `schedule_posts()` function to properly distribute posts across days based on the "Posts Per Day" setting
  - Posts now correctly move to the next day when the daily limit is reached
  - Added intelligent day calculation that respects the posts per day limit

### Added
- **Dynamic Schedule Re-adjustment**: Added automatic re-adjustment of existing scheduled posts when "Posts Per Day" setting changes
  - New `readjust_scheduled_posts()` function that resets and reschedules existing future posts
  - New `handle_posts_per_day_change()` function that detects setting changes and triggers re-adjustment
  - Enhanced user feedback with success/error messages when re-adjustment occurs

### Enhanced
- **Improved Scheduling Logic**: Enhanced the scheduling algorithm to handle multi-day distribution
  - Added day counter and posts-per-day tracking within the scheduling loop
  - Better handling of time conflicts when moving between days
  - More comprehensive debug logging for multi-day scheduling

### Technical
- Modified `schedule_posts()` to retrieve all available posts instead of limiting by posts_per_day
- Added day tracking variables (`$current_day`, `$posts_scheduled_today`) for proper distribution
- Enhanced `sanitize_settings()` to detect posts_per_day changes and trigger re-adjustment
- Added WordPress action hook integration for seamless settings change handling

## [1.1.4] - 01/10/2025

### Fixed
- **Critical Scheduling Bug**: Fixed issue where posts were being published immediately instead of being scheduled for future publication
  - Improved timezone handling and timestamp calculations to ensure proper future scheduling
  - Enhanced time comparison logic using proper timestamp comparison instead of string comparison
  - Added multiple safety checks to prevent scheduling posts in the past
  - Added comprehensive debugging logs to track scheduling process and identify issues
  - Fixed wp_update_post() error handling to properly detect and report scheduling failures
  - Added post-update verification to ensure posts are correctly set to 'future' status

### Enhanced
- **Debugging System**: Added extensive debug logging throughout the scheduling process
  - Logs current WordPress time, server time, and timestamps for accurate troubleshooting
  - Tracks each post's scheduling process with detailed status updates
  - Verifies post status after updates to ensure proper scheduling
  - Added safety checks to prevent accidental immediate publication

### Technical
- Modified schedule_posts() function with improved timestamp-based time calculations
- Enhanced error handling in wp_update_post() calls with proper error detection
- Added multiple validation layers to ensure scheduled times are always in the future
- Improved debugging output for better maintenance and troubleshooting

## [1.1.3] - 01/10/2025

### Fixed
- **Manual Testing Button**: Fixed critical bug where "Manual Testing" button was immediately publishing posts instead of scheduling them
  - Changed scheduling logic to use tomorrow's date instead of today's date
  - Prevents WordPress from immediately publishing posts when scheduled time has already passed today
  - Ensures all posts are properly scheduled for future publication, not immediately published
  - Resolves issue where fresh draft posts were published instantly while old published posts (returned to draft) were correctly scheduled

### Technical
- Modified schedule_posts() function to use `strtotime('+1 day')` for scheduling date calculation
- Added explanatory comment for future maintenance clarity

## [1.1.2] - 01/10/2025

### Added
- **12-Hour Time Format Support**: Enhanced time input fields to use user-friendly 12-hour format
  - Start and End time inputs now accept 12-hour format (e.g., "9:00 AM", "6:30 PM")
  - Automatic conversion between 12-hour display format and 24-hour storage format
  - Client-side validation and formatting with real-time feedback
  - Backward compatibility with existing 24-hour format settings
  - Enhanced user experience with intuitive time entry

### Enhanced
- **Time Input Validation**: Improved time validation with better error messages and format guidance
- **JavaScript Functions**: Added comprehensive time conversion utilities for seamless format handling
- **Admin Interface**: Updated time input fields with placeholders and pattern validation

### Technical
- Added convert_24_to_12() and convert_12_to_24() PHP methods for server-side time conversion
- Enhanced sanitize_settings() to handle both display and storage time formats
- Added JavaScript time conversion functions with robust format parsing
- Maintained 24-hour format for internal storage and cron scheduling

## [1.1.1] - 01/10/2025

### Fixed
- **Duplicate Settings Message**: Fixed issue where "Settings saved." message was appearing twice on the admin page
  - Modified admin-page.php to only display settings_errors() when there are actual validation errors
  - WordPress now handles success messages automatically, preventing duplication
  - Improved user experience with cleaner settings feedback

## [1.1.0] - 01/10/2025

### Added
- **Comprehensive Uninstall System**: Added proper uninstall.php file for complete plugin cleanup
- **Enhanced Activation Process**: Added WordPress and PHP version compatibility checks
- **Version Upgrade Handling**: Automatic detection and handling of plugin updates
- **Admin Notices**: Success notifications for activation and update processes
- **Better Data Management**: Improved option handling with version tracking and installation date
- **Multisite Support**: Full compatibility with WordPress multisite installations

### Enhanced
- **Activation Hook**: Now includes version checking, compatibility validation, and better error handling
- **Deactivation Hook**: Enhanced cleanup process with transient removal and cache flushing
- **Security**: Added proper capability checks and validation throughout install/uninstall process

### Technical
- Added `register_uninstall_hook()` for proper WordPress uninstall handling
- Enhanced database cleanup with comprehensive option and meta data removal
- Added transient management for better performance and cleanup
- Improved error logging and debugging capabilities
- Added flush_rewrite_rules() for better WordPress integration

## [1.0.0] - 01/10/2025

### Added
- Initial release of KSM Post Scheduler plugin
- WordPress plugin with proper header and metadata
- Admin settings page with comprehensive configuration options:
  - Post Status to Monitor (select dropdown)
  - Posts Per Day (number input, default: 5)
  - Start Time (time input, default: 09:00)
  - End Time (time input, default: 18:00)
  - Days Active (checkboxes for each day of week)
  - Minimum Interval Between Posts (number in minutes, default: 30)
  - Enable/Disable toggle with modern switch design
- WordPress cron job functionality:
  - Daily cron job that runs at midnight
  - Function name: random_post_scheduler_daily_cron
  - Automatic scheduling of posts with random publish times
  - Respect for minimum interval between posts
  - Only schedules specified number of posts per day
- Manual "Run Now" button for testing purposes
- Activation/deactivation hooks for proper cron job management
- Security features:
  - Nonces for CSRF protection
  - Input sanitization and validation
  - Proper capability checks (manage_options)
- Status dashboard showing:
  - Current count of posts in monitored status
  - List of next 10 upcoming scheduled posts with titles and times
  - Scheduler enable/disable status
  - Next cron run time
- Modern admin interface with:
  - Responsive design
  - Professional styling
  - AJAX functionality for real-time updates
  - Form validation and user feedback
- Complete file structure:
  - Main plugin file (ksm-post-scheduler.php)
  - Admin page template (templates/admin-page.php)
  - CSS styling (assets/admin.css)
  - JavaScript functionality (assets/admin.js)
  - Documentation (CHANGELOG.md)

### Technical Details
- Requires WordPress 5.0 or higher
- Requires PHP 7.4 or higher
- Uses KSM_PS namespace for all functions and classes
- Follows WordPress coding standards and best practices
- Implements proper error handling and user feedback
- Uses WordPress hooks and filters appropriately
- Includes comprehensive inline documentation

### Security
- All user inputs are properly sanitized
- CSRF protection via WordPress nonces
- Capability checks for admin access
- No direct file access allowed
- Secure AJAX implementations