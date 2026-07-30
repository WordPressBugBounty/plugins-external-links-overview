<?php
/**
 * Admin view for Domain Metrics
 * Corrected escaping. Prefixed CSS classes.
 * Assumes $metrics, $total_items, $max_links are passed from controller.
 * $this refers to the SEOKELO_Admin instance.
 *
 * @since      1.0
 * @package    External_Links_Overview
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure variables exist
if (!isset($metrics)) { $metrics = array(); }
if (!isset($total_items)) { $total_items = 0; } // Already sanitized/casted int in controller
if (!isset($max_links)) { $max_links = 1; } // Already sanitized/casted int in controller


// If no domains found, show a message
if (empty($metrics)) : ?>
    <p class="seokelo-no-domains"><?php esc_html_e('No domain metrics found. Please run "Collect All External Links" first.', 'external-links-overview'); // Prefixed class ?></p>
<?php else : ?>

<div class="seokelo-table-container">
    <table class="wp-list-table widefat fixed striped seokelo-domain-table"> <?php // Prefixed class ?>
        <thead>
            <tr>
                <th scope="col" class="column-domain"><?php esc_html_e('Domain', 'external-links-overview'); ?></th>
                <th scope="col" class="column-links"><?php esc_html_e('Links', 'external-links-overview'); ?></th>
                <th scope="col" class="column-sources"><?php esc_html_e('Source Posts', 'external-links-overview'); ?></th>
                <th scope="col" class="column-chart"><?php esc_html_e('Distribution', 'external-links-overview'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($metrics as $domain) : ?>
                <?php
                // Ensure object properties exist before using them
                $domain_name = isset($domain->ziel_domain) ? $domain->ziel_domain : '';
                $link_count = isset($domain->link_count) ? intval($domain->link_count) : 0;
                $source_count = isset($domain->source_count) ? intval($domain->source_count) : 0;

                // Calculate the width for this domain's bar (max_links is ensured >= 1)
                $percentage = ($max_links > 0 && $link_count > 0) ? round(($link_count / $max_links) * 100, 1) : 0;
                $bar_width = min($percentage, 100); // Cap at 100%

                // Determine bar class based on percentage
                $bar_class = 'seokelo-bar-low'; // Prefixed class, default low
                if ($percentage > 75) {
                     $bar_class = 'seokelo-bar-high'; // Prefixed class
                } elseif ($percentage > 40) {
                     $bar_class = 'seokelo-bar-medium'; // Prefixed class
                }

                // Prepare URL for search link (use current plugin page slug)
                 $search_url_base = admin_url('admin.php?page=' . $this->plugin_slug); // $this refers to SEOKELO_Admin instance
                 $search_url = add_query_arg('s', urlencode($domain_name), $search_url_base); // urlencode the search term

                ?>
                <tr>
                    <td class="column-domain" data-colname="<?php esc_attr_e('Domain', 'external-links-overview'); ?>">
                        <?php if (!empty($domain_name)): ?>
                        <a href="<?php echo esc_url('https://' . $domain_name); ?>" target="_blank" rel="noopener external noreferrer">
                            <?php echo esc_html($domain_name); ?>
                        </a>
                        <div class="row-actions">
                            <span class="view">
                                <a href="<?php echo esc_url($search_url); ?>">
                                    <?php esc_html_e('View All Links', 'external-links-overview'); ?>
                                </a>
                            </span>
                        </div>
                        <?php else: ?>
                            <?php esc_html_e('Unknown Domain', 'external-links-overview'); ?>
                        <?php endif; ?>
                    </td>
                    <td class="column-links" data-colname="<?php esc_attr_e('Links', 'external-links-overview'); ?>">
                        <?php echo esc_html(number_format_i18n($link_count)); ?>
                    </td>
                    <td class="column-sources" data-colname="<?php esc_attr_e('Source Posts', 'external-links-overview'); ?>">
                        <?php echo esc_html(number_format_i18n($source_count)); ?>
                    </td>
                    <td class="column-chart" data-colname="<?php esc_attr_e('Distribution', 'external-links-overview'); ?>">
                        <div class="seokelo-bar-chart" title="<?php printf(esc_attr__('%s Links', 'external-links-overview'), number_format_i18n($link_count)); ?>"> <?php // Prefixed class ?>
                            <div class="seokelo-bar <?php echo esc_attr($bar_class); ?>" style="width: <?php echo esc_attr($bar_width); ?>%;"> <?php // Prefixed class ?>
                                <?php // Only show value if bar is wide enough ?>
                                <?php if ($bar_width > 5): ?>
                                <span class="seokelo-bar-value"><?php echo esc_html(number_format_i18n($percentage, 1)); ?>%</span> <?php // Prefixed class ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
         <tfoot>
            <tr>
                <th scope="col" class="column-domain"><?php esc_html_e('Domain', 'external-links-overview'); ?></th>
                <th scope="col" class="column-links"><?php esc_html_e('Links', 'external-links-overview'); ?></th>
                <th scope="col" class="column-sources"><?php esc_html_e('Source Posts', 'external-links-overview'); ?></th>
                <th scope="col" class="column-chart"><?php esc_html_e('Distribution', 'external-links-overview'); ?></th>
            </tr>
        </tfoot>
    </table>
</div>

<?php endif; // End of if empty($metrics) check ?>