# Changelog

All notable changes to the KSM Post Scheduler plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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