/**
 * Admin JavaScript for External Links Overview (Free Version)
 * Prefixed localization object and CSS selectors.
 * Console logs removed.
 */
(function($) {
    'use strict';

    let isProcessing = false;
    let currentProcess = null;
    let processStartTime = 0;
    let batchIndex = 0;
    let processTimer = null;
    let fullDomainMetrics = null; // Cache for domain data

    $(function() {
        initializeTabs();
        initializeAjaxButtons();
        initializeDeleteConfirmation();
        initializeManualStatusReset();
        initializeCancelButton();
        initializeIgnoreButtons();
    });

    function initializeTabs() {
        // Handle tab clicks
        $('.seokelo-tabs .nav-tab').on('click', function(e) {
            e.preventDefault();
            const tabTarget = $(this).attr('href');

            $('.seokelo-tabs .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.seokelo-tab-content').hide();
            $(tabTarget).show();

            // If switching to domain tab, ensure chart is initialized
            if (tabTarget === '#seokelo-tab-domain-distribution') {
                const chartContainer = $('#seokelo-domain-barchart-container');
                if (chartContainer.length && !chartContainer.data('chart-initialized')) {
                    initializeDomainChart();
                }
            }
        });

        // Activate tab based on URL hash on page load
        if (window.location.hash && $('.seokelo-tabs .nav-tab[href="' + window.location.hash + '"]').length) {
            $('.seokelo-tabs .nav-tab[href="' + window.location.hash + '"]').trigger('click');
        } else {
            // Default view if no hash
            $('#seokelo-tab-links').show();
            $('#seokelo-tab-domain-distribution').hide();
        }
    }

    function initializeAjaxButtons() {
        $('.seokelo-action-buttons').on('click', '.seokelo-ajax-button', function() {
            if (isProcessing) {
                showAjaxNotification(
                    seokelo_ajax.already_running_text.replace('%s', currentProcess),
                    'warning',
                    3000
                );
                return;
            }
            const confirmationMessage = $(this).data('confirm');
            if (confirmationMessage && !confirm(confirmationMessage)) {
                return;
            }

            const actionType = $(this).data('action');
            startAjaxProcess(actionType);
        });
    }

    function initializeDeleteConfirmation() {
        $('body').on('submit', '#seokelo-delete-form', function(e) {
            const $button = $(this).find('.seokelo-delete-button');
            const confirmationMessage = $button.data('confirm') || seokelo_ajax.confirm_delete;
            if (!confirm(confirmationMessage)) {
                e.preventDefault();
            }
        });
    }

    function initializeManualStatusReset() {
        $('#seokelo-tab-links').on('click', '.seokelo-reset-link-status', function() {
            const $button = $(this);
            const $row = $button.closest('tr');
            const linkId = $button.data('link-id');

            if ($button.prop('disabled')) return;

            $button.prop('disabled', true).addClass('updating-message');
            $row.addClass('seokelo-loading');

            $.ajax({
                url: seokelo_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'seokelo_reset_external_link_status',
                    nonce: seokelo_ajax.nonce,
                    link_id: linkId
                },
                success: function(response) {
                    if (response.success) {
                        const $statusCell = $row.find('.seokelo-status-cell');
                        $statusCell.html(`
                            <span class="seokelo-status-ok" title="${seokelo_ajax.status_ok_text} (200)">
                                <span class="dashicons dashicons-yes-alt"></span> ${seokelo_ajax.status_ok_text} (200)
                            </span>
                            <div class="seokelo-check-time" title="${seokelo_ajax.last_checked_text}">
                                ${seokelo_ajax.just_now_text}
                            </div>
                        `);
                        $row.removeClass('seokelo-broken-link').addClass('seokelo-ok-link');
                        $button.closest('td').html('');

                        showAjaxNotification(response.data.message || seokelo_ajax.status_reset_success, 'success', 5000);
                    } else {
                        showAjaxNotification(response.data.message || seokelo_ajax.status_reset_error, 'error');
                        $button.prop('disabled', false).removeClass('updating-message');
                    }
                },
                error: function() {
                    showAjaxNotification(seokelo_ajax.network_error, 'error');
                    $button.prop('disabled', false).removeClass('updating-message');
                },
                complete: function() {
                    $row.removeClass('seokelo-loading');
                }
            });
        });
    }

    function initializeIgnoreButtons() {
        $('#seokelo-tab-links').on('click', '.seokelo-ignore-link, .seokelo-unignore-link', function() {
            const $button = $(this);
            const $row = $button.closest('tr');
            const linkId = $button.data('link-id');
            const newStatus = $button.hasClass('seokelo-ignore-link') ? 1 : 0; // 1 for ignore, 0 for unignore

            if ($button.prop('disabled')) return;

            $button.prop('disabled', true).addClass('updating-message');
            $row.addClass('seokelo-loading');

            $.ajax({
                url: seokelo_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: seokelo_ajax.action_ignore,
                    nonce: seokelo_ajax.nonce,
                    link_id: linkId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        showAjaxNotification(response.data.message, 'success', 5000);
                        
                        // Update UI based on new status
                        const $statusCell = $row.find('.seokelo-status-cell');
                        const $actionsCell = $button.closest('td');

                        if (newStatus === 1) { // Ignored
                            $row.removeClass('seokelo-broken-link').addClass('seokelo-ignored-link');
                            const originalStatus = $statusCell.find('.seokelo-status-broken').text().trim();
                            $statusCell.html(`
                                <span class="seokelo-status-ignored" title="${seokelo_ajax.status_ignored_text}">
                                    <span class="dashicons dashicons-hidden"></span> ${seokelo_ajax.status_ignored_text}
                                </span>
                                <div class="seokelo-original-status">(${originalStatus})</div>
                            `);
                            $actionsCell.html(`<button type="button" class="button button-small seokelo-unignore-link" data-link-id="${linkId}">${seokelo_ajax.unignore_text}</button>`);
                        } else { // Unignored
                            // This requires a page reload to get the correct original status and actions back
                            // It's simpler and more reliable than trying to reconstruct the state with JS
                            $row.fadeOut(function() {
                                showAjaxNotification(response.data.message + ' ' + 'Reloading table...', 'success', 2000);
                                setTimeout(() => window.location.reload(), 1000);
                            });
                        }
                    } else {
                        showAjaxNotification(response.data.message || seokelo_ajax.error_text, 'error');
                        $button.prop('disabled', false).removeClass('updating-message');
                    }
                },
                error: function() {
                    showAjaxNotification(seokelo_ajax.network_error, 'error');
                    $button.prop('disabled', false).removeClass('updating-message');
                },
                complete: function() {
                    $row.removeClass('seokelo-loading');
                }
            });
        });
    }

    function initializeCancelButton() {
        $('#seokelo-cancel-process').on('click', function() {
            if (isProcessing && confirm(seokelo_ajax.confirm_cancel)) {
                isProcessing = false;
                const cancelledProcess = currentProcess;
                currentProcess = null;
                clearTimeout(processTimer);

                $('#seokelo-progress-container').slideUp();
                showAjaxNotification(seokelo_ajax.processing_cancelled_text.replace('%s', cancelledProcess), 'warning');

                $('.seokelo-ajax-button').prop('disabled', false);
                $('#seokelo-delete-form button').prop('disabled', false);
            }
        });
    }

    function renderDomainChart(metrics, totalLinks, displayCount = 10) {
        const container = $('#seokelo-domain-barchart-container');
        const dataToRender = (displayCount === 'all') ? metrics : metrics.slice(0, displayCount);

        if (dataToRender.length === 0) {
            container.html('<p>No domain data found to visualize.</p>');
            return;
        }

        const maxLinks = Math.max(...dataToRender.map(item => parseInt(item.link_count)));
        
        let kpiText = `Showing Top ${dataToRender.length} of ${metrics.length} unique domains.`;
        if (displayCount === 'all') {
            kpiText = `Showing all ${metrics.length} unique domains.`;
        }

        const kpis = `
            <div class="seokelo-domain-kpis">
                <span><strong>Total Links:</strong> ${totalLinks.toLocaleString()}</span>
                <span><strong>Unique Domains:</strong> ${metrics.length.toLocaleString()}</span>
                <span class="seokelo-kpi-info">${kpiText}</span>
            </div>
        `;
        
        let chartHtml = '<div class="seokelo-barchart-wrapper">';
        dataToRender.forEach(item => {
            const linkCount = parseInt(item.link_count);
            const percentage = totalLinks > 0 ? ((linkCount / totalLinks) * 100).toFixed(1) : 0;
            const barWidth = maxLinks > 0 ? (linkCount / maxLinks) * 100 : 0;
            const searchUrl = `admin.php?page=${seokelo_ajax.plugin_slug}&s=domain:${encodeURIComponent(item.ziel_domain)}`;

            chartHtml += `
                <a href="${searchUrl}" class="seokelo-bar-item" title="Click to view all links for ${item.ziel_domain}">
                    <div class="seokelo-bar-label">${item.ziel_domain}</div>
                    <div class="seokelo-bar-bg">
                        <div class="seokelo-bar-fg" style="width: ${barWidth}%;"></div>
                    </div>
                    <div class="seokelo-bar-value">
                        <span class="count">${linkCount.toLocaleString()}</span>
                        <span class="percent">(${percentage}%)</span>
                    </div>
                </a>
            `;
        });
        chartHtml += '</div>';

        container.html(kpis + chartHtml);
    }
    
    function fetchAndRenderDomainChart() {
        const container = $('#seokelo-domain-barchart-container');
        const displayCount = $('.seokelo-domain-view-selector button.active').data('count') || 10;
        
        container.html('<p>Loading domain data...</p>');

        $.ajax({
            url: seokelo_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'seokelo_get_domain_metrics',
                nonce: seokelo_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.metrics.length > 0) {
                    fullDomainMetrics = response.data.metrics;
                    const totalLinks = fullDomainMetrics.reduce((sum, item) => sum + parseInt(item.link_count), 0);
                    renderDomainChart(fullDomainMetrics, totalLinks, displayCount);
                } else {
                    container.html('<p>No domain data found to visualize.</p>');
                }
            },
            error: function() {
                container.html('<p>Error loading domain data.</p>');
            }
        });
    }

    function initializeDomainChart() {
        const container = $('#seokelo-domain-barchart-container');
        if (!container.length || container.data('chart-initialized')) return;
        container.data('chart-initialized', true);

        // Event listener for view selector buttons
        $('.seokelo-domain-view-selector').on('click', 'button', function() {
            if (!fullDomainMetrics) return;
            const $button = $(this);
            $('.seokelo-domain-view-selector button').removeClass('active');
            $button.addClass('active');
            const count = $button.data('count');
            const totalLinks = fullDomainMetrics.reduce((sum, item) => sum + parseInt(item.link_count), 0);
            renderDomainChart(fullDomainMetrics, totalLinks, count);
        });

        // Initial data fetch and render
        fetchAndRenderDomainChart();
    }

    function startAjaxProcess(actionType) {
        if (isProcessing) return;

        isProcessing = true;
        currentProcess = actionType;
        batchIndex = 0;
        processStartTime = new Date().getTime();

        $('.seokelo-ajax-button').prop('disabled', true);
        $('#seokelo-delete-form button').prop('disabled', true);

        resetProgressBar(actionType);
        $('#seokelo-progress-container').slideDown();
        $('#seokelo-ajax-notification').fadeOut();

        runNextBatch();
    }

    function runNextBatch() {
        if (!isProcessing) {
            $('.seokelo-ajax-button').prop('disabled', false);
            $('#seokelo-delete-form button').prop('disabled', false);
            return;
        }

        let ajaxAction = '';
        const ajaxData = {
            nonce: seokelo_ajax.nonce,
            batch_index: batchIndex
        };

        if (currentProcess === 'collect' || currentProcess === 'update') {
            ajaxAction = 'seokelo_process_external_links';
            ajaxData.action_type = currentProcess;
        } else if (currentProcess === 'check') {
            ajaxAction = 'seokelo_check_links_status';
        } else {
            finishAjaxProcess('Internal error: Invalid process type.', 'error');
            return;
        }
        ajaxData.action = ajaxAction;

        $.ajax({
            url: seokelo_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: ajaxData,
            success: function(response) {
                if (!isProcessing) return;

                if (response.success) {
                    updateProgressBar(response.data);

                    if (response.data.continue) {
                        batchIndex = response.data.batch_index;
                        setTimeout(runNextBatch, 150);
                    } else {
                        finishAjaxProcess(response.data.message || seokelo_ajax.complete_text, 'success');
                    }
                } else {
                    finishAjaxProcess(response.data.message || seokelo_ajax.error_text, 'error');
                }
            },
            error: function(jqXHR, textStatus) {
                if (!isProcessing) return;
                let errorMsg = seokelo_ajax.network_error;
                if (textStatus === 'timeout') {
                    errorMsg = 'The request timed out.';
                } else if (jqXHR.status === 500) {
                    errorMsg = 'Server error (500).';
                } else if (jqXHR.status === 403) {
                    errorMsg = 'Permission denied (403).';
                }
                finishAjaxProcess(errorMsg, 'error');
            }
        });
    }

    function finishAjaxProcess(message, type) {
        isProcessing = false;
        currentProcess = null;
        clearTimeout(processTimer);
    
        if (type === 'success') {
            $('#seokelo-progress-bar').css('width', '100%').attr('data-progress', 100);
            $('#seokelo-progress-stats-text').text(seokelo_ajax.complete_text);
            $('#seokelo-progress-estimate').text('');
    
            showAjaxNotification(message, 'success');
    
            // Automatically reload the page after a 2-second delay on success
            setTimeout(function() {
                const cleanBaseUrl = 'admin.php?page=' + seokelo_ajax.plugin_slug;
                window.location.href = cleanBaseUrl;
            }, 2000);
    
        } else {
            // On error, show the message with a manual reload link
            const cleanBaseUrl = 'admin.php?page=' + seokelo_ajax.plugin_slug;
            const reloadLink = `<a href="${cleanBaseUrl}" class="seokelo-reload-link">${seokelo_ajax.page_reload_text}</a>`;
            showAjaxNotification(`${message} ${reloadLink}`, type);
    
            // Re-enable buttons immediately on error
            $('.seokelo-ajax-button').prop('disabled', false);
            $('#seokelo-delete-form button').prop('disabled', false);
        }
    }

    function resetProgressBar(actionType) {
        let title = '';
        if (actionType === 'collect') title = seokelo_ajax.collect_action_text;
        else if (actionType === 'update') title = seokelo_ajax.update_action_text;
        else if (actionType === 'check') title = seokelo_ajax.check_action_text;
        else title = seokelo_ajax.processing_text;

        $('#seokelo-progress-title').text(title);
        $('#seokelo-progress-bar').css('width', '0%').attr('data-progress', 0);
        $('#seokelo-progress-stats-text').text(seokelo_ajax.starting_text);
        $('#seokelo-progress-estimate').text('');
        clearTimeout(processTimer);
    }

    function updateProgressBar(data) {
        const progress = Math.min(100, Math.max(0, parseInt(data.progress || 0)));
        $('#seokelo-progress-bar').css('width', progress + '%').attr('data-progress', progress);

        let statsText = '';
        const current = Number(data.current_offset || 0).toLocaleString();
        const total = Number(data.total_items || 0).toLocaleString();

        if (currentProcess === 'check') {
            const broken = Number(data.broken_count || 0).toLocaleString();
            statsText = `${seokelo_ajax.links_checked_text} ${current} / ${total} (${seokelo_ajax.broken_links_text} ${broken})`;
        } else {
            const found = Number(data.link_count || 0).toLocaleString();
            statsText = `${seokelo_ajax.processed_posts_text} ${current} / ${total} (${seokelo_ajax.found_links_text} ${found})`;
        }
        $('#seokelo-progress-stats-text').text(statsText);

        if (isProcessing) {
            updateEstimatedTime(progress, data.current_offset, data.total_items);
        }
    }

    function updateEstimatedTime(progress, current, total) {
        total = Number(total);
        if (!total || total <= 0) {
            $('#seokelo-progress-estimate').text('');
            return;
        }
        if (progress > 1 && progress < 100 && processStartTime > 0) {
            const now = new Date().getTime();
            const elapsedSeconds = (now - processStartTime) / 1000;
            const estimatedTotalSeconds = (elapsedSeconds / progress) * 100;
            const remainingSeconds = Math.max(0, estimatedTotalSeconds - elapsedSeconds);

            $('#seokelo-progress-estimate').text(seokelo_ajax.estimate_text + ' ' + formatTime(remainingSeconds));

            clearTimeout(processTimer);
            if (isProcessing) {
                processTimer = setTimeout(function() {
                    if (isProcessing) {
                        const newNow = new Date().getTime();
                        const newElapsed = (newNow - processStartTime) / 1000;
                        const stillRemaining = Math.max(0, estimatedTotalSeconds - newElapsed);
                        $('#seokelo-progress-estimate').text(seokelo_ajax.estimate_text + ' ' + formatTime(stillRemaining));
                    }
                }, 5000);
            }
        } else {
            $('#seokelo-progress-estimate').text('');
        }
    }

    function formatTime(seconds) {
        seconds = Math.round(seconds);
        if (isNaN(seconds) || seconds < 0) return seokelo_ajax.calculating_text;

        if (seconds < 60) {
            return seconds + ' ' + seokelo_ajax.seconds_text;
        } else if (seconds < 3600) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return mins + ' ' + seokelo_ajax.minutes_text + (secs > 0 ? ' ' + secs + 's' : '');
        } else {
            const hours = Math.floor(seconds / 3600);
            const mins = Math.floor((seconds % 3600) / 60);
            return hours + ' ' + seokelo_ajax.hours_text + (mins > 0 ? ' ' + mins + 'm' : '');
        }
    }

    function showAjaxNotification(message, type, autoHideDelay = 0) {
        const $notification = $('#seokelo-ajax-notification');
        $notification.hide();
        $notification
            .removeClass('notice-success notice-error notice-warning notice-info is-dismissible')
            .addClass('notice notice-' + type + ' is-dismissible')
            .find('p').html(message);

        if (!$notification.find('.notice-dismiss').length) {
            $notification.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
        }

        $notification.fadeIn();

        $notification.off('click.seokelo-dismiss').on('click.seokelo-dismiss', '.notice-dismiss', function(event) {
            event.preventDefault();
            $(this).closest('.notice').fadeOut();
        });

        if (autoHideDelay > 0) {
            setTimeout(function() {
                if ($notification.is(':visible')) {
                    $notification.fadeOut();
                }
            }, autoHideDelay > 0);
        }
    }

}(jQuery));