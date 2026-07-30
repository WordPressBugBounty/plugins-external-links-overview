<?php
/**
 * Database Handler for the plugin (Free Version)
 * Renamed to SEOKELO_Database_Handler.
 * Prefixed table name and option names.
 * Added/improved SQL escaping and error checks.
 *
 * @since      1.0
 * @package    External_Links_Overview
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Handler Class - Renamed
 */
class SEOKELO_Database_Handler {

    /**
     * Table name for external links - Prefixed
     * @var string
     */
    private $external_table_name;

    /**
     * Option for the batch offset (link collection) - Prefixed
     * @var string
     */
    private $batch_offset_option = 'seokelo_batch_offset';

    /**
     * Option for the batch offset (link checking) - Prefixed
     * @var string
     */
    private $batch_offset_check_option = 'seokelo_batch_offset_check';

    /**
     * Option for the cache timestamp - Prefixed
     * @var string
     */
    private $cache_timestamp_option = 'seokelo_cache_timestamp';

    /**
     * Constructor - Sets prefixed table name
     */
    public function __construct() {
        global $wpdb;
        // Use prefixed table name
        $this->external_table_name = $wpdb->prefix . 'seokelo_external_links';
    }

    /**
     * Initialize the class (placeholder)
     */
    public function init() {
        // No specific initialization needed currently
    }

    /**
     * Get the table name for external links - Prefixed
     *
     * @return string Table name
     */
    public function get_external_table_name() {
        return $this->external_table_name;
    }

    /**
     * Get the name of the batch offset option (collection) - Prefixed
     *
     * @return string Option name
     */
    public function get_batch_offset_option() {
        return $this->batch_offset_option;
    }

     /**
      * Get the name of the batch offset option (checking) - Prefixed
      *
      * @return string Option name
      */
     public function get_batch_offset_check_option() {
         return $this->batch_offset_check_option;
     }


    /**
     * Get the name of the cache timestamp option - Prefixed
     *
     * @return string Option name
     */
    public function get_cache_timestamp_option() {
        return $this->cache_timestamp_option;
    }

    /**
     * Create or update the necessary database table for external links.
     * Includes the new link_target column.
     */
    public function create_tables() {
        global $wpdb;
        $table_name = $this->get_external_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        // Define the SQL query for the external links table
        $sql_external = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ankertext text DEFAULT NULL,
            link_url text NOT NULL,
            link_rel varchar(255) DEFAULT NULL,
            link_target varchar(50) DEFAULT NULL,
            quelle_id bigint(20) unsigned NOT NULL,
            quelle_titel text DEFAULT NULL,
            quelle_url text DEFAULT NULL,
            ziel_domain varchar(255) DEFAULT NULL,
            ziel_titel text DEFAULT NULL,
            is_broken tinyint(1) NOT NULL DEFAULT 0,
            is_ignored tinyint(1) NOT NULL DEFAULT 0,
            last_checked datetime DEFAULT NULL,
            http_status smallint(5) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY quelle_id (quelle_id),
            KEY ziel_domain (ziel_domain(191)),
            KEY is_broken (is_broken),
            KEY is_ignored (is_ignored)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_external);
    }


    /**
     * Perform a bulk insert or update for multiple external links.
     *
     * @param array $links_data Array of arrays, each containing link data.
     * @return array Counts of inserted, updated, and failed links.
     */
     public function bulk_upsert_external_links($links_data) {
         global $wpdb;
         if (empty($links_data) || !is_array($links_data)) {
             return ['inserted' => 0, 'updated' => 0, 'failed' => 0];
         }

         $table_name = $this->get_external_table_name();
         $results = ['inserted' => 0, 'updated' => 0, 'failed' => 0];

         foreach ($links_data as $link) {
            $link_url = isset($link['link_url']) ? $link['link_url'] : '';
            $quelle_id = isset($link['quelle_id']) ? intval($link['quelle_id']) : 0;
            
            if (empty($link_url) || $quelle_id <= 0) {
                $results['failed']++;
                continue;
            }

            // Check if a link with the same URL and source ID already exists
            $existing_link_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE link_url = %s AND quelle_id = %d",
                $link_url,
                $quelle_id
            ));

            if ($existing_link_id) {
                // UPDATE: Only update core data
                $data_to_update = [
                    'ankertext'    => $link['ankertext'],
                    'link_rel'     => $link['link_rel'],
                    'link_target'  => $link['link_target'],
                    'quelle_titel' => $link['quelle_titel'],
                    'quelle_url'   => $link['quelle_url'],
                    'ziel_domain'  => $link['ziel_domain'],
                ];
                $where = ['id' => $existing_link_id];
                $updated = $wpdb->update($table_name, $data_to_update, $where);
                if ($updated !== false) {
                    $results['updated']++;
                } else {
                    $results['failed']++;
                }
            } else {
                // INSERT: New link, insert all data
                $data_to_insert = [
                    'ankertext'     => $link['ankertext'],
                    'link_url'      => $link['link_url'],
                    'link_rel'      => $link['link_rel'],
                    'link_target'   => $link['link_target'],
                    'quelle_id'     => $quelle_id,
                    'quelle_titel'  => $link['quelle_titel'],
                    'quelle_url'    => $link['quelle_url'],
                    'ziel_domain'   => $link['ziel_domain'],
                ];
                $inserted = $wpdb->insert($table_name, $data_to_insert);
                if ($inserted) {
                    $results['inserted']++;
                } else {
                    $results['failed']++;
                }
            }
        }
         return $results;
     }

    /**
     * Delete all external link data - Uses prefixed options/table
     */
    public function delete_all_external_data() {
        global $wpdb;
        $table_name = $this->get_external_table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- TRUNCATE cannot be prepared.
        $wpdb->query("TRUNCATE TABLE {$table_name}");

        update_option($this->batch_offset_option, 0);
        update_option($this->batch_offset_check_option, 0);
        update_option('seokelo_posts_to_update', array());
        update_option($this->cache_timestamp_option, time());
        delete_option('seokelo_total_posts_to_scan');

        wp_cache_flush();
    }


    /**
     * Get external links from the database, filtered and sorted.
     */
    public function get_external_links($search_term = '', $order_by = 'id', $order = 'ASC', $filter_broken = false, $current_page = 1, $items_per_page = 20) {
        global $wpdb;
        $table_name = $this->get_external_table_name();

        $where_clauses = array();
        $params = array();

        if (strpos($search_term, 'domain:') === 0) {
            $domain = substr($search_term, 7);
            $where_clauses[] = "ziel_domain = %s";
            $params[] = $domain;
        } elseif (strpos($search_term, 'source_id:') === 0) {
            $source_id = intval(str_replace('source_id:', '', $search_term));
            if ($source_id > 0) {
                 $where_clauses[] = "quelle_id = %d";
                 $params[] = $source_id;
            }
        }
        elseif (!empty($search_term)) {
            $like = '%' . $wpdb->esc_like($search_term) . '%';
            $where_clauses[] = "(
                ankertext LIKE %s OR
                link_url LIKE %s OR
                CAST(quelle_id AS CHAR) LIKE %s OR
                quelle_titel LIKE %s OR
                quelle_url LIKE %s OR
                ziel_domain LIKE %s OR
                ziel_titel LIKE %s OR
                link_rel LIKE %s OR
                link_target LIKE %s
            )";
            $params = array_fill(0, 9, $like);
        }

        if ($filter_broken === true) {
            $where_clauses[] = "is_broken = %d";
            $params[] = 1;
        }

        $where_sql = "";
        if (!empty($where_clauses)) {
            $where_sql = " WHERE " . implode(' AND ', $where_clauses);
        }

        $count_sql = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
        $prepared_count_sql = $wpdb->prepare($count_sql, $params);
        if ($prepared_count_sql === false) {
            error_log("SEOKELO DB Prepare Error for count query: " . $wpdb->last_error);
            $total_items = 0;
        } else {
            $total_items = $wpdb->get_var($prepared_count_sql);
            if ($wpdb->last_error) error_log("SEOKELO DB Error executing count query: " . $wpdb->last_error);
        }
        $total_items = (int) $total_items;

        $sql = "SELECT * FROM {$table_name} {$where_sql}";

        $allowed_columns = array('id', 'quelle_id', 'quelle_titel', 'ankertext', 'link_url', 'link_rel', 'link_target', 'is_broken', 'is_ignored', 'last_checked');
        if (!in_array($order_by, $allowed_columns, true)) {
             $order_by = 'id';
        }
        $sql .= " ORDER BY `{$order_by}` {$order}";

        $limit_params = array();
        $total_pages = 1;
        if ($items_per_page !== 'all' && $total_items > 0) {
            $offset = ($current_page - 1) * $items_per_page;
            $sql .= " LIMIT %d, %d";
            $limit_params[] = $offset;
            $limit_params[] = $items_per_page;
            $total_pages = ceil($total_items / $items_per_page);
        } elseif ($items_per_page !== 'all') {
            $total_items = 0;
        }

        $final_params = array_merge($params, $limit_params);
        $links = array();
        if ($items_per_page === 'all' || $total_items > 0) {
             $prepared_sql = $wpdb->prepare($sql, $final_params);
            if($prepared_sql) {
                 $links = $wpdb->get_results($prepared_sql, OBJECT);
                 if($wpdb->last_error) error_log("SEOKELO DB Error executing main link query: " . $wpdb->last_error);
            } else {
                 error_log("SEOKELO DB Prepare Error for main link query: " . $wpdb->last_error);
            }
        }

        return array(
            'links' => is_array($links) ? $links : array(),
            'total_items' => (int)$total_items,
            'total_pages' => (int)$total_pages
        );
    }


    /**
     * Get domain metrics for external links - Uses prefixed table name
     */
    public function get_domain_metrics($current_page = 1, $items_per_page = 20) {
        global $wpdb;
        $table_name = $this->get_external_table_name();

        $where_sql = " WHERE ziel_domain IS NOT NULL AND ziel_domain != ''";

        $count_sql = "SELECT COUNT(DISTINCT ziel_domain) FROM {$table_name} {$where_sql}";
        $total_items = $wpdb->get_var($count_sql);
        if ($wpdb->last_error) error_log("SEOKELO DB Error executing domain count query: " . $wpdb->last_error);
        $total_items = (int) $total_items;

        $sql = "SELECT ziel_domain, COUNT(*) as link_count, COUNT(DISTINCT quelle_id) as source_count
                FROM {$table_name}
                {$where_sql}
                GROUP BY ziel_domain
                ORDER BY link_count DESC, ziel_domain ASC";

        $total_pages = 1;
        $params = array();

        if ($items_per_page !== 'all' && $total_items > 0) {
             $offset = ($current_page - 1) * $items_per_page;
             $sql .= " LIMIT %d, %d";
             $params[] = $offset;
             $params[] = $items_per_page;
             $total_pages = ceil($total_items / $items_per_page);
        } elseif ($items_per_page !== 'all') {
            $total_items = 0;
        }

        $results = array();
        if ($items_per_page === 'all' || $total_items > 0) {
            $prepared_sql = $wpdb->prepare($sql, $params);
            if($prepared_sql) {
                $results = $wpdb->get_results($prepared_sql, OBJECT);
                if($wpdb->last_error) error_log("SEOKELO DB Error executing domain metrics query: " . $wpdb->last_error);
            } else {
                  error_log("SEOKELO DB Prepare Error for domain metrics query: " . $wpdb->last_error);
            }
        }

        $max_links_query = "SELECT MAX(link_count) FROM (SELECT COUNT(*) as link_count FROM {$table_name} {$where_sql} GROUP BY ziel_domain) as counts";
        $max_links = $wpdb->get_var($max_links_query);
        if ($wpdb->last_error) error_log("SEOKELO DB Error executing max links query: " . $wpdb->last_error);

        return array(
            'metrics' => is_array($results) ? $results : array(),
            'total_items' => (int)$total_items,
            'total_pages' => (int)$total_pages,
            'max_links' => max(1, (int)$max_links)
        );
    }

     /**
      * Get links for the checking process in batches. Uses prefixed table name.
      */
     public function get_links_for_checking($offset, $limit) {
         global $wpdb;
         $table_name = $this->get_external_table_name();
         $offset = max(0, intval($offset));
         $limit = max(0, intval($limit));

         if ($limit <= 0) return array();

         $query = $wpdb->prepare(
            "SELECT id, link_url FROM {$table_name} ORDER BY id ASC LIMIT %d, %d",
            $offset,
            $limit
         );

         if (!$query) {
             error_log("SEOKELO DB Prepare Error for get_links_for_checking: " . $wpdb->last_error);
             return array();
         }

         $results = $wpdb->get_results($query);
          if ($wpdb->last_error) {
                error_log("SEOKELO DB Error executing get_links_for_checking: " . $wpdb->last_error);
                return array();
            }
         return $results;
     }

      /**
       * Get the total count of external links for the checking process. Uses prefixed table name.
       */
      public function get_total_external_links_count() {
          global $wpdb;
          $table_name = $this->get_external_table_name();
          $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
           if ($wpdb->last_error) {
                error_log("SEOKELO DB Error executing get_total_external_links_count: " . $wpdb->last_error);
                return 0;
            }
          return (int) $count;
      }

     /**
      * Update the status of a single link after checking. Uses prefixed table name.
      */
      public function update_link_status($link_id, $is_broken, $http_status = null, $manual_reset = false) {
         global $wpdb;
         $table_name = $this->get_external_table_name();

         $link_id = intval($link_id);
         if ($link_id <= 0) {
             return false;
         }

         $data = array(
             'is_broken'    => $is_broken ? 1 : 0,
             'last_checked' => current_time('mysql', 1),
             'http_status'  => is_null($http_status) ? null : intval($http_status),
         );

         $format = array(
             '%d',
             '%s',
         );
         $format[] = is_null($data['http_status']) ? '%s' : '%d';

         if ($manual_reset) {
             $data['is_ignored'] = 0;
             $format[] = '%d';
         }

         $where = array('id' => $link_id);
         $where_format = array('%d');

         $result = $wpdb->update(
             $table_name,
             $data,
             $where,
             $format,
             $where_format
         );

         if ($result === false) {
             error_log("SEOKELO DB Error updating link status for ID {$link_id}: " . $wpdb->last_error);
             return false;
         }
         return $result;
     }

    /**
     * Update the ignored status of a single link.
     *
     * @param int  $link_id      The ID of the link to update.
     * @param bool $is_ignored   True to mark as ignored, false to un-ignore.
     * @return int|false Number of rows updated, or false on error.
     */
    public function set_link_ignored_status($link_id, $is_ignored) {
        global $wpdb;
        $table_name = $this->get_external_table_name();

        $link_id = intval($link_id);
        if ($link_id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            $table_name,
            array('is_ignored' => $is_ignored ? 1 : 0),
            array('id' => $link_id),
            array('%d'),
            array('%d')
        );

        if ($result === false) {
            error_log("SEOKELO DB Error updating ignore status for ID {$link_id}: " . $wpdb->last_error);
        }
        return $result;
    }
} // End Class SEOKELO_Database_Handler