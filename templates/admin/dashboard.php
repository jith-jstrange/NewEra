<?php
/**
 * Newera Plugin Dashboard Template
 *
 * @package Newera
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>
        <?php echo esc_html(get_admin_page_title()); ?>
        <span class="newera-version">v<?php echo esc_html(NEWERA_VERSION); ?></span>
    </h1>

    <?php if (!empty($recent_logs)): ?>
        <div class="notice notice-info inline">
            <p><?php _e('Plugin is working correctly. Recent activity shown below.', 'newera'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Dashboard Stats Cards -->
    <div class="newera-stats-grid">
        <div class="newera-stat-card">
            <div class="newera-stat-icon">⚡</div>
            <div class="newera-stat-content">
                <div class="newera-stat-number"><?php echo esc_html($stats['active_modules']); ?></div>
                <div class="newera-stat-label"><?php _e('Active Modules', 'newera'); ?></div>
            </div>
        </div>

        <div class="newera-stat-card">
            <div class="newera-stat-icon">📊</div>
            <div class="newera-stat-content">
                <div class="newera-stat-number"><?php echo esc_html($stats['total_modules']); ?></div>
                <div class="newera-stat-label"><?php _e('Total Modules', 'newera'); ?></div>
            </div>
        </div>

        <div class="newera-stat-card">
            <div class="newera-stat-icon">🔄</div>
            <div class="newera-stat-content">
                <div class="newera-stat-number"><?php echo $dashboard->format_timestamp($stats['last_migration']); ?></div>
                <div class="newera-stat-label"><?php _e('Last Migration', 'newera'); ?></div>
            </div>
        </div>

        <div class="newera-stat-card">
            <div class="newera-stat-icon">📅</div>
            <div class="newera-stat-content">
                <div class="newera-stat-number"><?php echo $dashboard->format_timestamp($stats['activation_date']); ?></div>
                <div class="newera-stat-label"><?php _e('Activated', 'newera'); ?></div>
            </div>
        </div>
    </div>

    <!-- Health Status Section -->
    <div class="newera-section">
        <h2>
            <?php _e('System Health', 'newera'); ?>
            <?php echo $dashboard->get_health_status_badge($health_info['status']); ?>
        </h2>

        <?php if (!empty($health_info['issues'])): ?>
            <div class="notice notice-warning inline">
                <p><strong><?php _e('Issues detected:', 'newera'); ?></strong></p>
                <ul>
                    <?php foreach ($health_info['issues'] as $issue): ?>
                        <li><?php echo esc_html($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Check', 'newera'); ?></th>
                    <th><?php _e('Status', 'newera'); ?></th>
                    <th><?php _e('Details', 'newera'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($health_info['checks'] as $check): ?>
                    <tr>
                        <td><strong><?php echo esc_html($check['name']); ?></strong></td>
                        <td><?php echo $dashboard->get_health_status_badge($check['status']); ?></td>
                        <td><?php echo esc_html($check['message']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modules Status Section -->
    <div class="newera-section">
        <h2><?php _e('Modules Status', 'newera'); ?></h2>
        
        <?php if (empty($modules_status)): ?>
            <p><?php _e('No modules registered.', 'newera'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Module', 'newera'); ?></th>
                        <th><?php _e('Status', 'newera'); ?></th>
                        <th><?php _e('Access', 'newera'); ?></th>
                        <th><?php _e('Class', 'newera'); ?></th>
                        <th><?php _e('Actions', 'newera'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules_status as $module_id => $module): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($module['name']); ?></strong>
                                <br><small><?php echo esc_html($module['description']); ?></small>
                            </td>
                            <td>
                                <?php if ($module['enabled']): ?>
                                    <span class="newera-badge newera-badge-green"><?php _e('Enabled', 'newera'); ?></span>
                                <?php else: ?>
                                    <span class="newera-badge newera-badge-red"><?php _e('Disabled', 'newera'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($module['can_access']): ?>
                                    <span class="newera-badge newera-badge-blue"><?php _e('Allowed', 'newera'); ?></span>
                                <?php else: ?>
                                    <span class="newera-badge newera-badge-gray"><?php _e('No Access', 'newera'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($module['class_exists']): ?>
                                    <span class="newera-badge newera-badge-green">✓</span>
                                <?php else: ?>
                                    <span class="newera-badge newera-badge-red">✗</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($module['enabled']): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=newera-modules&action=disable&module=' . $module_id)); ?>" class="button button-small"><?php _e('Disable', 'newera'); ?></a>
                                <?php else: ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=newera-modules&action=enable&module=' . $module_id)); ?>" class="button button-small button-primary"><?php _e('Enable', 'newera'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Recent Logs Section -->
    <div class="newera-section">
        <h2><?php _e('Recent Activity', 'newera'); ?></h2>
        
        <?php if (empty($recent_logs)): ?>
            <p><?php _e('No recent activity.', 'newera'); ?></p>
        <?php else: ?>
            <div class="newera-log-viewer">
                <?php foreach ($recent_logs as $log_entry): ?>
                    <div class="newera-log-entry newera-log-<?php echo esc_attr($log_entry['level']); ?>">
                        <div class="newera-log-header">
                            <span class="newera-log-time"><?php echo esc_html($log_entry['timestamp']); ?></span>
                            <?php echo $dashboard->get_log_level_badge($log_entry['level']); ?>
                            <span class="newera-log-caller"><?php echo esc_html($log_entry['caller']); ?></span>
                        </div>
                        <div class="newera-log-message"><?php echo esc_html($log_entry['message']); ?></div>
                        <?php if (!empty($log_entry['context'])): ?>
                            <div class="newera-log-context">
                                <small><?php echo esc_html(json_encode($log_entry['context'])); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=newera-logs')); ?>" class="button">
                    <?php _e('View All Logs', 'newera'); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</div>

<style>
.newera-version {
    font-size: 14px;
    color: #666;
    font-weight: normal;
    margin-left: 10px;
}

.newera-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.newera-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.newera-stat-icon {
    font-size: 24px;
    opacity: 0.7;
}

.newera-stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #1d2327;
}

.newera-stat-label {
    color: #646970;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.newera-section {
    margin: 30px 0;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.newera-section h2 {
    padding: 20px 20px 0 20px;
    margin: 0;
    border-bottom: 1px solid #ccd0d4;
    padding-bottom: 15px;
}

.newera-section table {
    border: none;
    margin: 0;
}

.newera-section table thead th {
    border-bottom: 1px solid #ccd0d4;
}

.newera-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.newera-badge-green { background: #d1e7dd; color: #0f5132; }
.newera-badge-yellow { background: #fff3cd; color: #664d03; }
.newera-badge-red { background: #f8d7da; color: #842029; }
.newera-badge-blue { background: #cff4fc; color: #055160; }
.newera-badge-gray { background: #e2e3e5; color: #41464b; }

.newera-log-viewer {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 15px 0;
}

.newera-log-entry {
    padding: 10px 15px;
    border-bottom: 1px solid #f0f0f1;
    font-family: 'Courier New', monospace;
    font-size: 12px;
}

.newera-log-entry:last-child {
    border-bottom: none;
}

.newera-log-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 5px;
}

.newera-log-time {
    color: #646970;
    font-weight: bold;
}

.newera-log-caller {
    color: #8c8f94;
    font-style: italic;
}

.newera-log-message {
    color: #1d2327;
    margin-bottom: 2px;
}

.newera-log-context {
    color: #8c8f94;
}

.newera-log-info { border-left: 3px solid #72aee6; }
.newera-log-warning { border-left: 3px solid #f0b849; }
.newera-log-error { border-left: 3px solid #d63638; }
.newera-log-debug { border-left: 3px solid #8c8f94; }
</style>