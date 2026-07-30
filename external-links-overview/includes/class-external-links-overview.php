<?php
/**
 * Main plugin class
 * Renamed to SEOKELO_Main and prefixed options/constants.
 * Corrected nonce verification and output escaping.
 * Replaced unlink() with wp_delete_file().
 *
 * @since      1.0
 * @package    External_Links_Overview
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class - Renamed
 */
class SEOKELO_Main {
    private $database;
    private $link_processor;
    private $admin;
    private $ajax_handler;
    private $plugin_slug = 'external-links-overview'; // Keep slug for menu page etc.

    /**
     * Constructor
     * Expects instances of renamed classes.
     */
    public function __construct(SEOKELO_Database_Handler $database, SEOKELO_Link_Processor $link_processor, SEOKELO_Admin $admin, SEOKELO_Ajax_Handler $ajax_handler) {
        $this->database = $database;
        $this->link_processor = $link_processor;
        $this->admin = $admin;
        $this->ajax_handler = $ajax_handler;
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize components
        $this->database->init();
        $this->link_processor->init();
        $this->admin->init(); // Adds menus, enqueues scripts etc.
        $this->ajax_handler->init(); // Registers AJAX actions

        // Hooks for Post updates/deletions using class methods
        add_action('save_post', array($this, 'mark_post_for_update'), 10, 3);
        add_action('before_delete_post', array($this, 'cleanup_deleted_post_links'));
        // Use static method from SEOKELO_Admin for cache clearing (prefixed)
        add_action('delete_post', array('SEOKELO_Admin', 'clear_post_link_count_cache'));

        // CSV Export hook
        add_action('admin_init', array($this, 'process_csv_export'));

        // Process POST actions (like saving settings)
        add_action('admin_init', array($this, 'process_post_actions'));

        // Register Dashboard Widget hook
        add_action('wp_dashboard_setup', array($this, 'register_dashboard_widget'));
    }

    /**
     * Process CSV Export Request
     * Includes corrected nonce verification and prefixed action name.
     */
     public function process_csv_export() {
        // Check if export action is set, user is admin, and has capabilities
        if (isset($_GET['export']) && $_GET['export'] == 'external_csv' && isset($_GET['page']) && $_GET['page'] === $this->plugin_slug && is_admin() && current_user_can('manage_options')) {
             // Verify nonce after sanitizing/unslashing
             // Use prefixed nonce action name
             $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
             if (!empty($nonce) && wp_verify_nonce($nonce, 'seokelo_export_nonce')) {
                $this->export_external_csv(); // Call export method
                exit; // Stop execution after download
             } else {
                 // Nonce failed or missing
                 wp_die(esc_html__('Security check failed for CSV export.', 'external-links-overview'), esc_html__('Nonce Error', 'external-links-overview'), 403);
             }
        }
     }

    /**
     * Process POST actions (e.g., save widget setting, delete data)
     * Includes corrected nonce verification and prefixed input/action names.
     */
    public function process_post_actions() {
        // Basic checks for admin area and presence of our specific nonce field name
        // Use prefixed nonce field name
        if (!is_admin() || !isset($_POST['seokelo_nonce'])) {
            return;
        }
        // Capability check
        if (!current_user_can('manage_options')) {
             wp_die(esc_html__('Permission denied.', 'external-links-overview'), esc_html__('Permission Error', 'external-links-overview'), 403);
        }

        // Verify nonce after sanitizing/unslashing
        // Use prefixed nonce field name and action name
        $nonce = sanitize_text_field(wp_unslash($_POST['seokelo_nonce']));
        if (!wp_verify_nonce($nonce, 'seokelo_actions_nonce')) {
             wp_die(esc_html__('Security check failed.', 'external-links-overview'), esc_html__('Nonce Error', 'external-links-overview'), 403);
        }

        // Handle Dashboard Widget setting save
        // Use prefixed submit button name
        if (isset($_POST['seokelo_save_settings'])) {
            // Use intval to sanitize checkbox value (1 if checked, 0 otherwise)
            // Use prefixed checkbox name
            $widget_enabled = isset($_POST['seokelo_widget_enabled']) ? 1 : 0;
            // Use prefixed option name
            update_option('seokelo_widget_enabled', $widget_enabled);

            // Add feedback notice
            add_action('admin_notices', function() {
                 // Use esc_html__ for escaped translation
                 echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'external-links-overview') . '</p></div>';
            });
        }

         // Handle Delete Button (nonce already verified above)
         // Use prefixed button name
        if (isset($_POST['seokelo_delete_external_data_button'])) {
            $this->database->delete_all_external_data(); // Call DB method
            // Add admin notice for feedback
            add_action('admin_notices', function() {
                  // Use esc_html__ for escaped translation
                  echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('All external link data has been deleted.', 'external-links-overview') . '</p></div>';
            });
            return; // Stop further processing
        }
    }

    /**
     * Clean up links when a post is deleted - Uses prefixed option/table name.
     */
    public function cleanup_deleted_post_links($post_id) {
        global $wpdb;
        $post_id = intval($post_id);
        if ($post_id <= 0) {
            return;
        }
        $external_table_name = $this->database->get_external_table_name(); // Gets prefixed name
        $wpdb->delete($external_table_name, array('quelle_id' => $post_id), array('%d'));

        // Use prefixed option name
        $posts_to_update_option = 'seokelo_posts_to_update';
        $posts_to_update = get_option($posts_to_update_option, array());
        if (isset($posts_to_update[$post_id])) {
            unset($posts_to_update[$post_id]);
            update_option($posts_to_update_option, $posts_to_update);
        }
    }

    /**
     * Marks a post for update when saved - Uses prefixed option/filter name.
     */
    public function mark_post_for_update($post_id, $post, $update) {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !$update || wp_is_post_revision($post_id)) {
            return;
        }
        // Use prefixed filter name
        $allowed_post_types = apply_filters('seokelo_allowed_post_types', array('post', 'page'));
        if (!is_object($post) || !in_array($post->post_type, $allowed_post_types) || !in_array($post->post_status, array('publish', 'private'))) {
            return;
        }

        // Use prefixed option name
        $posts_to_update_option = 'seokelo_posts_to_update';
        $posts_to_update = get_option($posts_to_update_option, array());
        $posts_to_update[intval($post_id)] = time();
        update_option($posts_to_update_option, $posts_to_update);
        // Cache clearing is handled by a separate hook calling the static Admin method
    }

    /**
     * Export external links data as CSV.
     * Updated with all new and translated columns.
     */
    public function export_external_csv() {
        global $wpdb;
        $table_name = $this->database->get_external_table_name(); // Gets prefixed name
        $rows = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id ASC", ARRAY_A);

        if ($wpdb->last_error) {
             wp_die(esc_html__('Database error fetching links for export.', 'external-links-overview') . '<br>' . esc_html($wpdb->last_error));
             return;
        }

        $filename = sanitize_file_name('external_links_export_' . date('Y-m-d') . '.csv');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        ob_start();
        $output = fopen('php://output', 'w');

        if ($output === false) {
             ob_end_clean();
             wp_die(esc_html__('Could not open output stream for CSV export.', 'external-links-overview'));
             return;
        }

        fwrite($output, "\xEF\xBB\xBF");

        $headers = array(
            'Source ID', 'Source Title', 'Source URL', 'Anchor Text',
            'Link URL', 'Target Domain', 'Rel Attribute', 'Target',
            'Is Broken', 'HTTP Status', 'Last Checked',
            'Link type', 'Placed on', 'Expires on', 
            'Contact Name', 'Contact E-Mail', 'Price', 'Note'
        );
        fputcsv($output, $headers);

        if ($rows) {
             foreach ($rows as $row) {
                  $csv_row = array(
                     isset($row['quelle_id']) ? $row['quelle_id'] : '',
                     isset($row['quelle_titel']) ? $row['quelle_titel'] : '',
                     isset($row['quelle_url']) ? $row['quelle_url'] : '',
                     isset($row['ankertext']) ? $row['ankertext'] : '',
                     isset($row['link_url']) ? $row['link_url'] : '',
                     isset($row['ziel_domain']) ? $row['ziel_domain'] : '',
                     isset($row['link_rel']) ? $row['link_rel'] : '',
                     isset($row['link_target']) ? $row['link_target'] : '',
                     (isset($row['is_broken']) && $row['is_broken'] == 1) ? 'Yes' : 'No',
                     isset($row['http_status']) ? $row['http_status'] : '',
                     isset($row['last_checked']) ? $row['last_checked'] : '',
                     isset($row['link_type']) ? $row['link_type'] : '',
                     (isset($row['live_seit']) && $row['live_seit'] !== '0000-00-00') ? $row['live_seit'] : '',
                     (isset($row['expires_on']) && $row['expires_on'] !== '0000-00-00') ? $row['expires_on'] : '',
                     isset($row['kontakt_name']) ? $row['kontakt_name'] : '',
                     isset($row['kontakt_email']) ? $row['kontakt_email'] : '',
                     isset($row['price']) ? $row['price'] : '',
                     isset($row['notiz']) ? $row['notiz'] : ''
                  );
                  fputcsv($output, $csv_row);
              }
        }
        
        fclose($output);
        $csv_content = ob_get_clean();
        echo $csv_content;
        exit;
    }

    /**
     * Register the dashboard widget if enabled - Uses prefixed option/widget ID.
     */
    public function register_dashboard_widget() {
         // Use prefixed option name
         $widget_enabled = (bool) get_option('seokelo_widget_enabled', true);

         if ($widget_enabled && current_user_can('manage_options')) {
             wp_add_dashboard_widget(
                 'seokelo_broken_links_widget', // Prefixed widget ID
                 esc_html__('External Links Overview: Status', 'external-links-overview'),
                 array($this, 'display_dashboard_widget')
             );
         }
    }

    /**
     * Display the dashboard widget content - Prefixed elements, corrected escaping.
     */
    public function display_dashboard_widget() {
         global $wpdb;
         if (!isset($this->database) || !$this->database instanceof SEOKELO_Database_Handler) {
             echo '<p>' . esc_html__('Error: Database handler not available.', 'external-links-overview') . '</p>';
             return;
         }
         $table_name = $this->database->get_external_table_name(); // Prefixed

         // Queries remain the same, use prefixed table name
         $total_links_sql = "SELECT COUNT(*) FROM {$table_name}";
         $total_links = $wpdb->get_var($total_links_sql);
         $broken_count_sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE is_broken = %d", 1);
         $broken_count = $wpdb->get_var($broken_count_sql);
         $last_check_time = get_option('seokelo_last_external_check_time', 0); // Prefixed option

         // Use prefixed CSS class
         echo '<div class="seokelo-widget-content">';

         if (is_null($total_links) || is_null($broken_count)) {
              echo '<p>' . esc_html__('Could not retrieve link data. Please check database table.', 'external-links-overview') . '</p>';
              if (isset($wpdb->last_error) && $wpdb->last_error) {
                   echo '<p><small>Database Error: ' . esc_html($wpdb->last_error) . '</small></p>';
              }
              echo '</div>';
              return;
         }

         if ($total_links == 0) {
              echo '<p>' . esc_html__('No external links found in the database yet.', 'external-links-overview') . '</p>';
              echo '<p><a href="' . esc_url(admin_url('admin.php?page=' . $this->plugin_slug)) . '" class="button">' . esc_html__('Collect Links', 'external-links-overview') . '</a></p>';
         } else {
               $broken_count = intval($broken_count);
               $total_links = intval($total_links);

               $broken_text = sprintf(
                   esc_html__('Found %s broken external links.', 'external-links-overview'), // Escaped format string
                   '<strong>' . number_format_i18n($broken_count) . '</strong>' // number_format is safe
               );
               $total_text = sprintf(
                    esc_html__('out of %s total links', 'external-links-overview'), // Escaped format string
                    number_format_i18n($total_links) // number_format is safe
               );
               // Allow only <strong> tag for broken count part
               echo '<p>' . wp_kses($broken_text, ['strong' => []]) . ' (' . esc_html($total_text) . ')</p>';

               if ($last_check_time) {
                    echo '<p><small>';
                     $last_checked_text = sprintf(
                         esc_html__('Last checked: %s', 'external-links-overview'), // Escaped format string
                         date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_check_time + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )) // date_i18n is safe
                     );
                    echo esc_html($last_checked_text); // Escape final string
                    echo '</small></p>';
               } else {
                    echo '<p><small>' . esc_html__('Links have not been checked yet.', 'external-links-overview') . '</small></p>';
               }

               echo '<p>';
               echo '<a href="' . esc_url(admin_url('admin.php?page=' . $this->plugin_slug . '&filter_broken=1')) . '" class="button">' . esc_html__('View Broken Links', 'external-links-overview') . '</a> ';
               echo '<a href="' . esc_url(admin_url('admin.php?page=' . $this->plugin_slug)) . '" class="button button-secondary">' . esc_html__('Go to Overview', 'external-links-overview') . '</a>';
               echo '</p>';
          }
         echo '</div>'; // .seokelo-widget-content
    }


    /**
     * Plugin uninstall (static method) - Uses prefixed options/table name. Uses wp_delete_file.
     */
    public static function uninstall() {
        global $wpdb;
        // Use prefixed table name
        $external_table_name = $wpdb->prefix . 'seokelo_external_links';
        // Drop custom table
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DROP TABLE cannot be prepared.
        $wpdb->query("DROP TABLE IF EXISTS {$external_table_name}");

        // Delete options using new prefixes
        delete_option('seokelo_batch_offset');
        delete_option('seokelo_batch_offset_check');
        delete_option('seokelo_cache_timestamp');
        delete_option('seokelo_posts_to_update');
        delete_option('seokelo_last_error');
        delete_option('seokelo_last_external_check_count');
        delete_option('seokelo_last_external_broken_count');
        delete_option('seokelo_last_external_check_time');
        delete_option('seokelo_widget_enabled');
        delete_option('seokelo_total_posts_to_scan');

        // Remove cache directory using prefixed name
        $upload_dir = wp_upload_dir();
        if ( ! empty( $upload_dir['basedir'] ) ) {
            $cache_dir = trailingslashit($upload_dir['basedir']) . 'seokelo-cache'; // Prefixed name
            if (is_dir($cache_dir)) {
                self::remove_directory($cache_dir); // Call static helper method
            }
        }
        // Flush WP Cache
        wp_cache_flush();
    }

    /**
     * Helper function to recursively remove a directory (static) - Uses wp_delete_file
     */
     private static function remove_directory($dir) {
         if (!is_dir($dir) || !is_readable($dir)) {
             return;
         }
         try {
             $iterator = new DirectoryIterator($dir);
             foreach ($iterator as $fileinfo) {
                 if ($fileinfo->isDot()) {
                     continue;
                 }
                 $path = $fileinfo->getPathname(); // Gets absolute path
                 if (!is_readable($path)) {
                      error_log("SEOKELO Uninstall: Cannot read path $path"); // Logging error
                      continue;
                 }
                 if ($fileinfo->isDir()) {
                     self::remove_directory($path); // Recurse
                 } else {
                     // Use WordPress function to delete file
                     if (!wp_delete_file($path)) { // Check return value
                          error_log("SEOKELO Uninstall: Failed to delete file $path"); // Logging error
                     }
                 }
             }
             // Remove the main directory itself after emptying
             if (!@rmdir($dir)) { // Check return value and suppress error display
                 error_log("SEOKELO Uninstall: Failed to remove directory $dir"); // Logging error
             }
         } catch (Exception $e) { // Catch potential exceptions from DirectoryIterator
              error_log('Error removing directory during SEOKELO uninstall: ' . $e->getMessage());
         }
     }

} // End Class SEOKELO_Main