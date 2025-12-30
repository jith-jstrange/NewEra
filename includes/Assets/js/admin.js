/**
 * Newera Plugin Admin JavaScript
 */

// Import CSS for webpack bundling
import '../css/admin.css';

(function($) {
    'use strict';

    // Global object to store plugin state
    var NeweraAdmin = {
        isLoading: false,
        currentPage: 1,
        entriesPerPage: 50,
        filters: {
            level: 'all',
            date: 'all',
            search: ''
        }
    };

    /**
     * Initialize admin functionality
     */
    $(document).ready(function() {
        NeweraAdmin.init();
    });

    /**
     * Initialize all admin functionality
     */
    NeweraAdmin.init = function() {
        this.bindEvents();
        this.initTooltips();
        this.initConfirmDialogs();
        this.initAutoRefresh();
    };

    /**
     * Bind event handlers
     */
    NeweraAdmin.bindEvents = function() {
        var self = this;

        // Module toggle events
        $(document).on('click', '.module-toggle', function(e) {
            e.preventDefault();
            var moduleId = $(this).data('module-id');
            var enable = $(this).data('enable') === 'true';
            self.toggleModule(moduleId, enable, $(this));
        });

        // Settings form handling
        $(document).on('submit', '.newera-settings-form', function(e) {
            e.preventDefault();
            self.saveSettings($(this));
        });

        // Log filtering
        $(document).on('change', '#log-level-filter, #log-date-filter', function() {
            self.filters.level = $('#log-level-filter').val();
            self.filters.date = $('#log-date-filter').val();
            self.loadLogs(true);
        });

        $(document).on('input', '#log-search', function() {
            var searchTerm = $(this).val();
            clearTimeout(self.searchTimeout);
            self.searchTimeout = setTimeout(function() {
                self.filters.search = searchTerm;
                self.loadLogs(true);
            }, 300);
        });

        // Refresh logs
        $(document).on('click', '#refresh-logs', function() {
            self.loadLogs(true);
        });

        // Clear logs
        $(document).on('click', '#clear-logs', function(e) {
            e.preventDefault();
            if (confirm(newera_ajax.strings.confirm_action || 'Are you sure?')) {
                self.clearLogs();
            }
        });

        // Download logs
        $(document).on('click', '#download-logs', function(e) {
            e.preventDefault();
            self.downloadLogs();
        });

        // Load more logs
        $(document).on('click', '#load-more-logs', function() {
            self.currentPage++;
            self.loadLogs();
        });

        // Log entry expansion
        $(document).on('click', '.newera-log-entry', function() {
            $(this).toggleClass('expanded');
        });

        // Bulk actions
        $(document).on('click', '.bulk-action-apply', function(e) {
            e.preventDefault();
            var action = $(this).data('action');
            var selectedModules = [];
            
            $('input[name="module[]"]:checked').each(function() {
                selectedModules.push($(this).val());
            });

            if (selectedModules.length === 0) {
                self.showNotice('Please select at least one module.', 'error');
                return;
            }

            if (!confirm('Are you sure you want to perform this action on ' + selectedModules.length + ' modules?')) {
                return;
            }

            self.bulkToggleModules(selectedModules, action === 'enable');
        });

        // Capabilities toggle
        $(document).on('click', '.capabilities-toggle', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            $(target).slideToggle();
        });

        // Auto-dismiss notices
        $(document).on('click', '.notice-dismiss', function() {
            var noticeId = $(this).closest('.notice').data('notice-id');
            if (noticeId) {
                self.dismissNotice(noticeId);
            }
        });
    };

    /**
     * Initialize tooltips
     */
    NeweraAdmin.initTooltips = function() {
        // Initialize any tooltips or help bubbles
        $('[data-tooltip]').each(function() {
            var $element = $(this);
            var tooltipText = $element.data('tooltip');
            
            $element.on('mouseenter', function() {
                var tooltip = $('<div class="newera-tooltip">' + tooltipText + '</div>');
                $('body').append(tooltip);
                
                var position = $element.offset();
                tooltip.css({
                    top: position.top - tooltip.outerHeight() - 5,
                    left: position.left + ($element.outerWidth() / 2) - (tooltip.outerWidth() / 2)
                }).fadeIn(200);
            }).on('mouseleave', function() {
                $('.newera-tooltip').remove();
            });
        });
    };

    /**
     * Initialize confirmation dialogs
     */
    NeweraAdmin.initConfirmDialogs = function() {
        // Add confirmation to critical actions
        $('[data-confirm]').each(function() {
            var confirmText = $(this).data('confirm');
            $(this).on('click', function(e) {
                if (!confirm(confirmText)) {
                    e.preventDefault();
                }
            });
        });
    };

    /**
     * Initialize auto-refresh functionality
     */
    NeweraAdmin.initAutoRefresh = function() {
        // Auto-refresh dashboard stats every 30 seconds
        if ($('.newera-dashboard').length > 0) {
            setInterval(function() {
                NeweraAdmin.refreshDashboardStats();
            }, 30000);
        }
    };

    /**
     * Toggle module enable/disable
     */
    NeweraAdmin.toggleModule = function(moduleId, enable, $button) {
        var self = this;
        
        self.setLoading($button, true);
        
        $.ajax({
            url: newera_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'newera_toggle_module',
                module_id: moduleId,
                enable: enable,
                nonce: newera_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    self.showNotice(
                        enable ? 'Module enabled successfully.' : 'Module disabled successfully.',
                        'success'
                    );
                    // Refresh the page to update the UI
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    self.showNotice('Failed to toggle module: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function() {
                self.showNotice('An error occurred while toggling the module.', 'error');
            },
            complete: function() {
                self.setLoading($button, false);
            }
        });
    };

    /**
     * Save plugin settings
     */
    NeweraAdmin.saveSettings = function($form) {
        var self = this;
        var $submitButton = $form.find('input[type="submit"]');
        
        self.setLoading($submitButton, true);
        
        $.ajax({
            url: newera_ajax.ajax_url,
            type: 'POST',
            data: $form.serialize() + '&action=newera_save_settings&nonce=' + newera_ajax.nonce,
            success: function(response) {
                if (response.success) {
                    self.showNotice('Settings saved successfully.', 'success');
                } else {
                    self.showNotice('Failed to save settings: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function() {
                self.showNotice('An error occurred while saving settings.', 'error');
            },
            complete: function() {
                self.setLoading($submitButton, false);
            }
        });
    };

    /**
     * Load and filter logs
     */
    NeweraAdmin.loadLogs = function(reset) {
        var self = this;
        
        if (reset) {
            $('#log-viewer').empty();
            self.currentPage = 1;
        }
        
        self.setLoading($('#refresh-logs'), true);
        
        $.ajax({
            url: newera_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'newera_get_logs',
                page: self.currentPage,
                per_page: self.entriesPerPage,
                filters: self.filters,
                nonce: newera_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    self.appendLogEntries(response.data.entries);
                    self.updateLogStats(response.data.stats);
                    
                    if (response.data.has_more) {
                        $('#load-more-logs').show();
                    } else {
                        $('#load-more-logs').hide();
                    }
                } else {
                    self.showNotice('Failed to load logs: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function() {
                self.showNotice('An error occurred while loading logs.', 'error');
            },
            complete: function() {
                self.setLoading($('#refresh-logs'), false);
            }
        });
    };

    /**
     * Append log entries to the viewer
     */
    NeweraAdmin.appendLogEntries = function(entries) {
        var self = this;
        
        entries.forEach(function(entry) {
            var logEntry = self.createLogEntryHtml(entry);
            $('#log-viewer').append(logEntry);
        });
    };

    /**
     * Create HTML for a log entry
     */
    NeweraAdmin.createLogEntryHtml = function(entry) {
        var levelClass = 'newera-log-' + entry.level;
        var badgeClass = 'newera-badge-' + (
            entry.level === 'error' ? 'red' : 
            entry.level === 'warning' ? 'yellow' : 
            entry.level === 'debug' ? 'gray' : 'blue'
        );
        
        var html = '<div class="newera-log-entry ' + levelClass + '">';
        html += '<div class="newera-log-header">';
        html += '<span class="newera-log-time">' + entry.timestamp + '</span>';
        html += '<span class="newera-log-level"><span class="newera-badge ' + badgeClass + '">' + 
                entry.level.toUpperCase() + '</span></span>';
        html += '<span class="newera-log-caller">' + entry.caller + '</span>';
        html += '</div>';
        html += '<div class="newera-log-message">' + self.escapeHtml(entry.message) + '</div>';
        
        if (entry.context && Object.keys(entry.context).length > 0) {
            html += '<div class="newera-log-context"><small>' + 
                   self.escapeHtml(JSON.stringify(entry.context)) + '</small></div>';
        }
        html += '</div>';
        
        return html;
    };

    /**
     * Clear all logs
     */
    NeweraAdmin.clearLogs = function() {
        var self = this;
        
        self.setLoading($('#clear-logs'), true);
        
        $.ajax({
            url: newera_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'newera_clear_logs',
                nonce: newera_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    self.showNotice('Logs cleared successfully.', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    self.showNotice('Failed to clear logs: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function() {
                self.showNotice('An error occurred while clearing logs.', 'error');
            },
            complete: function() {
                self.setLoading($('#clear-logs'), false);
            }
        });
    };

    /**
     * Download logs as file
     */
    NeweraAdmin.downloadLogs = function() {
        var url = newera_ajax.ajax_url + '?action=newera_download_logs&nonce=' + newera_ajax.nonce;
        window.open(url, '_blank');
    };

    /**
     * Perform bulk actions on modules
     */
    NeweraAdmin.bulkToggleModules = function(moduleIds, enable) {
        var self = this;
        
        $.ajax({
            url: newera_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'newera_bulk_toggle_modules',
                module_ids: moduleIds,
                enable: enable,
                nonce: newera_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var message = enable ? 'modules enabled successfully.' : 'modules disabled successfully.';
                    self.showNotice(moduleIds.length + ' ' + message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    self.showNotice('Bulk action failed: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function() {
                self.showNotice('An error occurred during bulk action.', 'error');
            }
        });
    };

    /**
     * Refresh dashboard statistics
     */
    NeweraAdmin.refreshDashboardStats = function() {
        $.ajax({
            url: newera_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'newera_get_dashboard_stats',
                nonce: newera_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    NeweraAdmin.updateDashboardStats(response.data);
                }
            }
        });
    };

    /**
     * Update dashboard statistics display
     */
    NeweraAdmin.updateDashboardStats = function(stats) {
        // Update stat cards with new values
        $('.newera-stat-number').each(function() {
            var $this = $(this);
            var type = $this.data('stat');
            if (stats[type] !== undefined) {
                $this.text(stats[type]);
            }
        });
    };

    /**
     * Update log statistics
     */
    NeweraAdmin.updateLogStats = function(stats) {
        if (stats.total_entries !== undefined) {
            $('#total-entries').text(stats.total_entries);
        }
        if (stats.file_size !== undefined) {
            $('#file-size').text(stats.file_size);
        }
        if (stats.last_modified !== undefined) {
            $('#last-modified').text(stats.last_modified);
        }
    };

    /**
     * Set loading state for an element
     */
    NeweraAdmin.setLoading = function($element, loading) {
        if (loading) {
            $element.addClass('newera-loading');
            $element.prop('disabled', true);
            
            // Add spinner if it's a button
            if ($element.is('button') || $element.is('input[type="submit"]')) {
                $element.html('<span class="newera-spinner"></span>' + $element.text());
            }
        } else {
            $element.removeClass('newera-loading');
            $element.prop('disabled', false);
            
            // Remove spinner
            $element.find('.newera-spinner').remove();
        }
    };

    /**
     * Show notification message
     */
    NeweraAdmin.showNotice = function(message, type) {
        type = type || 'info';
        
        var noticeClass = 'notice notice-' + type + ' is-dismissible';
        var notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 5000);
        }
    };

    /**
     * Dismiss a notice
     */
    NeweraAdmin.dismissNotice = function(noticeId) {
        $.ajax({
            url: newera_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'newera_dismiss_notice',
                notice_id: noticeId,
                nonce: newera_ajax.nonce
            }
        });
    };

    /**
     * Escape HTML to prevent XSS
     */
    NeweraAdmin.escapeHtml = function(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    };

    // Make NeweraAdmin globally accessible
    window.NeweraAdmin = NeweraAdmin;

})(jQuery);