<?php
/**
 * Newera Plugin Logs Template
 *
 * @package Newera
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="newera-logs-toolbar">
        <div class="alignleft actions">
            <select id="log-level-filter">
                <option value="all"><?php _e('All Levels', 'newera'); ?></option>
                <option value="debug"><?php _e('Debug', 'newera'); ?></option>
                <option value="info"><?php _e('Info', 'newera'); ?></option>
                <option value="warning"><?php _e('Warning', 'newera'); ?></option>
                <option value="error"><?php _e('Error', 'newera'); ?></option>
            </select>

            <select id="log-date-filter">
                <option value="all"><?php _e('All Time', 'newera'); ?></option>
                <option value="today"><?php _e('Today', 'newera'); ?></option>
                <option value="week"><?php _e('Last Week', 'newera'); ?></option>
                <option value="month"><?php _e('Last Month', 'newera'); ?></option>
            </select>

            <input type="text" 
                   id="log-search" 
                   placeholder="<?php _e('Search logs...', 'newera'); ?>"
                   class="search-input">

            <button type="button" id="refresh-logs" class="button">
                <?php _e('Refresh', 'newera'); ?>
            </button>
        </div>

        <div class="alignright actions">
            <button type="button" id="clear-logs" class="button button-secondary">
                <?php _e('Clear All Logs', 'newera'); ?>
            </button>
            <button type="button" id="download-logs" class="button button-secondary">
                <?php _e('Download Logs', 'newera'); ?>
            </button>
        </div>
    </div>

    <?php if (empty($log_content)): ?>
        <div class="notice notice-info inline">
            <p><?php _e('No log entries found. The log file may be empty or does not exist yet.', 'newera'); ?></p>
        </div>
    <?php else: ?>
        <div class="newera-logs-container">
            <div class="newera-logs-stats">
                <div class="newera-log-stat">
                    <strong><?php _e('Total Entries:', 'newera'); ?></strong>
                    <span id="total-entries">0</span>
                </div>
                <div class="newera-log-stat">
                    <strong><?php _e('File Size:', 'newera'); ?></strong>
                    <span id="file-size"><?php echo size_format(filesize(WP_CONTENT_DIR . '/newera-logs/newera.log')); ?></span>
                </div>
                <div class="newera-log-stat">
                    <strong><?php _e('Last Modified:', 'newera'); ?></strong>
                    <span id="last-modified"><?php echo date('Y-m-d H:i:s', filemtime(WP_CONTENT_DIR . '/newera-logs/newera.log')); ?></span>
                </div>
            </div>

            <div class="newera-log-viewer" id="log-viewer">
                <!-- Log entries will be loaded here via JavaScript -->
            </div>

            <div class="newera-logs-pagination">
                <button type="button" id="load-more-logs" class="button" style="display: none;">
                    <?php _e('Load More Entries', 'newera'); ?>
                </button>
                <span id="loading-indicator" style="display: none;">
                    <?php _e('Loading...', 'newera'); ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <div class="newera-section" style="margin-top: 30px;">
        <h3><?php _e('Log Information', 'newera'); ?></h3>
        <p><?php _e('The plugin logs various events and activities. Log levels indicate the importance of the entry:', 'newera'); ?></p>
        <ul>
            <li><strong><?php _e('DEBUG:', 'newera'); ?></strong> <?php _e('Detailed information for debugging purposes', 'newera'); ?></li>
            <li><strong><?php _e('INFO:', 'newera'); ?></strong> <?php _e('General information about plugin operation', 'newera'); ?></li>
            <li><strong><?php _e('WARNING:', 'newera'); ?></strong> <?php _e('Warning messages about potential issues', 'newera'); ?></li>
            <li><strong><?php _e('ERROR:', 'newera'); ?></strong> <?php _e('Error messages indicating problems that need attention', 'newera'); ?></li>
        </ul>
        <p><?php _e('Log files are automatically created in the wp-content/newera-logs/ directory.', 'newera'); ?></p>
    </div>
</div>

<style>
.newera-logs-toolbar {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
    margin: 20px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.newera-logs-toolbar .actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-input {
    min-width: 200px;
}

.newera-logs-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.newera-logs-stats {
    padding: 15px;
    border-bottom: 1px solid #ccd0d4;
    display: flex;
    gap: 30px;
    background: #f9f9f9;
}

.newera-log-stat {
    font-size: 13px;
}

.newera-log-stat strong {
    margin-right: 5px;
}

.newera-log-viewer {
    max-height: 600px;
    overflow-y: auto;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.4;
}

.newera-log-entry {
    padding: 8px 15px;
    border-bottom: 1px solid #f0f0f1;
    cursor: pointer;
    transition: background-color 0.2s;
}

.newera-log-entry:hover {
    background-color: #f8f9fa;
}

.newera-log-entry:last-child {
    border-bottom: none;
}

.newera-log-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 3px;
    flex-wrap: wrap;
}

.newera-log-time {
    color: #646970;
    font-weight: bold;
    min-width: 140px;
}

.newera-log-level {
    min-width: 70px;
    text-align: center;
}

.newera-log-caller {
    color: #8c8f94;
    font-style: italic;
    flex: 1;
}

.newera-log-message {
    color: #1d2327;
    margin-left: 220px;
    word-break: break-word;
}

.newera-log-context {
    color: #8c8f94;
    margin-left: 220px;
    font-size: 11px;
    margin-top: 2px;
    opacity: 0.8;
}

.newera-log-entry.expanded .newera-log-message,
.newera-log-entry.expanded .newera-log-context {
    margin-left: 20px;
}

.newera-logs-pagination {
    padding: 15px;
    text-align: center;
    border-top: 1px solid #ccd0d4;
    background: #f9f9f9;
}

#loading-indicator {
    color: #646970;
    font-style: italic;
}

.newera-logs-pagination .button {
    min-width: 150px;
}

/* Log level specific styles */
.newera-log-info { border-left: 3px solid #72aee6; }
.newera-log-warning { border-left: 3px solid #f0b849; }
.newera-log-error { border-left: 3px solid #d63638; }
.newera-log-debug { border-left: 3px solid #8c8f94; }

/* Responsive design */
@media (max-width: 768px) {
    .newera-logs-toolbar {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .newera-logs-toolbar .actions {
        justify-content: flex-start;
        flex-wrap: wrap;
    }
    
    .newera-logs-stats {
        flex-direction: column;
        gap: 10px;
    }
    
    .newera-log-message,
    .newera-log-context {
        margin-left: 20px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    var logData = <?php echo json_encode(explode("\n", trim($log_content))); ?>;
    var currentPage = 0;
    var entriesPerPage = 50;
    var filteredLogs = logData;
    
    // Initialize
    loadLogEntries();
    updateStats();
    
    // Event handlers
    $('#log-level-filter, #log-date-filter').on('change', function() {
        filterLogs();
        loadLogEntries(true);
    });
    
    $('#log-search').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(function() {
            filterLogs(searchTerm);
            loadLogEntries(true);
        }, 300);
    });
    
    $('#refresh-logs').on('click', function() {
        location.reload();
    });
    
    $('#clear-logs').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to clear all logs? This action cannot be undone.', 'newera'); ?>')) {
            $.post(ajaxurl, {
                action: 'newera_clear_logs',
                nonce: '<?php echo wp_create_nonce('newera_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('<?php _e('Failed to clear logs.', 'newera'); ?>');
                }
            });
        }
    });
    
    $('#download-logs').on('click', function() {
        window.location.href = '<?php echo wp_nonce_url(admin_url('admin.php?page=newera-logs&action=download'), 'newera_download_logs'); ?>';
    });
    
    $('#load-more-logs').on('click', function() {
        currentPage++;
        loadLogEntries();
    });
    
    function filterLogs(searchTerm) {
        var levelFilter = $('#log-level-filter').val();
        var dateFilter = $('#log-date-filter').val();
        var searchTerm = $('#log-search').val().toLowerCase();
        
        filteredLogs = logData.filter(function(line) {
            if (!line.trim()) return false;
            
            var entry = parseLogLine(line);
            if (!entry) return false;
            
            // Level filter
            if (levelFilter !== 'all' && entry.level !== levelFilter) {
                return false;
            }
            
            // Date filter
            if (dateFilter !== 'all') {
                var entryDate = new Date(entry.timestamp);
                var now = new Date();
                var diffDays = Math.floor((now - entryDate) / (1000 * 60 * 60 * 24));
                
                switch (dateFilter) {
                    case 'today':
                        if (diffDays > 0) return false;
                        break;
                    case 'week':
                        if (diffDays > 7) return false;
                        break;
                    case 'month':
                        if (diffDays > 30) return false;
                        break;
                }
            }
            
            // Search filter
            if (searchTerm) {
                var searchableText = (entry.message + ' ' + entry.caller + ' ' + JSON.stringify(entry.context)).toLowerCase();
                if (!searchableText.includes(searchTerm)) return false;
            }
            
            return true;
        });
    }
    
    function loadLogEntries(reset) {
        if (reset) {
            $('#log-viewer').empty();
            currentPage = 0;
        }
        
        var startIndex = currentPage * entriesPerPage;
        var endIndex = Math.min(startIndex + entriesPerPage, filteredLogs.length);
        var entries = filteredLogs.slice(startIndex, endIndex);
        
        if (entries.length === 0 && currentPage === 0) {
            $('#log-viewer').html('<p style="padding: 20px; text-align: center; color: #646970;"><?php _e('No log entries found matching the current filters.', 'newera'); ?></p>');
            return;
        }
        
        entries.forEach(function(line) {
            if (line.trim()) {
                var entry = parseLogLine(line);
                if (entry) {
                    appendLogEntry(entry);
                }
            }
        });
        
        // Update load more button
        if (endIndex < filteredLogs.length) {
            $('#load-more-logs').show();
        } else {
            $('#load-more-logs').hide();
        }
        
        updateStats();
    }
    
    function appendLogEntry(entry) {
        var logLevelClass = 'newera-log-' + entry.level;
        var badgeClass = 'newera-badge-' + (entry.level === 'error' ? 'red' : entry.level === 'warning' ? 'yellow' : entry.level === 'debug' ? 'gray' : 'blue');
        
        var html = '<div class="newera-log-entry ' + logLevelClass + '">';
        html += '<div class="newera-log-header">';
        html += '<span class="newera-log-time">' + entry.timestamp + '</span>';
        html += '<span class="newera-log-level"><span class="newera-badge ' + badgeClass + '">' + entry.level.toUpperCase() + '</span></span>';
        html += '<span class="newera-log-caller">' + entry.caller + '</span>';
        html += '</div>';
        html += '<div class="newera-log-message">' + escapeHtml(entry.message) + '</div>';
        if (entry.context && Object.keys(entry.context).length > 0) {
            html += '<div class="newera-log-context"><small>' + escapeHtml(JSON.stringify(entry.context)) + '</small></div>';
        }
        html += '</div>';
        
        $('#log-viewer').append(html);
        
        // Add click handler for expansion
        $('.newera-log-entry').last().on('click', function() {
            $(this).toggleClass('expanded');
        });
    }
    
    function parseLogLine(line) {
        // Expected format: [2023-12-14 11:00:00] [INFO] [Class::method] Message {"context":"data"}
        var pattern = /\[([^\]]+)\] \[([^\]]+)\] \[([^\]]+)\] (.+)/;
        var matches = line.match(pattern);
        
        if (!matches) return null;
        
        var timestamp = matches[1];
        var level = matches[2].toLowerCase();
        var caller = matches[3];
        var message = matches[4];
        
        // Try to parse context
        var context = {};
        var contextMatch = message.match(/\{.+\}$/);
        if (contextMatch) {
            message = message.substring(0, message.lastIndexOf(contextMatch[0])).trim();
            try {
                context = JSON.parse(contextMatch[0]);
            } catch (e) {
                // Invalid JSON, ignore context
            }
        }
        
        return {
            timestamp: timestamp,
            level: level,
            caller: caller,
            message: message,
            context: context
        };
    }
    
    function updateStats() {
        $('#total-entries').text(filteredLogs.length);
        
        var fileSize = '<?php echo file_exists(WP_CONTENT_DIR . '/newera-logs/newera.log') ? size_format(filesize(WP_CONTENT_DIR . '/newera-logs/newera.log')) : '0 B'; ?>';
        $('#file-size').text(fileSize);
    }
    
    function escapeHtml(text) {
        var map = {
            '&': '&',
            '<': '<',
            '>': '>',
            '"': '"',
            "'": '''
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>