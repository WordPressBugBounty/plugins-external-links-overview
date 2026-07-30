<?php
/**
 * Plugin Name:       External Links Overview
 * Plugin URI:        https://www.seo-kreativ.de/plugins/external-links-overview/
 * Description:       Analyze, display, and check all external links from your posts and pages. Helps find broken links and understand your outbound linking profile.
 * Version:           1.3.0
 * Requires at least: 5.0
 * Requires PHP:      7.0
 * Author:            www.seo-kreativ.de | Christian Ott
 * Author URI:        https://www.seo-kreativ.de/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       external-links-overview
 * Domain Path:       /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants with new prefix
define('SEOKELO_VERSION', '1.3.0');
define('SEOKELO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SEOKELO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SEOKELO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check External Links (set to true for link status checking) - Renamed
define('SEOKELO_CHECK_EXTERNAL_LINKS', true);
// Fetch titles of external pages (can be slow, disabled by default) - Renamed
define('SEOKELO_FETCH_EXTERNAL_TITLES', false);
// Maximum memory limit for PHP (can be adjusted) - Renamed
define('SEOKELO_MEMORY_LIMIT', '256M');


// Include required files using new Constant and assuming renamed class files
require_once SEOKELO_PLUGIN_DIR . 'includes/class-external-links-overview.php'; // Main Class - SEOKELO_Main
require_once SEOKELO_PLUGIN_DIR . 'includes/class-database-handler.php';      // SEOKELO_Database_Handler
require_once SEOKELO_PLUGIN_DIR . 'includes/class-link-processor.php';         // SEOKELO_Link_Processor
require_once SEOKELO_PLUGIN_DIR . 'admin/class-admin.php';                     // SEOKELO_Admin
require_once SEOKELO_PLUGIN_DIR . 'admin/class-ajax-handler.php';              // SEOKELO_Ajax_Handler


/**
 * Function to increase PHP memory limit - Renamed
 */
function seokelo_increase_memory_limit() {
    // Use renamed constant
    $memory_limit_constant = defined('SEOKELO_MEMORY_LIMIT') ? SEOKELO_MEMORY_LIMIT : '256M';
    $current_limit = ini_get('memory_limit');

    // Function to convert memory string (e.g., '256M') to bytes
    $normalize_memory_limit = function( $size ) {
        if ( is_numeric( $size ) ) {
            return (int) $size;
        }
        $suffix = strtoupper( substr( $size, -1 ) );
        $value  = substr( $size, 0, -1 );
        switch ( $suffix ) {
            case 'K': return (int) $value * 1024;
            case 'M': return (int) $value * 1024 * 1024;
            case 'G': return (int) $value * 1024 * 1024 * 1024;
            default: return (int) $size; // Assume bytes if no suffix or unknown
        }
    };

    $current_limit_val = $normalize_memory_limit($current_limit);
    $seokelo_limit_val = $normalize_memory_limit($memory_limit_constant);

    // Increase only if current limit is less than desired limit and greater than 0
    if ($current_limit_val > 0 && $seokelo_limit_val > 0 && $current_limit_val < $seokelo_limit_val) {
       @ini_set('memory_limit', $memory_limit_constant);
    }
}


/**
 * Initialize the plugin - Renamed
 */
function seokelo_init() {
    // Increase memory limit for plugin operations
    seokelo_increase_memory_limit();

    // Instantiate classes using new names
    $database_handler = new SEOKELO_Database_Handler();
    $link_processor   = new SEOKELO_Link_Processor($database_handler);
    $ajax_handler     = new SEOKELO_Ajax_Handler($link_processor, $database_handler);
    $admin            = new SEOKELO_Admin($link_processor, $database_handler);
    $plugin           = new SEOKELO_Main($database_handler, $link_processor, $admin, $ajax_handler); // Use new main class name

    // Initialize
    $plugin->init();
}
// Use renamed function in hook
add_action('plugins_loaded', 'seokelo_init');

/**
 * Plugin activation - Renamed
 */
function seokelo_activate() {
    // Ensure classes are loaded for activation context
    require_once SEOKELO_PLUGIN_DIR . 'includes/class-database-handler.php';
    // Instantiate with new name
    $database = new SEOKELO_Database_Handler();
    $database->create_tables(); // Creates/Updates the prefixed external links table

    // Create cache directory using prefixed name
    $upload_dir = wp_upload_dir();
    if ( ! empty( $upload_dir['basedir'] ) ) {
        // Use prefixed cache directory name
        $cache_dir = trailingslashit($upload_dir['basedir']) . 'seokelo-cache';
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
         // Secure the directory with .htaccess and index.php
        if ( is_dir( $cache_dir ) && wp_is_writable( $cache_dir ) ) {
            $htaccess_file = trailingslashit($cache_dir) . '.htaccess';
            if (!file_exists($htaccess_file)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents -- Required for htaccess.
                @file_put_contents($htaccess_file, "Options -Indexes\ndeny from all\n");
            }
            $index_file = trailingslashit( $cache_dir ) . 'index.php';
			if ( ! file_exists( $index_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents -- Required for index.php.
				@file_put_contents( $index_file, '<?php // Silence is golden.' );
			}
        }
    }

    // Set default options using new prefixed names
    add_option('seokelo_batch_offset', 0);
    add_option('seokelo_batch_offset_check', 0);
    add_option('seokelo_cache_timestamp', 0);
    add_option('seokelo_posts_to_update', array());
    add_option('seokelo_widget_enabled', true); // Enable widget by default
}
// Use renamed function in hook
register_activation_hook(__FILE__, 'seokelo_activate');

/**
 * Plugin deactivation - Renamed
 */
function seokelo_deactivate() {
    // Add any deactivation tasks here if needed in the future
    // e.g., remove scheduled cron jobs if any were added
}
// Use renamed function in hook
register_deactivation_hook(__FILE__, 'seokelo_deactivate');

/**
 * Plugin uninstall hook registration - Calls renamed function
 */
function seokelo_uninstall_hook() {
    // Ensure main class file is loaded if needed for uninstall method
    if (!class_exists('SEOKELO_Main')) {
         // Check if file exists before requiring
         $main_class_file = SEOKELO_PLUGIN_DIR . 'includes/class-external-links-overview.php';
         if ( file_exists( $main_class_file ) ) {
             require_once $main_class_file;
         }
    }
    // Call static uninstall method using new class name
    if(class_exists('SEOKELO_Main') && method_exists('SEOKELO_Main', 'uninstall')) {
       SEOKELO_Main::uninstall();
    } else {
        // Fallback if class loading failed during uninstall
        $uninstall_file = SEOKELO_PLUGIN_DIR . 'uninstall.php';
        if ( file_exists( $uninstall_file ) ) {
            require_once $uninstall_file; // Ensure uninstall script logic runs
            // Check if fallback function exists before calling
            if ( function_exists('seokelo_uninstall_fallback') ) {
                 seokelo_uninstall_fallback(); // Call the fallback function directly
            }
        }
    }
}
// Use renamed function in hook registration
register_uninstall_hook(__FILE__, 'seokelo_uninstall_hook');