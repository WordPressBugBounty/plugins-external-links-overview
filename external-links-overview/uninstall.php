<?php
/**
 * Uninstall file for the plugin
 * Uses new prefixed constants, class names, option names, table name, cache dir.
 *
 * @since      1.0
 * @package    External_Links_Overview
 */

// Security check: Ensure file is not called directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define plugin directory constant if not already defined (use new prefix)
if (!defined('SEOKELO_PLUGIN_DIR')) {
     define('SEOKELO_PLUGIN_DIR', plugin_dir_path(__FILE__));
}


// Include the main class file to use the uninstall method (use new constant)
$main_class_file = SEOKELO_PLUGIN_DIR . 'includes/class-external-links-overview.php';

if (file_exists($main_class_file)) {
     require_once $main_class_file;
     // Call the static uninstall method using new class name
     if(class_exists('SEOKELO_Main')) {
        SEOKELO_Main::uninstall(); // Calls static method in SEOKELO_Main
     } else {
         // Fallback if class somehow failed to load
         seokelo_uninstall_fallback();
     }
} else {
     // Fallback: Direct database operations if class file is missing
     seokelo_uninstall_fallback();
}

/**
 * Fallback uninstall function if the main class method cannot be called.
 * Uses prefixed names directly.
 */
function seokelo_uninstall_fallback() {
    global $wpdb;
    // Use prefixed table name
    $external_table_name = $wpdb->prefix . 'seokelo_external_links';
    // Drop table if it exists - Prepared statements cannot be used for DROP TABLE
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query("DROP TABLE IF EXISTS {$external_table_name}");

    // Delete options using new prefixes
    delete_option('seokelo_batch_offset');
    delete_option('seokelo_batch_offset_check');
    delete_option('seokelo_cache_timestamp');
    delete_option('seokelo_posts_to_update');
    delete_option('seokelo_last_error');
    delete_option('seokelo_last_external_check_count'); // Keep for cleanup, though unused
    delete_option('seokelo_last_external_broken_count'); // Keep for cleanup, though unused
    delete_option('seokelo_last_external_check_time');
    delete_option('seokelo_widget_enabled');
    delete_option('seokelo_total_posts_to_scan');

    // Delete cache directory using prefixed name
      $upload_dir = wp_upload_dir();
      if ( ! empty( $upload_dir['basedir'] ) ) {
          $cache_dir = trailingslashit($upload_dir['basedir']) . 'seokelo-cache'; // Prefixed cache dir name
          // Use the static remove directory function if main class was loaded, otherwise basic attempt
          if (class_exists('SEOKELO_Main') && method_exists('SEOKELO_Main', 'remove_directory') && is_dir($cache_dir)) {
               SEOKELO_Main::remove_directory($cache_dir);
          } elseif (is_dir($cache_dir)) {
              // Basic recursive delete if helper unavailable (use with caution)
              try {
                  $iterator = new RecursiveDirectoryIterator($cache_dir, RecursiveDirectoryIterator::SKIP_DOTS);
                  $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
                  foreach($files as $file) {
                      if ($file->isDir()){
                          @rmdir($file->getRealPath());
                       } else {
                          @unlink($file->getRealPath());
                       }
                   }
                   @rmdir($cache_dir);
              } catch (Exception $e) {
                  error_log('Failed to remove directory during SEOKELO uninstall fallback: ' . $e->getMessage());
              }
          }
      }
      // --- REMOVED LICENSE-THIRDPARTY file removal ---

       // Flush WP Cache potentially
       wp_cache_flush();
}