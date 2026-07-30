<?php
/**
 * Ajax Handler for the plugin (Free Version)
 * Renamed to SEOKELO_Ajax_Handler.
 * Prefixed actions, nonces, options.
 *
 * @since      1.0
 * @package    External_Links_Overview
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajax Handler Class - Renamed
 */
class SEOKELO_Ajax_Handler {
    /**
     * Link Processor
     * @var SEOKELO_Link_Processor
     */
    private $link_processor;

    /**
     * Database Handler
     * @var SEOKELO_Database_Handler
     */
    private $database;

    /**
     * Constructor
     * @param SEOKELO_Link_Processor   $link_processor Link Processor instance.
     * @param SEOKELO_Database_Handler $database       Database Handler instance.
     */
    public function __construct(SEOKELO_Link_Processor $link_processor, SEOKELO_Database_Handler $database) {
        $this->link_processor = $link_processor;
        $this->database = $database;
    }

    /**
     * Initialize the class and register AJAX actions using new prefixes.
     */
    public function init() {
        // Register prefixed AJAX actions
        add_action('wp_ajax_seokelo_process_external_links', array($this, 'process_external_links_ajax'));
        add_action('wp_ajax_seokelo_reset_external_link_status', array($this, 'reset_external_link_status_ajax'));
        add_action('wp_ajax_seokelo_check_links_status', array($this, 'check_links_status_ajax'));
        add_action('wp_ajax_seokelo_get_process_progress', array($this, 'get_process_progress_ajax'));
        add_action('wp_ajax_seokelo_get_domain_metrics', array($this, 'get_domain_metrics_ajax'));
        add_action('wp_ajax_seokelo_ignore_link_status', array($this, 'ignore_link_status_ajax'));
    }

    /**
     * AJAX handler for batch processing of external links collection/update.
     * Uses prefixed nonce and options.
     */
    public function process_external_links_ajax() {
        // Check nonce with prefixed action name
        check_ajax_referer('seokelo_ajax_nonce', 'nonce');
        // Check user capabilities
         if (!current_user_can('manage_options')) {
             wp_send_json_error(['message' => esc_html__('Permission denied.', 'external-links-overview')]);
             return; // Exit early
         }

        // Sanitize input parameters
        $action_type = isset($_POST['action_type']) ? sanitize_key($_POST['action_type']) : 'collect'; // 'collect' or 'update'
        $batch_index = isset($_POST['batch_index']) ? intval($_POST['batch_index']) : 0;

        // Validate action type
        if (!in_array($action_type, ['collect', 'update'])) {
            $action_type = 'collect'; // Default to collect if invalid
        }

        // Determine processing mode
        $process_only_updated = ($action_type === 'update');

        // Use prefixed option names
        $batch_offset_option = $this->database->get_batch_offset_option();
        $posts_to_update_option = 'seokelo_posts_to_update';
        $total_posts_scan_option = 'seokelo_total_posts_to_scan';
        $cache_timestamp_option = $this->database->get_cache_timestamp_option();

        // Reset offset on first batch
         if ($batch_index === 0) {
             update_option($batch_offset_option, 0);
             if (!$process_only_updated) {
                 // Clear table handled by process_post_actions or delete button, but ensure scan count is reset
                 delete_option($total_posts_scan_option);
             }
         }

        // Process one batch using the link processor instance
        $continue = $this->link_processor->collect_external_links_batch($process_only_updated);

        // Calculate progress using the link processor instance
        $progress_data = $this->link_processor->calculate_collection_progress($process_only_updated);

        // Set timestamp on completion
        if (!$continue) {
            update_option($cache_timestamp_option, time());
             // Clear update list if this was an update run that finished
             if ($process_only_updated) {
                  update_option($posts_to_update_option, array());
             }
             // Clear total posts scan count option upon completion
             delete_option($total_posts_scan_option);
        }

        // Send JSON success response with progress data
        wp_send_json_success(array(
            'continue'       => $continue,
            'batch_index'    => $batch_index + 1,
            'progress'       => $progress_data['progress'],
            'current_offset' => $progress_data['current_offset'],
            'total_items'    => $progress_data['total_items'],
            'link_count'     => $progress_data['link_count'],
            'message'        => $continue
                                 ? esc_html__('Processing external links...', 'external-links-overview')
                                 : esc_html__('External link processing complete!', 'external-links-overview')
        ));
    }

     /**
      * AJAX handler for batch checking of external link statuses.
      * Uses prefixed nonce and options.
      */
     public function check_links_status_ajax() {
         // Check nonce with prefixed action name
         check_ajax_referer('seokelo_ajax_nonce', 'nonce');
         // Check user capabilities
         if (!current_user_can('manage_options')) {
              wp_send_json_error(['message' => esc_html__('Permission denied.', 'external-links-overview')]);
              return; // Exit early
          }

         // Sanitize input
         $batch_index = isset($_POST['batch_index']) ? intval($_POST['batch_index']) : 0;

         // Use prefixed option name
         $batch_offset_check_option = $this->database->get_batch_offset_check_option();
         $last_check_time_option = 'seokelo_last_external_check_time';

          // Reset offset on first batch of check run
         if ($batch_index === 0) {
             update_option($batch_offset_check_option, 0);
         }

         // Process one batch of checks using the link processor instance
         $continue = $this->link_processor->check_links_batch();

         // Calculate progress using the link processor instance
         $progress_data = $this->link_processor->calculate_checking_progress();

         // Set timestamp on completion
         if (!$continue) {
              update_option($last_check_time_option, time());
         }

         // Send JSON success response with progress data
         wp_send_json_success(array(
             'continue'     => $continue,
             'batch_index'  => $batch_index + 1,
             'progress'     => $progress_data['progress'],
             'current_offset' => $progress_data['current_offset'],
             'total_items'  => $progress_data['total_items'],
             'broken_count' => $progress_data['broken_count'],
             'message'      => $continue
                                ? esc_html__('Checking link status...', 'external-links-overview')
                                : esc_html__('Link status check complete!', 'external-links-overview')
         ));
     }

      /**
       * AJAX handler to get progress for any running process (collect, update, check).
       * Uses prefixed nonce.
       */
       public function get_process_progress_ajax() {
            // Check nonce with prefixed action name
            check_ajax_referer('seokelo_ajax_nonce', 'nonce');
             // Check user capabilities
             if (!current_user_can('manage_options')) {
                 wp_send_json_error(['message' => esc_html__('Permission denied.', 'external-links-overview')]);
                 return; // Exit early
             }

             // Sanitize input
             $process_type = isset($_POST['process_type']) ? sanitize_key($_POST['process_type']) : 'collect';

             // Validate process type
             if (!in_array($process_type, ['collect', 'update', 'check'])) {
                 $process_type = 'collect'; // Default if invalid
             }

             $progress_data = array();

             // Get progress data based on process type using the link processor instance
             if ($process_type === 'check') {
                 $progress_data = $this->link_processor->calculate_checking_progress();
             } else {
                 // 'collect' or 'update'
                  $is_updating = ($process_type === 'update');
                  $progress_data = $this->link_processor->calculate_collection_progress($is_updating);
             }

             // Send JSON success response with progress data
             wp_send_json_success($progress_data);
       }


    /**
     * AJAX handler to manually reset link status (mark as OK).
     * Uses prefixed nonce and table name.
     */
    public function reset_external_link_status_ajax() {
        // Check nonce with prefixed action name
        check_ajax_referer('seokelo_ajax_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            // Return escaped translatable error message
            wp_send_json_error(array('message' => esc_html__('Permission denied.', 'external-links-overview')));
            return; // Exit early
        }

        // Sanitize input link ID
        $link_id = isset($_POST['link_id']) ? intval($_POST['link_id']) : 0;

        if (!$link_id || $link_id <= 0) {
            // Return escaped translatable error message
            wp_send_json_error(array('message' => esc_html__('Invalid Link ID.', 'external-links-overview')));
            return; // Exit early
        }

        // Update link status using the database handler instance
        $updated = $this->database->update_link_status(
            $link_id,
            false, // Mark as not broken (is_broken = 0)
            200,   // Set HTTP status to 200 OK as manually verified
            true   // This is a manual reset, so also un-ignore the link
            // Note: update_link_status also sets last_checked time automatically
        );

        // Send response based on update result
        if ($updated !== false) { // Check against false (0 rows updated is not an error)
            wp_send_json_success(array(
                // Return escaped translatable success message
                'message' => esc_html__('Link status successfully marked as OK.', 'external-links-overview'),
                'link_id' => $link_id // Return link ID for potential UI updates
            ));
        } else {
             // Log error occurred during update
            global $wpdb;
            error_log("SEOKELO Ajax Error resetting link status for ID {$link_id}: " . $wpdb->last_error);
            // Return escaped translatable error message
            wp_send_json_error(array(
                'message' => esc_html__('Could not update link status in the database.', 'external-links-overview'),
                'db_error' => $wpdb->last_error // Optionally include DB error for debugging (might expose info)
                ));
        }
    }

    /**
     * AJAX handler to ignore or unignore a link.
     */
    public function ignore_link_status_ajax() {
        check_ajax_referer('seokelo_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Permission denied.', 'external-links-overview')]);
            return;
        }

        $link_id = isset($_POST['link_id']) ? intval($_POST['link_id']) : 0;
        $status  = isset($_POST['status']) ? intval($_POST['status']) : 0; // 1 to ignore, 0 to unignore

        if (!$link_id || $link_id <= 0) {
            wp_send_json_error(['message' => esc_html__('Invalid Link ID.', 'external-links-overview')]);
            return;
        }

        $updated = $this->database->set_link_ignored_status($link_id, $status);

        if ($updated !== false) {
            $message = ($status === 1)
                ? esc_html__('Link successfully marked as ignored.', 'external-links-overview')
                : esc_html__('Link is no longer ignored.', 'external-links-overview');
            wp_send_json_success([
                'message' => $message,
                'link_id' => $link_id,
                'status'  => $status,
            ]);
        } else {
            global $wpdb;
            error_log("SEOKELO Ajax Error setting ignore status for ID {$link_id}: " . $wpdb->last_error);
            wp_send_json_error([
                'message' => esc_html__('Could not update link ignore status in the database.', 'external-links-overview'),
            ]);
        }
    }
    
    /**
     * AJAX handler to get all domain metrics for the chart view.
     */
    public function get_domain_metrics_ajax() {
        check_ajax_referer('seokelo_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Permission denied.', 'external-links-overview')]);
            return;
        }
    
        // Fetch all domain metrics, not paginated
        $data = $this->database->get_domain_metrics(1, 'all');
    
        if (isset($data['metrics'])) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error(['message' => esc_html__('Could not retrieve domain data.', 'external-links-overview')]);
        }
    }

} // End Class SEOKELO_Ajax_Handler