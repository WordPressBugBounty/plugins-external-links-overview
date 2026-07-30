<?php
/**
 * Admin Class for the plugin (Free Version)
 * Renamed to SEOKELO_Admin and prefixed elements.
 * Corrected sanitization and escaping. Added Target column support.
 *
 * @since      1.0
 * @package    External_Links_Overview
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Class - Renamed
 */
class SEOKELO_Admin {
    private $link_processor;
    private $database;
    private $items_per_page_options = array(20, 50, 100, 250, 'all');
    private $default_items_per_page = 50;
    private $plugin_slug = 'external-links-overview'; // Keep original slug for menu page URL

    /**
     * Constructor
     * @param SEOKELO_Link_Processor   $link_processor Instance of link processor.
     * @param SEOKELO_Database_Handler $database       Instance of database handler.
     */
    public function __construct(SEOKELO_Link_Processor $link_processor, SEOKELO_Database_Handler $database) {
        $this->link_processor = $link_processor;
        $this->database = $database;
    }

    /**
     * Initialize admin hooks.
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Add column to allowed post types - Use prefixed filter
        $allowed_post_types = apply_filters('seokelo_allowed_post_types', array('post', 'page'));
        if (!empty($allowed_post_types) && is_array($allowed_post_types)) {
            foreach ($allowed_post_types as $post_type) {
                // Sanitize post type key just in case
                $post_type = sanitize_key($post_type);
                if (post_type_exists($post_type)) {
                    add_filter("manage_{$post_type}_posts_columns", array($this, 'add_external_links_column'));
                    add_action("manage_{$post_type}_posts_custom_column", array($this, 'display_external_links_column'), 10, 2);
                }
            }
        }
    }

    /**
     * Add admin menu page.
     */
    public function add_admin_menu() {
        add_menu_page(
            esc_html__('External Links Overview', 'external-links-overview'), // Page title
            esc_html__('External Links', 'external-links-overview'),       // Menu title
            'manage_options',                                             // Capability
            $this->plugin_slug,                                           // Menu slug (keep original for URL stability)
            array($this, 'display_external_links_page'),                  // Callback function
            'dashicons-admin-links',                                      // Icon
            61                                                            // Position
        );
    }

    /**
     * Enqueue admin assets (CSS & JS).
     * Uses prefixed handles and localization object.
     */
    public function enqueue_admin_assets($hook) {
        // Only load on the plugin's admin page
        if ($hook !== 'toplevel_page_' . $this->plugin_slug) {
            return;
        }

        // Admin CSS
        wp_enqueue_style('seokelo-admin-css', SEOKELO_PLUGIN_URL . 'assets/css/admin-style.css', array(), SEOKELO_VERSION);
        
        // Chart.js
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.1', true);
        
        // Admin JS
        wp_enqueue_script('seokelo-admin-js', SEOKELO_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery', 'chart-js'), SEOKELO_VERSION, true);


        // Localize script for AJAX using prefixed object name and nonce action
        wp_localize_script('seokelo-admin-js', 'seokelo_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seokelo_ajax_nonce'),
            'plugin_slug' => $this->plugin_slug,
            'processing_text'           => esc_html__('Processing...', 'external-links-overview'),
            'complete_text'             => esc_html__('Complete!', 'external-links-overview'),
            'error_text'                => esc_html__('An error occurred during processing. Please check the browser console or server logs for details.', 'external-links-overview'),
            'confirm_delete'            => esc_html__('Are you sure you want to delete ALL external link data? This cannot be undone!', 'external-links-overview'),
            'confirm_cancel'            => esc_html__('Are you sure you want to cancel the current process?', 'external-links-overview'),
            'status_reset_success'      => esc_html__('Link status successfully marked as OK.', 'external-links-overview'),
            'status_ignore_success'     => esc_html__('Link successfully marked as ignored.', 'external-links-overview'),
            'status_reset_error'        => esc_html__('Could not update link status in the database.', 'external-links-overview'),
            'network_error'             => esc_html__('A network error occurred (e.g., timeout or connection issue). Please check your connection and try again.', 'external-links-overview'),
            'collect_action_text'       => esc_html__('Collecting all external links', 'external-links-overview'),
            'update_action_text'        => esc_html__('Updating links from changed posts', 'external-links-overview'),
            'check_action_text'         => esc_html__('Checking external link status', 'external-links-overview'),
            'starting_text'             => esc_html__('Starting...', 'external-links-overview'),
            'processed_posts_text'      => esc_html__('Posts Processed:', 'external-links-overview'),
            'found_links_text'          => esc_html__('Links Found:', 'external-links-overview'),
            'links_checked_text'        => esc_html__('Links Checked:', 'external-links-overview'),
            'broken_links_text'         => esc_html__('Broken Found:', 'external-links-overview'),
            'estimate_text'             => esc_html__('Est. time remaining:', 'external-links-overview'),
            'calculating_text'          => esc_html__('Calculating...', 'external-links-overview'),
            'seconds_text'              => esc_html_x('sec', 'time unit seconds short', 'external-links-overview'),
            'minutes_text'              => esc_html_x('min', 'time unit minutes short', 'external-links-overview'),
            'hours_text'                => esc_html_x('hr', 'time unit hours short', 'external-links-overview'),
            'page_reload_text'          => esc_html__('Reload Page', 'external-links-overview'),
            'processing_cancelled_text' => esc_html__('Process (%s) was cancelled.', 'external-links-overview'),
            'already_running_text'      => esc_html__('Please wait for the current process (%s) to finish before starting another.', 'external-links-overview'),
            'mark_as_ok_text'           => esc_html__('Mark as OK', 'external-links-overview'),
            'ignore_text'               => esc_html__('Ignore', 'external-links-overview'),
            'unignore_text'             => esc_html__('Unignore', 'external-links-overview'),
            'status_ok_text'            => esc_html__('OK', 'external-links-overview'),
            'status_broken_text'        => esc_html__('Broken', 'external-links-overview'),
            'status_ignored_text'       => esc_html__('Ignored', 'external-links-overview'),
            'last_checked_text'         => esc_html__('Last Checked', 'external-links-overview'),
            'just_now_text'             => esc_html__('Just now', 'external-links-overview'),
            'action_collect'            => 'collect',
            'action_update'             => 'update',
            'action_check'              => 'check',
            'action_ignore'             => 'seokelo_ignore_link_status',
        ));
    }

    /**
     * Helper function to get the currently selected items per page - Corrected sanitization/validation
     */
    private function get_current_items_per_page($context = 'main') {
        $param_name = ($context === 'domain') ? 'domain_per_page' : 'per_page';
        $items_per_page_raw = isset($_GET[$param_name]) ? sanitize_text_field(wp_unslash($_GET[$param_name])) : '';

        if ($items_per_page_raw === 'all') {
            return 'all';
        }

        $items_per_page = intval($items_per_page_raw);
        $allowed_numeric_options = array_filter($this->items_per_page_options, 'is_numeric');

        if (empty($items_per_page_raw) || !is_numeric($items_per_page_raw) || !in_array($items_per_page, $allowed_numeric_options, true) || $items_per_page <= 0) {
            return $this->default_items_per_page;
        }

        return $items_per_page;
    }

    /**
     * Helper function to display the items per page selector - Corrected escaping
     */
    private function display_items_per_page_selector($context = 'main') {
        $param_name = ($context === 'domain') ? 'domain_per_page' : 'per_page';
        $page_param = ($context === 'domain') ? 'domain_paged' : 'paged';
        $current_per_page = $this->get_current_items_per_page($context);

        $base_url = remove_query_arg(array($param_name, $page_param, 'orderby', 'order', 's', 'filter_broken', 'action', '_wpnonce'), $_SERVER['REQUEST_URI']);

        echo '<div class="seokelo-per-page-selector alignleft actions bulkactions">';
        echo '<label for="seokelo-per-page-select-' . esc_attr($context) . '" class="screen-reader-text">' . esc_html__('Items per page:', 'external-links-overview') . '</label>';
        echo '<span class="items-per-page-label">' . esc_html__('Items per page:', 'external-links-overview') . '</span> ';

        foreach ($this->items_per_page_options as $option) {
            $label = ($option === 'all') ? esc_html__('All', 'external-links-overview') : esc_html(number_format_i18n($option));
            $is_current = ($current_per_page == $option);
            $class = $is_current ? 'seokelo-per-page-current button button-small disabled' : 'button button-small';
            $option_url = add_query_arg(array($param_name => $option, $page_param => 1), $base_url);
            echo '<a href="' . esc_url($option_url) . '" class="' . esc_attr($class) . '">' . $label . '</a> ';
        }
        echo '</div>';
    }


    /**
     * Helper function for sort links - Added 'link_target' to allowed columns.
     */
    private function get_sort_link($column, $current_order_by, $current_order) {
        $current_page_slug = isset($_GET['page']) ? sanitize_key($_GET['page']) : $this->plugin_slug;
        $search_term = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $filter_broken = isset($_GET['filter_broken']) && $_GET['filter_broken'] === '1';

        $allowed_columns = array(
            'id', 'quelle_id', 'quelle_titel', 'ankertext', 'link_url', 'link_rel', 'link_target', 
            'is_broken', 'is_ignored', 'last_checked'
        );
        $current_order_by_safe = in_array($current_order_by, $allowed_columns, true) ? $current_order_by : 'id';
        $current_order_safe = (strtoupper($current_order) === 'DESC') ? 'desc' : 'asc';

        $params = array();
        $params['page'] = $current_page_slug;

        $params['orderby'] = in_array($column, $allowed_columns, true) ? $column : 'id';
        $params['order'] = ($current_order_by_safe === $column && $current_order_safe === 'asc') ? 'desc' : 'asc';

        if (isset($_GET['paged']) && $params['orderby'] !== $current_order_by_safe) {
             $params['paged'] = 1;
        } elseif (isset($_GET['paged'])) {
            $params['paged'] = intval($_GET['paged']);
        }
        if (isset($_GET['domain_paged']) && $params['orderby'] !== $current_order_by_safe) {
            $params['domain_paged'] = 1;
        } elseif (isset($_GET['domain_paged'])) {
             $params['domain_paged'] = intval($_GET['domain_paged']);
        }

        if (!empty($search_term)) {
            $params['s'] = $search_term;
        }
        if ($filter_broken) {
            $params['filter_broken'] = '1';
        }

        $params['per_page'] = $this->get_current_items_per_page('main');
        $params['domain_per_page'] = $this->get_current_items_per_page('domain');


        return add_query_arg($params, admin_url('admin.php'));
    }


    /**
     * Helper function for displaying pagination with "Page X of Y" and updated wording - Corrected escaping
     */
     private function display_pagination($total_items, $items_per_page, $current_page, $page_var = 'paged') {
        $total_items = max(0, intval($total_items));
        $current_page = max(1, intval($current_page));
        $page_var = sanitize_key($page_var);

         if ($items_per_page === 'all' || !is_numeric($items_per_page) || $items_per_page <= 0 || $total_items <= $items_per_page) {
              if($total_items > 0) {
                 $item_text = ($page_var === 'paged')
                    ? sprintf(esc_html(_n('%s link', '%s links', $total_items, 'external-links-overview')), number_format_i18n($total_items))
                    : sprintf(esc_html(_n('%s domain', '%s domains', $total_items, 'external-links-overview')), number_format_i18n($total_items));
                 echo '<div class="tablenav-pages seokelo-pagination no-pages">';
                 echo '<span class="displaying-num">' . $item_text . '</span>';
                 echo '</div>';
              }
              return;
         }

         $total_pages = ceil($total_items / $items_per_page);
         $page_links = paginate_links( array(
              'base' => add_query_arg( $page_var, '%#%' ),
              'format' => '',
              'prev_text' => esc_html__('&laquo; Previous', 'external-links-overview'),
              'next_text' => esc_html__('Next &raquo;', 'external-links-overview'),
              'total' => $total_pages,
              'current' => $current_page,
              'add_args' => false,
              'mid_size' => 1,
              'end_size' => 1,
              'type' => 'list',
         ) );

         if ( $page_links ) {
              echo '<div class="tablenav-pages seokelo-pagination">';
              $item_text = ($page_var === 'paged')
                 ? sprintf(esc_html(_n('%s link', '%s links', $total_items, 'external-links-overview')), number_format_i18n($total_items))
                 : sprintf(esc_html(_n('%s domain', '%s domains', $total_items, 'external-links-overview')), number_format_i18n($total_items));
              echo '<span class="displaying-num">' . $item_text . '</span>';
              echo '<span class="pagination-links">';
              echo '<span class="displaying-pages">' . sprintf(
                    esc_html__( 'Page %1$s of %2$s', 'external-links-overview' ),
                    esc_html(number_format_i18n( $current_page )),
                    esc_html(number_format_i18n( $total_pages ))
                ) . '</span>';
              // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output escaped by paginate_links()
              echo $page_links;
              echo '</span>';
              echo '</div>';
         }
     }


    /**
     * Display the main admin page for external links - Corrected sanitization and escaping
     */
    public function display_external_links_page() {
        $search_term   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $order_by      = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'id';
        $raw_order     = isset($_GET['order']) ? sanitize_key(strtolower($_GET['order'])) : 'asc';
        $order         = ($raw_order === 'desc') ? 'DESC' : 'ASC';
        $filter_broken = isset($_GET['filter_broken']) && $_GET['filter_broken'] === '1';
        
        $status_check_done = (get_option('seokelo_last_external_check_time', 0) > 0);

        $action_notice = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';

        if ($action_notice === 'deleted_external') {
             add_action('admin_notices', function() {
                  echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('All external link data has been deleted.', 'external-links-overview') . '</p></div>';
             });
        }

        $cache_timestamp_option = $this->database->get_cache_timestamp_option();
        $last_collection_update = get_option($cache_timestamp_option, 0);
        $collection_status_text = '';
        if ($last_collection_update) {
            $collection_status_text = sprintf(esc_html__('Data last collected/updated: %s', 'external-links-overview'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_collection_update + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )) );
        } else {
            $collection_status_text = esc_html__('Link data has not been collected yet.', 'external-links-overview');
        }

        $last_check_time = get_option('seokelo_last_external_check_time', 0);
        $check_status_text = '';
         if ($last_check_time) {
             $check_status_text = sprintf(esc_html__('Links last checked: %s', 'external-links-overview'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_check_time + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )) );
         } else {
             $check_status_text = esc_html__('Links have not been checked yet.', 'external-links-overview');
         }

        $posts_to_update_option = 'seokelo_posts_to_update';
        $posts_to_update = get_option($posts_to_update_option, array());
        $pending_updates = count($posts_to_update);
        $pending_updates_html = '';
        if ($pending_updates > 0) {
            $pending_updates_html = sprintf( _n('<span class="seokelo-pending-updates">%d post update waiting.</span>', '<span class="seokelo-pending-updates">%d post updates waiting.</span>', $pending_updates, 'external-links-overview'), number_format_i18n($pending_updates) );
        }
        
        include SEOKELO_PLUGIN_DIR . 'admin/views/external-links-page.php';
    }

    /**
     * Display the external links table - Passes already sanitized parameters
     */
    public function display_external_links_table($search_term = '', $order_by = 'id', $order = 'ASC', $filter_broken = false) {
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $items_per_page = $this->get_current_items_per_page('main');

        $data = $this->database->get_external_links($search_term, $order_by, $order, $filter_broken, $current_page, $items_per_page);
        $links = $data['links'];
        $total_items = $data['total_items'];

        $broken_on_page = 0;
        if (!empty($links) && is_array($links)) {
            foreach ($links as $link) {
                if (isset($link->is_broken) && $link->is_broken == 1) {
                    $broken_on_page++;
                }
            }
        }

        include SEOKELO_PLUGIN_DIR . 'admin/views/external-links-table.php';

        echo '<div class="tablenav bottom">';
        $this->display_items_per_page_selector('main');
        $this->display_pagination($total_items, $items_per_page, $current_page, 'paged');
        echo '<div class="clear"></div>';
        echo '</div>';
    }


    /**
     * Display domain metrics - Passes already sanitized parameters
     */
    public function display_domain_metrics() {
        // This function is now effectively a placeholder since the view is rendered via JS
    }

     /**
      * Add custom column header to posts/pages list - Corrected escaping
      */
     public function add_external_links_column($columns) {
         $new_columns = array();
         foreach ($columns as $key => $title) {
             $new_columns[$key] = $title;
             if ($key === 'title') {
                 $new_columns['external_links_count'] = esc_html__('Ext. Links', 'external-links-overview');
             }
         }
         if (!isset($new_columns['external_links_count'])) {
             $new_columns['external_links_count'] = esc_html__('Ext. Links', 'external-links-overview');
         }
         return $new_columns;
     }

    /**
      * Display content for the custom column - Corrected escaping and uses prefixed cache keys/groups
      */
     public function display_external_links_column($column_name, $post_id) {
         if ($column_name === 'external_links_count') {
             global $wpdb;
             if (!isset($this->database) || !$this->database instanceof SEOKELO_Database_Handler) {
                 echo '<span aria-hidden="true">&mdash;</span>';
                 return;
             }

             $table_name = $this->database->get_external_table_name();
             $post_id = intval($post_id);
             if ($post_id <= 0) {
                  echo '<span aria-hidden="true">&mdash;</span>';
                  return;
             }

             $cache_key = 'seokelo_ext_link_count_' . $post_id;
             $cache_group = 'seokelo_counts';
             $count = wp_cache_get($cache_key, $cache_group);

             if (false === $count) {
                 $prepared_sql = $wpdb->prepare(
                     "SELECT COUNT(*) FROM {$table_name} WHERE quelle_id = %d", $post_id
                 );
                 $count = $wpdb->get_var($prepared_sql);
                 if ($wpdb->last_error) {
                    error_log("SEOKELO DB Error fetching post link count for $post_id: " . $wpdb->last_error);
                    $count = 0;
                 }
                 wp_cache_set($cache_key, $count, $cache_group, HOUR_IN_SECONDS);
             }
             $count = intval($count);

             if ($count > 0) {
                 $plugin_page_url = admin_url('admin.php?page=' . $this->plugin_slug);
                 $filtered_url = add_query_arg('s', 'source_id:' . $post_id, $plugin_page_url);

                 echo '<a href="' . esc_url($filtered_url) . '" title="' . esc_attr__('View external links from this post', 'external-links-overview') .'">'
                      . esc_html(number_format_i18n($count))
                      . '</a>';
             } else {
                 echo '<span aria-hidden="true">&mdash;</span>';
             }
         }
     }

      /**
       * Clear the post link count cache when a post is updated or deleted.
       * Static method using prefixed keys/groups.
       */
       public static function clear_post_link_count_cache($post_id) {
             $post_id = intval($post_id);
             if ($post_id > 0) {
                 wp_cache_delete('seokelo_ext_link_count_' . $post_id, 'seokelo_counts');
             }
       }

} // End Class SEOKELO_Admin