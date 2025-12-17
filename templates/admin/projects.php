<?php
/**
 * Newera Projects Admin Template
 *
 * @package Newera
 */

if (!defined('ABSPATH')) {
    exit;
}

$project_manager = isset($project_manager) ? $project_manager : (function_exists('newera_get_project_manager') ? newera_get_project_manager() : null);
$projects = isset($projects) && is_array($projects) ? $projects : [];
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="newera-section" style="margin-top: 20px;">
        <h2><?php _e('Create Project', 'newera'); ?></h2>

        <form method="post" action="">
            <?php wp_nonce_field('newera_projects_action', 'newera_projects_nonce'); ?>
            <input type="hidden" name="newera_projects_action" value="1" />
            <input type="hidden" name="projects_action_type" value="create" />

            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="title"><?php _e('Title', 'newera'); ?></label></th>
                        <td><input type="text" id="title" name="title" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="description"><?php _e('Description', 'newera'); ?></label></th>
                        <td><textarea id="description" name="description" class="large-text" rows="4"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="status"><?php _e('Status', 'newera'); ?></label></th>
                        <td>
                            <select id="status" name="status">
                                <option value="pending"><?php _e('Pending', 'newera'); ?></option>
                                <option value="active"><?php _e('Active', 'newera'); ?></option>
                                <option value="done"><?php _e('Done', 'newera'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="progress"><?php _e('Progress', 'newera'); ?></label></th>
                        <td><input type="number" id="progress" name="progress" min="0" max="100" value="0" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="client_id"><?php _e('Client ID', 'newera'); ?></label></th>
                        <td><input type="number" id="client_id" name="client_id" min="0" value="0" /> <span class="description"><?php _e('Optional. Use 0 for internal/unassigned.', 'newera'); ?></span></td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button(__('Create Project', 'newera')); ?>
        </form>
    </div>

    <hr />

    <div class="newera-section">
        <h2><?php _e('Projects', 'newera'); ?></h2>

        <?php if (empty($projects)) : ?>
            <p class="description"><?php _e('No projects found.', 'newera'); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'newera'); ?></th>
                        <th><?php _e('Title', 'newera'); ?></th>
                        <th><?php _e('Status', 'newera'); ?></th>
                        <th><?php _e('Client ID', 'newera'); ?></th>
                        <th><?php _e('Updated', 'newera'); ?></th>
                        <th><?php _e('Actions', 'newera'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project) : ?>
                        <tr>
                            <td><?php echo esc_html($project->id); ?></td>
                            <td><?php echo esc_html($project->title); ?></td>
                            <td><?php echo esc_html($project->status); ?></td>
                            <td><?php echo esc_html($project->client_id); ?></td>
                            <td><?php echo esc_html($project->updated_at ?? ''); ?></td>
                            <td>
                                <form method="post" action="" style="display:inline;">
                                    <?php wp_nonce_field('newera_projects_action', 'newera_projects_nonce'); ?>
                                    <input type="hidden" name="newera_projects_action" value="1" />
                                    <input type="hidden" name="projects_action_type" value="delete" />
                                    <input type="hidden" name="project_id" value="<?php echo esc_attr($project->id); ?>" />
                                    <?php submit_button(__('Delete', 'newera'), 'delete', 'submit', false, ['onclick' => 'return confirm("' . esc_js(__('Are you sure?', 'newera')) . '");']); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="description" style="margin-top: 10px;">
                <?php _e('Creating a project can auto-create a Linear issue / Notion page if those integrations are configured.', 'newera'); ?>
            </p>
        <?php endif; ?>
    </div>
</div>
