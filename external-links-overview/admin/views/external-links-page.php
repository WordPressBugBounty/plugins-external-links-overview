<?php
/**
 * Admin view for External Links Overview - Main Page (Free Version)
 * Assumes necessary variables ($search_term, $order_by, $order, $filter_broken,
 * $collection_status_text, $check_status_text, $pending_updates_html, $pending_updates)
 * are passed from the controller (SEOKELO_Admin::display_external_links_page) and are pre-sanitized/pre-escaped where appropriate.
 * $this refers to the SEOKELO_Admin instance.
 *
 * @since      1.0
 * @package    External_Links_Overview
 */

if (!defined('ABSPATH')) {
    exit;
}

// Sanitize variables passed from the controller
$search_term   = isset($search_term) ? $search_term : '';
$order_by      = isset($order_by) ? $order_by : 'id';
$order         = isset($order) ? $order : 'ASC';
$filter_broken = isset($filter_broken) ? $filter_broken : false;
$collection_status_text = isset($collection_status_text) ? $collection_status_text : '';
$check_status_text = isset($check_status_text) ? $check_status_text : '';
$pending_updates_html = isset($pending_updates_html) ? $pending_updates_html : '';
$pending_updates = isset($pending_updates) ? intval($pending_updates) : 0;
$plugin_slug = isset($this->plugin_slug) ? $this->plugin_slug : 'external-links-overview';

$status_check_done = isset($status_check_done) ? $status_check_done : false;

?>

<div class="wrap seokelo-wrap">
    <h1><?php esc_html_e('External Links Overview', 'external-links-overview'); ?></h1>

    <?php if (!empty($collection_status_text) || !empty($check_status_text) || !empty($pending_updates_html)) : ?>
    <div class="seokelo-status-bar notice notice-info inline">
        <?php if (!empty($collection_status_text)) : ?>
            <span class="seokelo-status-item"><span class="dashicons dashicons-database-view"></span> <?php echo esc_html( $collection_status_text ); ?></span>
        <?php endif; ?>

        <?php if (!empty($check_status_text)) : ?>
            <span class="seokelo-status-item"><span class="dashicons dashicons-admin-links"></span> <?php echo esc_html( $check_status_text ); ?></span>
        <?php endif; ?>

        <?php if (!empty($pending_updates_html)) : ?>
            <span class="seokelo-status-item"><span class="dashicons dashicons-edit"></span> <?php echo wp_kses_post( $pending_updates_html ); ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="seokelo-action-buttons seokelo-controls-container">
        <form method="post" style="display: inline;">
            <?php wp_nonce_field('seokelo_actions_nonce', 'seokelo_nonce_alt'); ?>
            <button type="button" class="button button-primary seokelo-ajax-button" data-action="collect">
                <span class="dashicons dashicons-download"></span> <?php esc_html_e('Collect All External Links', 'external-links-overview'); ?>
            </button>

            <button type="button" class="button seokelo-ajax-button" data-action="update" <?php disabled($pending_updates === 0); ?>>
                <span class="dashicons dashicons-update-alt"></span> <?php esc_html_e('Update Links from Changed Posts', 'external-links-overview'); ?>
                <?php if ($pending_updates > 0) : ?>
                    (<?php echo esc_html(number_format_i18n($pending_updates)); ?>)
                <?php endif; ?>
            </button>

            <button type="button" class="button seokelo-ajax-button" data-action="check" data-confirm="<?php esc_attr_e('This will re-check all links, including those you have manually ignored. Are you sure you want to continue?', 'external-links-overview'); ?>">
                 <span class="dashicons dashicons-admin-links"></span> <?php esc_html_e('Check External Links Status', 'external-links-overview'); ?>
            </button>
        </form>

        <form method="post" id="seokelo-delete-form" style="display:inline;">
            <?php wp_nonce_field('seokelo_actions_nonce', 'seokelo_nonce'); ?>
            <button type="submit" name="seokelo_delete_external_data_button" class="button seokelo-delete-button" data-confirm="<?php esc_attr_e('Are you sure you want to delete ALL external link data? This cannot be undone!', 'external-links-overview'); ?>">
                <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Delete All External Link Data', 'external-links-overview'); ?>
            </button>
        </form>

        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=' . $plugin_slug . '&export=external_csv'), 'seokelo_export_nonce', '_wpnonce')); ?>" class="button">
             <span class="dashicons dashicons-database-export"></span> <?php esc_html_e('Export to CSV', 'external-links-overview'); ?>
        </a>
    </div>

    <div id="seokelo-progress-container" class="seokelo-progress-box" style="display: none;">
        <h3 id="seokelo-progress-title"><?php esc_html_e('Processing...', 'external-links-overview'); ?></h3>
        <div class="seokelo-progress-stats">
            <div id="seokelo-progress-stats-text"><?php esc_html_e('Starting...', 'external-links-overview'); ?></div>
            <div id="seokelo-progress-estimate" class="seokelo-estimate"></div>
        </div>
        <div class="seokelo-progress-bar-wrapper">
            <div id="seokelo-progress-bar" class="seokelo-progress-bar" data-progress="0" style="width: 0%;"></div>
        </div>
         <div class="seokelo-progress-controls">
            <button type="button" id="seokelo-cancel-process" class="button button-secondary">
                <span class="dashicons dashicons-no-alt"></span> <?php esc_html_e('Cancel', 'external-links-overview'); ?>
            </button>
        </div>
    </div>

    <div id="seokelo-ajax-notification" class="notice seokelo-ajax-notice" style="display: none;">
        <p></p>
    </div>

    <h2 class="nav-tab-wrapper seokelo-tabs">
        <a href="#seokelo-tab-links" class="nav-tab nav-tab-active" data-tab="links"><?php esc_html_e('Link Table', 'external-links-overview'); ?></a>
        <a href="#seokelo-tab-domain-distribution" class="nav-tab" data-tab="domain-distribution"><?php esc_html_e('Domain Distribution', 'external-links-overview'); ?></a>
    </h2>

    <div id="seokelo-tab-links" class="seokelo-tab-content">
        <div class="tablenav top">
            <form method="get" class="seokelo-search-form">
                <input type="hidden" name="page" value="<?php echo esc_attr($plugin_slug); ?>">
                <?php if (!empty($order_by)): ?>
                    <input type="hidden" name="orderby" value="<?php echo esc_attr($order_by); ?>">
                <?php endif; ?>
                <?php if (!empty($order)): ?>
                    <input type="hidden" name="order" value="<?php echo esc_attr(strtolower($order)); ?>">
                <?php endif; ?>

                <p class="search-box">
                    <label class="screen-reader-text" for="seokelo-search-input"><?php esc_html_e('Search Links:', 'external-links-overview'); ?></label>
                    <input type="search" id="seokelo-search-input" name="s" value="<?php echo esc_attr($search_term); ?>" placeholder="<?php esc_attr_e('Search by URL, anchor text, etc...', 'external-links-overview'); ?>">
                    <input type="submit" class="button" value="<?php esc_attr_e('Search', 'external-links-overview'); ?>">
                    <?php if (!empty($search_term)): ?>
                        <a href="<?php echo esc_url(remove_query_arg('s')); ?>" class="button"><?php esc_html_e('Clear Search', 'external-links-overview'); ?></a>
                    <?php endif; ?>
                </p>

                <div class="alignleft actions">
                    <?php if ($status_check_done): ?>
                    <label>
                        <input type="checkbox" name="filter_broken" value="1" <?php checked($filter_broken, true); ?>>
                        <?php esc_html_e('Show only broken links', 'external-links-overview'); ?>
                    </label>
                    <?php endif; ?>
                    <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'external-links-overview'); ?>">
                </div>
            </form>

            <?php $this->display_items_per_page_selector('main'); ?>
            <div class="clear"></div>
        </div>

        <?php
        $this->display_external_links_table($search_term, $order_by, $order, $filter_broken);
        ?>

    </div>

    <div id="seokelo-tab-domain-distribution" class="seokelo-tab-content" style="display: none;">
        <div class="seokelo-domain-controls">
            <div class="seokelo-domain-view-selector">
                <span>Show:</span>
                <button class="button active" data-count="10">Top 10</button>
                <button class="button" data-count="25">Top 25</button>
                <button class="button" data-count="50">Top 50</button>
                <button class="button" data-count="100">Top 100</button>
                <button class="button" data-count="all">All</button>
            </div>
        </div>
        <div id="seokelo-domain-barchart-container">
            </div>
    </div>

</div>