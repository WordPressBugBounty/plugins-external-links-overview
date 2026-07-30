<?php
/**
 * Admin view for External Links Table (Free Version)
 * Includes Rel and Target columns. Corrected escaping. Prefixed CSS classes.
 * Assumes $links, $total_items, $broken_on_page, $order_by, $order passed and sanitized.
 * This template assumes it's included within a class context (like SEOKELO_Admin)
 * that provides helper methods like get_sort_link().
 *
 * @since      1.0
 * @package    External_Links_Overview
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($links)) { $links = array(); }
if (!isset($order_by)) { $order_by = 'id'; }
if (!isset($order)) { $order = 'ASC'; }
if (!isset($total_items)) { $total_items = 0; }
if (!isset($broken_on_page)) { $broken_on_page = 0; }

if (empty($links) && empty($_REQUEST['s'])) {
    echo '<p class="seokelo-no-links">' . esc_html__('No external links found. Please run "Collect All External Links" first.', 'external-links-overview') . '</p>';
} elseif (empty($links) && !empty($_REQUEST['s'])) {
    echo '<p class="seokelo-no-links">' . esc_html__('No external links found for your current search criteria.', 'external-links-overview') . '</p>';
} else {
?>

<div class="seokelo-table-container">
    <table class="wp-list-table widefat fixed striped seokelo-links-table">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-id <?php echo ($order_by === 'id' ? 'sorted ' . esc_attr(strtolower($order)) : 'sortable'); ?>">
                    <a href="<?php echo esc_url($this->get_sort_link('id', $order_by, $order)); ?>">
                        <span><?php esc_html_e('ID', 'external-links-overview'); ?></span><span class="sorting-indicator"></span>
                    </a>
                </th>
                <th scope="col" class="manage-column column-quelle <?php echo ($order_by === 'quelle_titel' ? 'sorted ' . esc_attr(strtolower($order)) : 'sortable'); ?>">
                     <a href="<?php echo esc_url($this->get_sort_link('quelle_titel', $order_by, $order)); ?>">
                        <span><?php esc_html_e('Source', 'external-links-overview'); ?></span><span class="sorting-indicator"></span>
                    </a>
                </th>
                <th scope="col" class="manage-column column-ankertext <?php echo ($order_by === 'ankertext' ? 'sorted ' . esc_attr(strtolower($order)) : 'sortable'); ?>">
                    <a href="<?php echo esc_url($this->get_sort_link('ankertext', $order_by, $order)); ?>">
                        <span><?php esc_html_e('Anchor Text', 'external-links-overview'); ?></span><span class="sorting-indicator"></span>
                    </a>
                </th>
                <th scope="col" class="manage-column column-url <?php echo ($order_by === 'link_url' ? 'sorted ' . esc_attr(strtolower($order)) : 'sortable'); ?>">
                    <a href="<?php echo esc_url($this->get_sort_link('link_url', $order_by, $order)); ?>">
                        <span><?php esc_html_e('Target URL', 'external-links-overview'); ?></span><span class="sorting-indicator"></span>
                    </a>
                </th>
                <th scope="col" class="manage-column column-link-attributes"><?php esc_html_e('Link Attributes', 'external-links-overview'); ?></th>
                <th scope="col" class="manage-column column-status <?php echo ($order_by === 'is_broken' ? 'sorted ' . esc_attr(strtolower($order)) : 'sortable'); ?>">
                    <a href="<?php echo esc_url($this->get_sort_link('is_broken', $order_by, $order)); ?>">
                        <span><?php esc_html_e('Status', 'external-links-overview'); ?></span><span class="sorting-indicator"></span>
                    </a>
                </th>
                <th scope="col" class="manage-column column-actions"><?php esc_html_e('Actions', 'external-links-overview'); ?></th>
            </tr>
        </thead>
        <tbody id="the-list">
            <?php
            foreach ($links as $link_item) :
                $link = (object) $link_item;

                $row_class = '';
                if (isset($link->is_ignored) && $link->is_ignored == 1) {
                    $row_class = 'seokelo-ignored-link';
                } elseif (isset($link->is_broken) && $link->is_broken == 1) {
                    $row_class = 'seokelo-broken-link';
                }

                $status_class = 'seokelo-status-unknown';
                $status_text = esc_html__('Not Checked', 'external-links-overview');
                $status_icon = 'dashicons-editor-help';
                $http_status_display = '';
                $valid_last_checked_timestamp = false;
                $last_checked_timestamp = 0;
                
                if (!is_null($link->last_checked) && $link->last_checked !== '0000-00-00 00:00:00') {
                     $last_checked_timestamp = strtotime($link->last_checked . ' +0000');
                     if ($last_checked_timestamp !== false && $last_checked_timestamp > 0) {
                        $valid_last_checked_timestamp = true;
                     }
                    if (isset($link->is_broken) && $link->is_broken == 1) {
                        $status_class = 'seokelo-status-broken';
                        $status_text = esc_html__('Broken', 'external-links-overview');
                        $status_icon = 'dashicons-no';
                    } else {
                        $status_class = 'seokelo-status-ok';
                        $status_text = esc_html__('OK', 'external-links-overview');
                        $status_icon = 'dashicons-yes-alt';
                    }
                    if (isset($link->http_status) && $link->http_status > 0) {
                        $http_status_display = " (" . esc_html($link->http_status) . ")";
                    }
                }

                $source_post_id = isset($link->quelle_id) ? intval($link->quelle_id) : 0;
                $source_post_title_raw = isset($link->quelle_titel) ? $link->quelle_titel : '';
                $source_post_exists = false;
                $edit_post_link = '';
                $view_post_link = '';
                if ($source_post_id > 0) {
                     $source_post = get_post($source_post_id);
                     if ($source_post && in_array($source_post->post_status, array('publish', 'private'))) {
                          $source_post_exists = true;
                          $edit_post_link = get_edit_post_link($source_post_id);
                          $view_post_link = get_permalink($source_post_id);
                          if (empty($source_post_title_raw)) {
                               $source_post_title_raw = $source_post->post_title;
                          }
                     }
                }
                $display_source_title = !empty($source_post_title_raw) ? $source_post_title_raw : esc_html__('Untitled', 'external-links-overview');

                $anchor_text_display = !empty($link->ankertext) ? wp_strip_all_tags($link->ankertext) : '<em>' . esc_html__('[No Anchor Text]', 'external-links-overview') . '</em>';
                $id_display = isset($link->id) ? esc_html($link->id) : '–';

            ?>
                <tr class="<?php echo esc_attr(trim($row_class)); ?>" data-link-id="<?php echo esc_attr($link->id); ?>">
                    <td class="column-id" data-colname="<?php esc_attr_e('ID', 'external-links-overview'); ?>">
                        <?php echo $id_display; ?>
                    </td>
                    <td class="column-quelle" data-colname="<?php esc_attr_e('Source', 'external-links-overview'); ?>">
                        <?php if ($source_post_exists && $edit_post_link) : ?>
                            <a href="<?php echo esc_url($edit_post_link); ?>" target="_blank" title="<?php esc_attr_e('Edit Source Post/Page', 'external-links-overview'); ?>">
                                <?php echo esc_html($display_source_title); ?>
                            </a>
                            (ID: <?php echo esc_html($source_post_id); ?>)
                        <?php else: ?>
                            <?php echo esc_html($display_source_title); ?>
                            (ID: <?php echo esc_html($source_post_id); ?>)
                             <?php if (!$source_post_exists && $source_post_id > 0) echo ' <small><em>(' . esc_html__('Deleted or Draft', 'external-links-overview') . ')</em></small>'; ?>
                        <?php endif; ?>
                    </td>
                    <td class="column-ankertext" data-colname="<?php esc_attr_e('Anchor Text', 'external-links-overview'); ?>">
                        <?php echo $anchor_text_display; ?>
                    </td>
                    <td class="column-url" data-colname="<?php esc_attr_e('Target URL', 'external-links-overview'); ?>">
                        <?php if (!empty($link->link_url)): ?>
                        <a href="<?php echo esc_url($link->link_url); ?>" target="_blank" rel="external noopener noreferrer" title="<?php echo esc_attr($link->link_url); ?>">
                            <?php
                            $link_url_display = $link->link_url;
                            $max_len = 60;
                            if (mb_strlen($link_url_display) > $max_len) {
                                $link_url_display = mb_substr($link_url_display, 0, 35) . '...' . mb_substr($link_url_display, -20);
                            }
                            echo esc_html($link_url_display);
                            ?>
                           <span class="dashicons dashicons-external"></span>
                        </a>
                        <?php else: ?>
                            –
                        <?php endif; ?>
                    </td>
                    <td class="column-link-attributes" data-colname="<?php esc_attr_e('Link Attributes', 'external-links-overview'); ?>">
                        <?php 
                            $rel_display = !empty($link->link_rel) ? esc_html($link->link_rel) : '–';
                            $target_display = !empty($link->link_target) ? esc_html($link->link_target) : '–';
                            echo 'Rel: ' . $rel_display . '<br>';
                            echo 'Target: ' . $target_display;
                        ?>
                    </td>
                    <td class="column-status seokelo-status-cell" data-colname="<?php esc_attr_e('Status', 'external-links-overview'); ?>">
                        <?php
                        if (isset($link->is_ignored) && $link->is_ignored == 1) :
                            $original_status_text = (isset($link->is_broken) && $link->is_broken == 1)
                                ? esc_html__('Broken', 'external-links-overview') . ' (' . esc_html($link->http_status) . ')'
                                : esc_html__('OK', 'external-links-overview');
                        ?>
                            <span class="seokelo-status-ignored" title="<?php esc_attr_e('This link is manually ignored.', 'external-links-overview'); ?>">
                                <span class="dashicons dashicons-hidden"></span> <?php esc_html_e('Ignored', 'external-links-overview'); ?>
                            </span>
                            <div class="seokelo-original-status">(<?php echo esc_html($original_status_text); ?>)</div>
                        <?php else : ?>
                            <span class="<?php echo esc_attr($status_class); ?>" title="<?php echo esc_attr($status_text . $http_status_display); ?>">
                                <span class="dashicons <?php echo esc_attr($status_icon); ?>"></span>
                                <?php echo $status_text; ?>
                                <?php echo esc_html($http_status_display); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($valid_last_checked_timestamp) : ?>
                            <div class="seokelo-check-time" title="<?php esc_attr_e('Last Checked', 'external-links-overview'); ?>">
                                <?php echo esc_html(sprintf(esc_html__('%s ago', 'external-links-overview'), human_time_diff($last_checked_timestamp, current_time('timestamp', true)))); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="column-actions" data-colname="<?php esc_attr_e('Actions', 'external-links-overview'); ?>">
                        <div class="seokelo-actions-wrapper seokelo-view-mode">
                            <?php if (isset($link->is_ignored) && $link->is_ignored == 1) : ?>
                                <button type="button" class="button button-small seokelo-unignore-link" data-link-id="<?php echo esc_attr($link->id); ?>"><?php esc_html_e('Unignore', 'external-links-overview'); ?></button>
                            <?php elseif (isset($link->is_broken) && $link->is_broken == 1) : ?>
                                <button type="button" class="button button-small seokelo-reset-link-status" data-link-id="<?php echo esc_attr($link->id); ?>"><?php esc_html_e('Mark as OK', 'external-links-overview'); ?></button>
                                <button type="button" class="button button-secondary button-small seokelo-ignore-link" data-link-id="<?php echo esc_attr($link->id); ?>"><?php esc_html_e('Ignore', 'external-links-overview'); ?></button>
                            <?php endif; ?>
                            <?php if ($source_post_exists && $edit_post_link) : ?>
                                <a href="<?php echo esc_url($edit_post_link); ?>" class="button button-small" target="_blank" title="<?php esc_attr_e('Edit the source post/page', 'external-links-overview'); ?>">
                                    <span class="dashicons dashicons-edit-page"></span>
                                    <?php esc_html_e('Edit Source', 'external-links-overview'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-id"><?php esc_html_e('ID', 'external-links-overview'); ?></th>
                <th scope="col" class="manage-column column-quelle"><?php esc_html_e('Source', 'external-links-overview'); ?></th>
                <th scope="col" class="manage-column column-ankertext"><?php esc_html_e('Anchor Text', 'external-links-overview'); ?></th>
                <th scope="col" class="manage-column column-url"><?php esc_html_e('Target URL', 'external-links-overview'); ?></th>
                <th scope="col" class="manage-column column-link-attributes"><?php esc_html_e('Link Attributes', 'external-links-overview'); ?></th>
                <th scope="col" class="manage-column column-status"><?php esc_html_e('Status', 'external-links-overview'); ?></th>
                <th scope="col" class="manage-column column-actions"><?php esc_html_e('Actions', 'external-links-overview'); ?></th>
            </tr>
        </tfoot>
    </table>
</div>
<?php
}
?>