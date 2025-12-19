<?php
/**
 * Newera Integrations Admin Template
 *
 * @package Newera
 */

if (!defined('ABSPATH')) {
    exit;
}

$linear = isset($linear) ? $linear : (function_exists('newera_get_linear_manager') ? newera_get_linear_manager() : null);
$notion = isset($notion) ? $notion : (function_exists('newera_get_notion_manager') ? newera_get_notion_manager() : null);

$projects = isset($projects) && is_array($projects) ? $projects : [];
$linear_states = isset($linear_states) && is_array($linear_states) ? $linear_states : [];
$linear_teams = isset($linear_teams) && is_array($linear_teams) ? $linear_teams : [];
$visible_states = isset($visible_states) && is_array($visible_states) ? $visible_states : [];
$notion_databases = isset($notion_databases) && is_array($notion_databases) ? $notion_databases : [];
$notion_mappings = isset($notion_mappings) && is_array($notion_mappings) ? $notion_mappings : [];

$linear_configured = $linear && method_exists($linear, 'is_configured') && $linear->is_configured();
$notion_configured = $notion && method_exists($notion, 'is_configured') && $notion->is_configured();

$linear_team_id = $linear && method_exists($linear, 'get_team_id') ? $linear->get_team_id() : '';
$notion_projects_db_id = $notion && method_exists($notion, 'get_projects_database_id') ? $notion->get_projects_database_id() : '';

$notion_db_label = function($db) {
    if (!is_array($db)) {
        return '';
    }

    $title = $db['title'] ?? [];
    if (!is_array($title) || empty($title)) {
        return $db['id'] ?? '';
    }

    $parts = [];
    foreach ($title as $piece) {
        $text = $piece['plain_text'] ?? ($piece['text']['content'] ?? null);
        if (is_string($text) && $text !== '') {
            $parts[] = $text;
        }
    }

    return trim(implode('', $parts));
};
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('newera_integrations_action', 'newera_integrations_nonce'); ?>
        <input type="hidden" name="newera_integrations_action" value="1" />

        <h2><?php _e('Linear', 'newera'); ?></h2>
        <p class="description">
            <?php _e('Connect using the installer\'s Linear API key. No central credential registry is used.', 'newera'); ?>
        </p>

        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="linear_api_key"><?php _e('API Key', 'newera'); ?></label></th>
                    <td>
                        <input type="password" id="linear_api_key" name="linear_api_key" value="" class="regular-text" autocomplete="off" />
                        <p class="description">
                            <?php echo $linear_configured ? esc_html__('A key is stored securely. Leave blank to keep it.', 'newera') : esc_html__('Paste a personal API key from Linear.', 'newera'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="linear_webhook_secret"><?php _e('Webhook Secret', 'newera'); ?></label></th>
                    <td>
                        <input type="password" id="linear_webhook_secret" name="linear_webhook_secret" value="" class="regular-text" autocomplete="off" />
                        <p class="description">
                            <?php _e('Used for signature validation. Configure the same secret in Linear when creating the webhook.', 'newera'); ?>
                        </p>
                        <?php if ($linear && method_exists($linear, 'get_webhook_url')) : ?>
                            <p class="description">
                                <?php _e('Webhook URL:', 'newera'); ?> <code><?php echo esc_html($linear->get_webhook_url()); ?></code>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="linear_team_id"><?php _e('Team', 'newera'); ?></label></th>
                    <td>
                        <?php if (!empty($linear_teams)) : ?>
                            <select id="linear_team_id" name="linear_team_id">
                                <option value=""><?php _e('Select…', 'newera'); ?></option>
                                <?php foreach ($linear_teams as $team) : ?>
                                    <?php
                                    $id = isset($team['id']) ? (string) $team['id'] : '';
                                    $name = isset($team['name']) ? (string) $team['name'] : $id;
                                    ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected($linear_team_id, $id); ?>><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else : ?>
                            <input type="text" id="linear_team_id" name="linear_team_id" value="<?php echo esc_attr($linear_team_id); ?>" class="regular-text" />
                            <p class="description"><?php _e('Enter a Team ID. (Teams list requires a valid API key.)', 'newera'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <h3><?php _e('Client-visible Issue States', 'newera'); ?></h3>
        <p class="description">
            <?php _e('Select which Linear workflow states are visible to client users in Newera.', 'newera'); ?>
        </p>

        <?php if (!$linear_configured) : ?>
            <p class="description"><?php _e('Save a Linear API key to load workflow states.', 'newera'); ?></p>
        <?php elseif (empty($linear_states)) : ?>
            <p class="description"><?php _e('No workflow states found (or API error).', 'newera'); ?></p>
        <?php else : ?>
            <fieldset>
                <?php foreach ($linear_states as $state) : ?>
                    <?php
                    $id = isset($state['id']) ? (string) $state['id'] : '';
                    $name = isset($state['name']) ? (string) $state['name'] : $id;
                    if ($id === '') {
                        continue;
                    }
                    ?>
                    <label style="display:block;margin:4px 0;">
                        <input type="checkbox" name="linear_visible_states[]" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, array_map('strval', $visible_states), true)); ?> />
                        <?php echo esc_html($name); ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        <?php endif; ?>

        <p>
            <button type="submit" class="button button-secondary" name="linear_sync_now" value="1"><?php _e('Sync Linear Now', 'newera'); ?></button>
        </p>

        <hr />

        <h2><?php _e('Notion', 'newera'); ?></h2>
        <p class="description">
            <?php _e('Connect using the installer\'s Notion integration token. No central credential registry is used.', 'newera'); ?>
        </p>

        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="notion_api_key"><?php _e('API Key', 'newera'); ?></label></th>
                    <td>
                        <input type="password" id="notion_api_key" name="notion_api_key" value="" class="regular-text" autocomplete="off" />
                        <p class="description">
                            <?php echo $notion_configured ? esc_html__('A key is stored securely. Leave blank to keep it.', 'newera') : esc_html__('Paste a Notion integration token.', 'newera'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="notion_webhook_secret"><?php _e('Webhook Secret', 'newera'); ?></label></th>
                    <td>
                        <input type="password" id="notion_webhook_secret" name="notion_webhook_secret" value="" class="regular-text" autocomplete="off" />
                        <p class="description"><?php _e('Used for signature validation if you configure a webhook.', 'newera'); ?></p>
                        <?php if ($notion && method_exists($notion, 'get_webhook_url')) : ?>
                            <p class="description">
                                <?php _e('Webhook URL:', 'newera'); ?> <code><?php echo esc_html($notion->get_webhook_url()); ?></code>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="notion_projects_database_id"><?php _e('Projects Database', 'newera'); ?></label></th>
                    <td>
                        <?php if (!empty($notion_databases)) : ?>
                            <select id="notion_projects_database_id" name="notion_projects_database_id">
                                <option value=""><?php _e('None', 'newera'); ?></option>
                                <?php foreach ($notion_databases as $db) : ?>
                                    <?php $id = isset($db['id']) ? (string) $db['id'] : ''; ?>
                                    <?php if ($id === '') { continue; } ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected($notion_projects_db_id, $id); ?>>
                                        <?php echo esc_html($notion_db_label($db) ?: $id); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('If selected, each Newera project syncs to a row (page) in this database.', 'newera'); ?></p>
                        <?php else : ?>
                            <input type="text" id="notion_projects_database_id" name="notion_projects_database_id" value="<?php echo esc_attr($notion_projects_db_id); ?>" class="regular-text" />
                            <p class="description"><?php _e('Enter a database ID. (Listing requires a valid API key.)', 'newera'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <h3><?php _e('Database → Project Mapping', 'newera'); ?></h3>
        <p class="description">
            <?php _e('Map Notion databases to projects. Items in mapped databases will be imported as deliverables.', 'newera'); ?>
        </p>

        <?php if (!$notion_configured) : ?>
            <p class="description"><?php _e('Save a Notion API key to load databases.', 'newera'); ?></p>
        <?php elseif (empty($notion_databases)) : ?>
            <p class="description"><?php _e('No databases found (or API error).', 'newera'); ?></p>
        <?php elseif (empty($projects)) : ?>
            <p class="description"><?php _e('Create at least one project to configure mappings.', 'newera'); ?></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th><?php _e('Notion Database', 'newera'); ?></th>
                        <th><?php _e('Project', 'newera'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notion_databases as $db) : ?>
                        <?php $db_id = isset($db['id']) ? (string) $db['id'] : ''; ?>
                        <?php if ($db_id === '') { continue; } ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($notion_db_label($db) ?: $db_id); ?></strong>
                                <br />
                                <code><?php echo esc_html($db_id); ?></code>
                            </td>
                            <td>
                                <select name="notion_db_map[<?php echo esc_attr($db_id); ?>]">
                                    <option value="0"><?php _e('— Not mapped —', 'newera'); ?></option>
                                    <?php foreach ($projects as $project) : ?>
                                        <option value="<?php echo esc_attr($project->id); ?>" <?php selected((int) ($notion_mappings[$db_id] ?? 0), (int) $project->id); ?>>
                                            <?php echo esc_html($project->title . ' (#' . $project->id . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p>
            <button type="submit" class="button button-secondary" name="notion_sync_now" value="1"><?php _e('Sync Notion Now', 'newera'); ?></button>
        </p>

        <?php submit_button(__('Save Integrations', 'newera')); ?>
    </form>
</div>
