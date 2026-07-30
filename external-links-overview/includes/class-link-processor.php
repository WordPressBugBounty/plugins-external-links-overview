<?php
/**
 * Link Processor for the plugin (Free Version)
 * Renamed to SEOKELO_Link_Processor.
 * Uses prefixed options/constants/filters. References renamed classes.
 * Replaced parse_url with wp_parse_url.
 *
 * @since      1.0
 * @package    External_Links_Overview
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Link Processor Class - Renamed
 */
class SEOKELO_Link_Processor {
    /**
     * @var SEOKELO_Database_Handler
     */
    private $database;
    private $collection_batch_size = 50; // Posts per batch
    private $checking_batch_size = 25;   // Links per batch

    /**
     * Constructor
     * @param SEOKELO_Database_Handler $database Instance of the database handler.
     */
    public function __construct(SEOKELO_Database_Handler $database) {
        $this->database = $database;
    }

    /**
     * Init placeholder
     */
    public function init() {}

    /**
     * Main function: Collect external links (batch processing)
     */
     public function collect_external_links_batch($process_only_updated = false) {
         global $wpdb;
         @set_time_limit(120);

         $batch_offset_option = $this->database->get_batch_offset_option();
         $table_name = $this->database->get_external_table_name();
         $posts_to_update_option = 'seokelo_posts_to_update';
         $total_posts_scan_option = 'seokelo_total_posts_to_scan';

         $offset = (int) get_option($batch_offset_option, 0);

         if ($offset == 0 && !$process_only_updated) {
             delete_option($total_posts_scan_option);
             // Instead of truncating, we'll clean up links from non-existent posts
             $all_post_ids_in_db = $wpdb->get_col("SELECT DISTINCT quelle_id FROM {$table_name}");
             if (!empty($all_post_ids_in_db)) {
                 $args = ['post_type' => 'any', 'post__in' => $all_post_ids_in_db, 'fields' => 'ids', 'posts_per_page' => -1, 'post_status' => 'any'];
                 $existing_posts = get_posts($args);
                 $deleted_post_ids = array_diff($all_post_ids_in_db, $existing_posts);
                 if (!empty($deleted_post_ids)) {
                     $in_clause = implode(',', array_map('intval', $deleted_post_ids));
                     $wpdb->query("DELETE FROM {$table_name} WHERE quelle_id IN ({$in_clause})");
                 }
             }
         }

         try {
             $post_ids_to_process = array();
             if ($process_only_updated) {
                 $posts_to_update = get_option($posts_to_update_option, array());
                 if (empty($posts_to_update)) {
                     update_option($batch_offset_option, 0);
                     return false;
                 }
                 $all_update_ids = array_keys($posts_to_update);
                 $post_ids_to_process = array_slice($all_update_ids, $offset, $this->collection_batch_size);
                 update_option($total_posts_scan_option, count($all_update_ids));
             } else {
                 $allowed_post_types = apply_filters('seokelo_allowed_post_types', array('post', 'page'));
                 $args = [
                     'post_type' => $allowed_post_types, 'posts_per_page' => $this->collection_batch_size,
                     'offset' => $offset, 'post_status' => ['publish', 'private'],
                     'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids', 'no_found_rows' => true
                 ];
                 $post_ids_to_process = get_posts($args);

                 if ($offset === 0) {
                       $count_args = $args;
                       $count_args['posts_per_page'] = -1;
                       unset($count_args['offset'], $count_args['no_found_rows']);
                       $total_posts_query = new WP_Query($count_args);
                       update_option($total_posts_scan_option, $total_posts_query->post_count);
                  }
             }

             if (empty($post_ids_to_process)) {
                 update_option($batch_offset_option, 0);
                 if ($process_only_updated) { update_option($posts_to_update_option, array()); }
                 return false;
             }
             
             $processed_count_in_batch = 0;
             foreach ($post_ids_to_process as $post_id) {
                  $post_id = intval($post_id);
                  $post = get_post($post_id);

                  if (!$post) continue;

                  $content_filtered = apply_filters('the_content', $post->post_content);
                  $this->extract_and_store_external_links($content_filtered, $post_id, $post->post_title);

                  if ($process_only_updated) {
                      $posts_to_update = get_option($posts_to_update_option, array());
                      unset($posts_to_update[$post_id]);
                      update_option($posts_to_update_option, $posts_to_update);
                  }
                 $processed_count_in_batch++;
             }

             update_option($batch_offset_option, $offset + $processed_count_in_batch);
             return true;

         } catch (Exception $e) {
             error_log("SEOKELO Error during collection batch: " . $e->getMessage());
             return false;
         }
     }
    
    /**
     * Clean HTML content before link extraction
     */
    private function clean_content_for_link_extraction($content) {
        if (empty($content)) return '';
        $content = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $content);
        return $content;
    }

    /**
     * Check if a link should be ignored - Uses wp_parse_url and prefixed filter.
     */
    private function should_ignore_link($url, $text, $full_tag) {
         $url = trim($url);
         if (empty($url)) return true;
         if (strpos($url, '#') === 0) return true;
         $url_scheme = wp_parse_url($url, PHP_URL_SCHEME);
         if (empty($url_scheme) && strpos($url, '#') !== false && strpos($url, '/') === false) return true;
         if (in_array($url_scheme, ['mailto', 'tel', 'javascript'])) return true;
         if (strpos($url, 'wp-login.php') !== false || strpos($url, '/wp-admin/') !== false) return true;
         if (preg_match('/class="[^"]*\b(button|btn|submit)\b[^"]*"/i', $full_tag)) return true;
         if (preg_match('/role="button"/i', $full_tag)) return true;

         $cleaned_text = trim(wp_strip_all_tags($text));
         if (empty($cleaned_text) && strpos($text, '<img') === false && strpos($text, '<svg') === false) {
             return true;
         }
         return apply_filters('seokelo_should_ignore_link', false, $url, $text, $full_tag);
     }
    
    /**
     * Extract external links from HTML content and upsert them into the database.
     */
     private function extract_and_store_external_links($content, $post_id, $post_title) {
         if (empty($content)) { return; }
         $content = $this->clean_content_for_link_extraction($content);

         $source_url = get_permalink($post_id);
         $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
         if (!$site_host) $site_host = '';
         $site_host_variants = [strtolower($site_host)];
         if (strpos($site_host, 'www.') === 0) {
             $site_host_variants[] = strtolower(substr($site_host, 4));
         } else if (!empty($site_host)) {
             $site_host_variants[] = strtolower('www.' . $site_host);
         }
         $site_host_variants = array_filter($site_host_variants);

         preg_match_all('/<a\s+([^>]*?)href=([\'"])(.*?)\2([^>]*?)>(.*?)<\/a>/si', $content, $matches, PREG_SET_ORDER);
         
         $links_on_page = [];
         if (!empty($matches)) {
             foreach ($matches as $match) {
                 $link_url_raw = isset($match[3]) ? trim($match[3]) : '';
                 $link_url = esc_url_raw($link_url_raw);

                 if (empty($link_url) || $this->should_ignore_link($link_url, $match[5], $match[0])) {
                     continue;
                 }

                 if ($this->is_external_link($link_url, $site_host_variants)) {
                     $link_host = strtolower(wp_parse_url($link_url, PHP_URL_HOST) ?? '');
                     if(empty($link_host)) continue;

                     $full_tag_attrs = trim($match[1] . ' ' . $match[4]);
                     
                     preg_match('/rel=([\'"])(.*?)\1/i', $full_tag_attrs, $rel_match);
                     $link_rel = isset($rel_match[2]) ? trim($rel_match[2]) : null;

                     preg_match('/target=([\'"])(.*?)\1/i', $full_tag_attrs, $target_match);
                     $link_target = isset($target_match[2]) ? trim($target_match[2]) : null;

                     $anchor_html = $match[5];
                     $anchor_text = trim(wp_strip_all_tags($anchor_html));
                     if(empty($anchor_text) && preg_match('/<img[^>]+alt=([\'"])(.*?)\1/i', $anchor_html, $img_match)) {
                         $anchor_text = trim($img_match[2]) ?: __('Image Link', 'external-links-overview');
                     } elseif (empty($anchor_text)) {
                         $anchor_text = __('[Empty Anchor]', 'external-links-overview');
                     }

                     $links_on_page[] = [
                         'ankertext'    => mb_substr($anchor_text, 0, 500),
                         'link_url'     => $link_url,
                         'link_rel'     => mb_substr($link_rel, 0, 255),
                         'link_target'  => mb_substr($link_target, 0, 50),
                         'quelle_id'    => $post_id,
                         'quelle_titel' => $post_title,
                         'quelle_url'   => $source_url,
                         'ziel_domain'  => $link_host,
                     ];
                 }
             }
         }
         
         if (!empty($links_on_page)) {
             $this->database->bulk_upsert_external_links($links_on_page);
         }
         
         global $wpdb;
         $table_name = $this->database->get_external_table_name();
         $db_links_for_post = $wpdb->get_col($wpdb->prepare("SELECT link_url FROM {$table_name} WHERE quelle_id = %d", $post_id));
         $page_link_urls = array_column($links_on_page, 'link_url');
         $links_to_delete = array_diff($db_links_for_post, $page_link_urls);
         
         if (!empty($links_to_delete)) {
             $placeholders = implode(', ', array_fill(0, count($links_to_delete), '%s'));
             $query_args = array_merge([$post_id], $links_to_delete);
             $query = $wpdb->prepare("DELETE FROM {$table_name} WHERE quelle_id = %d AND link_url IN ($placeholders)", $query_args);
             $wpdb->query($query);
         }
     }

    /**
     * Check if a link is external - Uses wp_parse_url
     */
     private function is_external_link($link_url, $site_host_variants) {
         $link_host_raw = wp_parse_url($link_url, PHP_URL_HOST);
         $link_host = $link_host_raw ? strtolower($link_host_raw) : '';

         if ($link_host && !in_array($link_host, $site_host_variants, true)) {
             return true;
         }
         return false;
     }

    /**
     * Check the status of a given URL using WordPress HTTP API - Uses prefixed filters/constants.
     */
     public function check_external_url_status($url) {
         $args = array(
             'timeout'     => apply_filters('seokelo_link_check_timeout', 10),
             'redirection' => apply_filters('seokelo_link_check_redirections', 5),
             'sslverify'   => apply_filters('seokelo_ssl_verify', true),
             'user-agent'  => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . '; SEOKELO-Link-Checker/' . SEOKELO_VERSION,
             'headers'     => ['Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8', 'Accept-Language' => 'en-US,en;q=0.5']
         );

         $status_code = null;
         $is_broken = true;

         $response = wp_remote_head($url, $args);

         if (!is_wp_error($response)) {
             $status_code = wp_remote_retrieve_response_code($response);
             if ($status_code && ($status_code < 400 || in_array($status_code, apply_filters('seokelo_allowed_head_error_codes', [401, 403, 405])))) {
                  $is_broken = false;
             }
         } else {
              $args['method'] = 'GET';
              $response = wp_remote_get($url, $args);
              if (!is_wp_error($response)) {
                   $status_code = wp_remote_retrieve_response_code($response);
                   if ($status_code && $status_code < 400) {
                        $is_broken = false;
                   }
              }
         }

         return array(
             'is_broken' => $is_broken,
             'http_status' => $status_code
         );
     }

    /**
     * Calculate current processing progress (for collection or update) - Uses prefixed options/filters.
     */
     public function calculate_collection_progress($process_only_updated) {
         $batch_offset_option = $this->database->get_batch_offset_option();
         $total_posts_scan_option = 'seokelo_total_posts_to_scan';

         $current_offset = intval(get_option($batch_offset_option, 0));
         $total_items = (int) get_option($total_posts_scan_option, 0);
         
         $progress = ($total_items > 0) ? min(100, round(($current_offset / $total_items) * 100)) : 100;

         return array(
             'progress'       => $progress,
             'current_offset' => $current_offset,
             'total_items'    => $total_items,
             'link_count'     => $this->database->get_total_external_links_count(),
             'type'           => $process_only_updated ? 'update' : 'collect'
         );
     }

     /**
      * Perform batch checking of external link statuses - Uses prefixed options/filters.
      */
      public function check_links_batch() {
          @set_time_limit(180);

          $batch_offset_check_option = $this->database->get_batch_offset_check_option();
          $offset = (int) get_option($batch_offset_check_option, 0);

          $links_to_check = $this->database->get_links_for_checking($offset, $this->checking_batch_size);

          if (empty($links_to_check)) {
              update_option($batch_offset_check_option, 0);
              update_option('seokelo_last_external_check_time', time());
              return false;
          }

          foreach ($links_to_check as $link) {
              if (empty($link->link_url)) continue;
              $status = $this->check_external_url_status($link->link_url);
              $this->database->update_link_status($link->id, $status['is_broken'], $status['http_status']);
              usleep(apply_filters('seokelo_link_check_delay_microseconds', 150000));
          }
          update_option($batch_offset_check_option, $offset + count($links_to_check));
          return true;
      }

       /**
        * Calculate progress for the link checking process - Uses prefixed options.
        */
       public function calculate_checking_progress() {
           $batch_offset_check_option = $this->database->get_batch_offset_check_option();
           $current_offset = (int) get_option($batch_offset_check_option, 0);
           $total_items = $this->database->get_total_external_links_count();
           $progress = ($total_items > 0) ? min(100, round(($current_offset / $total_items) * 100)) : 100;

           global $wpdb;
           $table_name = $this->database->get_external_table_name();
           $broken_count = (int) $wpdb->get_var($wpdb->prepare(
               "SELECT COUNT(*) FROM {$table_name} WHERE is_broken = %d", 1
           ));

           return array(
               'progress'       => $progress,
               'current_offset' => $current_offset,
               'total_items'    => $total_items,
               'broken_count'   => $broken_count,
               'type'           => 'check'
           );
       }

} // End Class SEOKELO_Link_Processor