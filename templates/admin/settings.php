<?php
/**
 * Newera Plugin Settings Template
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

    <form method="post" action="">
        <?php wp_nonce_field('newera_settings_action', 'newera_settings_nonce'); ?>
        <input type="hidden" name="newera_save_settings" value="1">

        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="newera_setting_enable_logging"><?php _e('Enable Logging', 'newera'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               id="newera_setting_enable_logging" 
                               name="newera_setting_enable_logging" 
                               value="1"
                               <?php checked(isset($settings['enable_logging']) ? $settings['enable_logging'] : true); ?>>
                        <p class="description"><?php _e('Enable plugin logging for debugging purposes.', 'newera'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="newera_setting_log_level"><?php _e('Log Level', 'newera'); ?></label>
                    </th>
                    <td>
                        <select id="newera_setting_log_level" name="newera_setting_log_level">
                            <option value="debug" <?php selected(isset($settings['log_level']) ? $settings['log_level'] : 'info', 'debug'); ?>>
                                <?php _e('Debug', 'newera'); ?>
                            </option>
                            <option value="info" <?php selected(isset($settings['log_level']) ? $settings['log_level'] : 'info', 'info'); ?>>
                                <?php _e('Info', 'newera'); ?>
                            </option>
                            <option value="warning" <?php selected(isset($settings['log_level']) ? $settings['log_level'] : 'info', 'warning'); ?>>
                                <?php _e('Warning', 'newera'); ?>
                            </option>
                            <option value="error" <?php selected(isset($settings['log_level']) ? $settings['log_level'] : 'info', 'error'); ?>>
                                <?php _e('Error', 'newera'); ?>
                            </option>
                        </select>
                        <p class="description"><?php _e('Minimum log level to record.', 'newera'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="newera_setting_auto_cleanup_logs"><?php _e('Auto Cleanup Logs', 'newera'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               id="newera_setting_auto_cleanup_logs" 
                               name="newera_setting_auto_cleanup_logs" 
                               value="1"
                               <?php checked(isset($settings['auto_cleanup_logs']) ? $settings['auto_cleanup_logs'] : false); ?>>
                        <p class="description"><?php _e('Automatically clean up old log files (older than 30 days).', 'newera'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="newera_setting_max_log_size"><?php _e('Max Log Size (MB)', 'newera'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="newera_setting_max_log_size" 
                               name="newera_setting_max_log_size" 
                               min="1" 
                               max="100" 
                               value="<?php echo esc_attr(isset($settings['max_log_size']) ? $settings['max_log_size'] : 10); ?>"
                               class="small-text">
                        <p class="description"><?php _e('Maximum log file size before rotation (1-100 MB).', 'newera'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="newera_setting_modules_per_page"><?php _e('Modules Per Page', 'newera'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="newera_setting_modules_per_page" 
                               name="newera_setting_modules_per_page" 
                               min="5" 
                               max="50" 
                               value="<?php echo esc_attr(isset($settings['modules_per_page']) ? $settings['modules_per_page'] : 10); ?>"
                               class="small-text">
                        <p class="description"><?php _e('Number of modules to display per page in the modules list.', 'newera'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="newera_setting_health_check_interval"><?php _e('Health Check Interval (hours)', 'newera'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="newera_setting_health_check_interval" 
                               name="newera_setting_health_check_interval" 
                               min="1" 
                               max="168" 
                               value="<?php echo esc_attr(isset($settings['health_check_interval']) ? $settings['health_check_interval'] : 24); ?>"
                               class="small-text">
                        <p class="description"><?php _e('How often to run automatic health checks (1-168 hours).', 'newera'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="newera_setting_debug_mode"><?php _e('Debug Mode', 'newera'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               id="newera_setting_debug_mode" 
                               name="newera_setting_debug_mode" 
                               value="1"
                               <?php checked(isset($settings['debug_mode']) ? $settings['debug_mode'] : false); ?>>
                        <p class="description"><?php _e('Enable debug mode for additional logging and error reporting. Only enable for development.', 'newera'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save Settings', 'newera')); ?>
    </form>

    <hr>

    <h2><?php _e('System Information', 'newera'); ?></h2>
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row"><?php _e('WordPress Version', 'newera'); ?></th>
                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('PHP Version', 'newera'); ?></th>
                <td><?php echo esc_html(PHP_VERSION); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Plugin Version', 'newera'); ?></th>
                <td><?php echo esc_html(NEWERA_VERSION); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Database Charset', 'newera'); ?></th>
                <td><?php echo esc_html(DB_CHARSET); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Memory Limit', 'newera'); ?></th>
                <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Upload Max Filesize', 'newera'); ?></th>
                <td><?php echo esc_html(ini_get('upload_max_filesize')); ?></td>
            </tr>
        </tbody>
    </table>

    <hr>

    <h2><?php _e('Danger Zone', 'newera'); ?></h2>
    <div class="notice notice-warning inline">
        <p><?php _e('The following actions are irreversible. Please proceed with caution.', 'newera'); ?></p>
    </div>

    <p>
        <button type="button" 
                id="newera-clear-logs" 
                class="button button-secondary">
            <?php _e('Clear All Logs', 'newera'); ?>
        </button>
        <button type="button" 
                id="newera-reset-settings" 
                class="button button-secondary">
            <?php _e('Reset to Defaults', 'newera'); ?>
        </button>
    </p>

    <script>
    jQuery(document).ready(function($) {
        $('#newera-clear-logs').on('click', function() {
            if (confirm('<?php _e('Are you sure you want to clear all logs? This action cannot be undone.', 'newera'); ?>')) {
                // AJAX call to clear logs
                $.post(ajaxurl, {
                    action: 'newera_clear_logs',
                    nonce: '<?php echo wp_create_nonce('newera_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Logs cleared successfully.', 'newera'); ?>');
                    } else {
                        alert('<?php _e('Failed to clear logs.', 'newera'); ?>');
                    }
                });
            }
        });

        $('#newera-reset-settings').on('click', function() {
            if (confirm('<?php _e('Are you sure you want to reset all settings to defaults? This action cannot be undone.', 'newera'); ?>')) {
                // AJAX call to reset settings
                $.post(ajaxurl, {
                    action: 'newera_reset_settings',
                    nonce: '<?php echo wp_create_nonce('newera_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Settings reset successfully. Page will reload.', 'newera'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('Failed to reset settings.', 'newera'); ?>');
                    }
                });
            }
        });
    });
    </script>
</div>