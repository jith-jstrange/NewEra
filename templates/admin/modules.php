<?php
/**
 * Newera Plugin Modules Template
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

    <p><?php _e('Manage and configure plugin modules. Enable or disable modules based on your needs.', 'newera'); ?></p>

    <?php if (empty($modules)): ?>
        <div class="notice notice-info inline">
            <p><?php _e('No modules are currently registered. Modules will appear here as they are added to the system.', 'newera'); ?></p>
        </div>
    <?php else: ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <select id="bulk-action-selector-top">
                    <option value="-1"><?php _e('Bulk Actions', 'newera'); ?></option>
                    <option value="enable"><?php _e('Enable', 'newera'); ?></option>
                    <option value="disable"><?php _e('Disable', 'newera'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php _e('Apply', 'newera'); ?>">
            </div>
            
            <div class="alignright">
                <span class="displaying-num">
                    <?php printf(__('%s modules', 'newera'), count($modules)); ?>
                </span>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped modules">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1">
                    </td>
                    <th class="manage-column column-name column-primary"><?php _e('Module', 'newera'); ?></th>
                    <th class="manage-column column-status"><?php _e('Status', 'newera'); ?></th>
                    <th class="manage-column column-access"><?php _e('Access', 'newera'); ?></th>
                    <th class="manage-column column-dependencies"><?php _e('Dependencies', 'newera'); ?></th>
                    <th class="manage-column column-actions"><?php _e('Actions', 'newera'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $module_id => $module): ?>
                    <tr class="<?php echo $module['enabled'] ? 'module-enabled' : 'module-disabled'; ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="module[]" value="<?php echo esc_attr($module_id); ?>">
                        </th>
                        <td class="column-name column-primary">
                            <strong><?php echo esc_html($module['name']); ?></strong>
                            <div class="row-actions">
                                <?php if ($module['enabled']): ?>
                                    <span class="disable">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=newera-modules&action=disable&module=' . $module_id)); ?>">
                                            <?php _e('Disable', 'newera'); ?>
                                        </a>
                                    </span>
                                <?php else: ?>
                                    <span class="enable">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=newera-modules&action=enable&module=' . $module_id)); ?>">
                                            <?php _e('Enable', 'newera'); ?>
                                        </a>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($module['capabilities'])): ?>
                                    <span class="view-capabilities">
                                        | <a href="#capabilities-<?php echo esc_attr($module_id); ?>" class="capabilities-toggle">
                                            <?php _e('View Capabilities', 'newera'); ?>
                                        </a>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="toggle-row">
                                <span class="screen-reader-text"><?php _e('Show more details', 'newera'); ?></span>
                            </button>
                            <div class="module-description">
                                <p><?php echo esc_html($module['description']); ?></p>
                                <?php if (!empty($module['capabilities'])): ?>
                                    <div id="capabilities-<?php echo esc_attr($module_id); ?>" class="module-capabilities" style="display: none;">
                                        <strong><?php _e('Required Capabilities:', 'newera'); ?></strong>
                                        <code><?php echo esc_html(implode(', ', $module['capabilities'])); ?></code>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="column-status">
                            <?php if ($module['enabled']): ?>
                                <span class="newera-badge newera-badge-green"><?php _e('Enabled', 'newera'); ?></span>
                            <?php else: ?>
                                <span class="newera-badge newera-badge-red"><?php _e('Disabled', 'newera'); ?></span>
                            <?php endif; ?>
                            
                            <?php if ($module['class_exists']): ?>
                                <span class="newera-badge newera-badge-blue" title="<?php _e('Module class is loaded', 'newera'); ?>">✓</span>
                            <?php else: ?>
                                <span class="newera-badge newera-badge-gray" title="<?php _e('Module class not found', 'newera'); ?>">✗</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-access">
                            <?php if ($module['can_access']): ?>
                                <span class="newera-badge newera-badge-green"><?php _e('Allowed', 'newera'); ?></span>
                            <?php else: ?>
                                <span class="newera-badge newera-badge-red"><?php _e('Denied', 'newera'); ?></span>
                                <br><small><?php _e('Insufficient permissions', 'newera'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="column-dependencies">
                            <?php if (empty($module['dependencies'])): ?>
                                <span class="newera-badge newera-badge-gray"><?php _e('None', 'newera'); ?></span>
                            <?php else: ?>
                                <?php foreach ($module['dependencies'] as $dep): ?>
                                    <span class="newera-badge newera-badge-yellow"><?php echo esc_html($dep); ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td class="column-actions">
                            <?php if ($module['enabled'] && $module['can_access']): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=newera&module=' . $module_id)); ?>" 
                                   class="button button-small">
                                    <?php _e('Configure', 'newera'); ?>
                                </a>
                            <?php endif; ?>
                            
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('newera_module_action', 'module_nonce'); ?>
                                <input type="hidden" name="action" value="toggle_module">
                                <input type="hidden" name="module_id" value="<?php echo esc_attr($module_id); ?>">
                                <input type="hidden" name="enable" value="<?php echo $module['enabled'] ? 'false' : 'true'; ?>">
                                
                                <?php if ($module['enabled']): ?>
                                    <input type="submit" 
                                           class="button button-small" 
                                           value="<?php _e('Disable', 'newera'); ?>"
                                           onclick="return confirm('<?php _e('Are you sure you want to disable this module?', 'newera'); ?>')">
                                <?php else: ?>
                                    <input type="submit" 
                                           class="button button-small button-primary" 
                                           value="<?php _e('Enable', 'newera'); ?>">
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="alignleft actions">
                <select id="bulk-action-selector-bottom">
                    <option value="-1"><?php _e('Bulk Actions', 'newera'); ?></option>
                    <option value="enable"><?php _e('Enable', 'newera'); ?></option>
                    <option value="disable"><?php _e('Disable', 'newera'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php _e('Apply', 'newera'); ?>">
            </div>
        </div>
    <?php endif; ?>

    <div class="newera-section" style="margin-top: 30px;">
        <h3><?php _e('Module Information', 'newera'); ?></h3>
        <p><?php _e('Modules are individual components that extend the plugin\'s functionality. Each module can be enabled or disabled based on your requirements.', 'newera'); ?></p>
        <ul>
            <li><strong><?php _e('Enabled:', 'newera'); ?></strong> <?php _e('Module is active and loaded', 'newera'); ?></li>
            <li><strong><?php _e('Disabled:', 'newera'); ?></strong> <?php _e('Module is not loaded', 'newera'); ?></li>
            <li><strong><?php _e('Access:', 'newera'); ?></strong> <?php _e('Whether current user can use the module', 'newera'); ?></li>
            <li><strong><?php _e('Dependencies:', 'newera'); ?></strong> <?php _e('Other modules this module requires', 'newera'); ?></li>
        </ul>
    </div>
</div>

<style>
.modules td.column-name {
    width: 40%;
}

.module-enabled {
    background-color: #f9f9f9;
}

.module-disabled {
    opacity: 0.7;
}

.module-capabilities {
    margin-top: 10px;
    padding: 10px;
    background: #f1f1f1;
    border-radius: 3px;
}

.capabilities-toggle {
    color: #0073aa;
    text-decoration: none;
}

.capabilities-toggle:hover {
    color: #005177;
    text-decoration: underline;
}

.module-description {
    margin-top: 5px;
    color: #666;
    font-size: 13px;
}

.column-dependencies .newera-badge {
    margin: 2px;
}

.column-actions form {
    margin-left: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle capabilities display
    $('.capabilities-toggle').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        $(target).toggle();
    });

    // Select all checkbox
    $('#cb-select-all-1').on('change', function() {
        $('input[name="module[]"]').prop('checked', this.checked);
    });

    // Bulk actions
    $('input.button.action').on('click', function(e) {
        var action = $(this).closest('.tablenav').find('select').val();
        var modules = [];
        
        $('input[name="module[]"]:checked').each(function() {
            modules.push($(this).val());
        });

        if (action === '-1') {
            alert('<?php _e('Please select an action to perform.', 'newera'); ?>');
            e.preventDefault();
            return;
        }

        if (modules.length === 0) {
            alert('<?php _e('Please select at least one module.', 'newera'); ?>');
            e.preventDefault();
            return;
        }

        if (!confirm('<?php _e('Are you sure you want to perform this bulk action?', 'newera'); ?>')) {
            e.preventDefault();
            return;
        }

        // Here you would handle the bulk action via AJAX or form submission
        console.log('Bulk action:', action, 'Modules:', modules);
    });
});
</script>