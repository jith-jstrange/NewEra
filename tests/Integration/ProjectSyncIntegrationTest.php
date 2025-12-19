<?php
/**
 * Integration tests for Project sync workflows
 */

namespace Newera\Tests\Integration;

use Newera\Modules\Projects\ProjectsModule;
use Newera\Modules\Integrations\IntegrationsModule;
use Newera\Core\StateManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Project Sync Integration Test
 * 
 * Tests end-to-end project sync workflows including:
 * - Linear connect → fetch issues
 * - Issue created in Linear → plugin project created
 * - Plugin update → Linear issue updated
 * - Activity logging all sync ops
 * - Notion DB map → project creation
 * - Bidirectional sync conflict handling
 */
class ProjectSyncIntegrationTest extends \Newera\Tests\Integration\IntegrationTestCase {
    
    /**
     * Projects module instance
     *
     * @var ProjectsModule
     */
    private $projectsModule;
    
    /**
     * Integrations module instance
     *
     * @var IntegrationsModule
     */
    private $integrationsModule;
    
    /**
     * Mock Linear API
     */
    private function mockLinearAPI() {
        if (!class_exists('MockLinearAPI')) {
            class MockLinearAPI {
                private $api_key = null;
                private $issues = [];
                private $projects = [];
                private $teams = [];
                
                public function setApiKey($key) {
                    $this->api_key = $key;
                    return $key !== null;
                }
                
                public function fetchIssues($team_id = null) {
                    if (!$this->api_key) {
                        throw new \Exception('API key not set');
                    }
                    
                    // Return mock issues
                    return [
                        'issues' => [
                            [
                                'id' => 'issue_123',
                                'title' => 'Test Issue from Linear',
                                'description' => 'This is a test issue created in Linear',
                                'state' => 'todo',
                                'assignee' => 'user_456',
                                'team' => 'team_789',
                                'createdAt' => '2023-01-01T12:00:00Z',
                                'updatedAt' => '2023-01-01T12:00:00Z'
                            ],
                            [
                                'id' => 'issue_456',
                                'title' => 'Another Linear Issue',
                                'description' => 'Second test issue',
                                'state' => 'in_progress',
                                'assignee' => 'user_789',
                                'team' => 'team_789',
                                'createdAt' => '2023-01-02T12:00:00Z',
                                'updatedAt' => '2023-01-02T12:00:00Z'
                            ]
                        ]
                    ];
                }
                
                public function createIssue($issue_data) {
                    if (!$this->api_key) {
                        throw new \Exception('API key not set');
                    }
                    
                    $issue_id = 'issue_' . uniqid();
                    $issue = array_merge([
                        'id' => $issue_id,
                        'createdAt' => date('c'),
                        'updatedAt' => date('c')
                    ], $issue_data);
                    
                    $this->issues[$issue_id] = $issue;
                    return $issue;
                }
                
                public function updateIssue($issue_id, $update_data) {
                    if (!$this->api_key) {
                        throw new \Exception('API key not set');
                    }
                    
                    if (isset($this->issues[$issue_id])) {
                        $this->issues[$issue_id] = array_merge($this->issues[$issue_id], $update_data);
                        return $this->issues[$issue_id];
                    }
                    
                    throw new \Exception('Issue not found');
                }
                
                public function fetchProjects() {
                    if (!$this->api_key) {
                        throw new \Exception('API key not set');
                    }
                    
                    return [
                        'projects' => [
                            [
                                'id' => 'proj_123',
                                'name' => 'Test Linear Project',
                                'description' => 'Test project from Linear',
                                'state' => 'active',
                                'teams' => ['team_789'],
                                'createdAt' => '2023-01-01T12:00:00Z',
                                'updatedAt' => '2023-01-01T12:00:00Z'
                            ]
                        ]
                    ];
                }
                
                public function createProject($project_data) {
                    if (!$this->api_key) {
                        throw new \Exception('API key not set');
                    }
                    
                    $project_id = 'proj_' . uniqid();
                    $project = array_merge([
                        'id' => $project_id,
                        'createdAt' => date('c'),
                        'updatedAt' => date('c')
                    ], $project_data);
                    
                    $this->projects[$project_id] = $project;
                    return $project;
                }
                
                public function webhook($event_type, $data) {
                    // Simulate webhook call
                    return [
                        'event' => $event_type,
                        'data' => $data,
                        'timestamp' => time()
                    ];
                }
                
                public function getIssues() {
                    return array_values($this->issues);
                }
                
                public function getProjects() {
                    return array_values($this->projects);
                }
            }
        }
        
        return new \MockLinearAPI();
    }
    
    /**
     * Mock Notion API
     */
    private function mockNotionAPI() {
        if (!class_exists('MockNotionAPI')) {
            class MockNotionAPI {
                private $api_key = null;
                private $databases = [];
                private $pages = [];
                
                public function setApiKey($key) {
                    $this->api_key = $key;
                    return $key !== null;
                }
                
                public function fetchDatabases() {
                    if (!$this->api_key) {
                        throw new \Exception('API key not set');
                    }
                    
                    return [
                        'results' => [
                            [
                                'id' => 'db_123',
                                'title' => [{'text' => ['content' => 'Project Database']}],
                                'properties' => [
                                    'Name' => ['title' => []],
                                    'Status' => ['select' => ['options' => [
                                        ['name' => 'Todo', 'color' => 'red'],
                                        ['name' => 'In Progress', 'color' => 'yellow'],
                                        ['name' => 'Done', 'color' => 'green']
                                    ]]],
                                    'Priority' => ['select' => ['options' => [
                                        ['name' => 'High', 'color' => 'red'],
                                        ['name' => 'Medium', 'color' => 'yellow'],
                                        ['name' => 'Low', 'color' => 'green']
                                    ]]]
                                ]
                            ]
                        ]
                    ];
                }
                
                public function createPage($database_id, $properties) {
                    if (!$this->api_key) {
                        throw new \Exception('API key not set');
                    }
                    
                    $page_id = 'page_' . uniqid();
                    $page = [
                        'id' => $page_id,
                        'url' => 'https://notion.so/' . $page_id,
                        'properties' => $properties,
                        'created_time' => date('c'),
                        'last_edited_time' => date('c')
                    ];
                    
                    $this->pages[$page_id] = $page;
                    return $page;
                }
                
                public function updatePage($page_id, $properties) {
                    if (!$this->api_key) {
                        throw new \Exception('API key not set');
                    }
                    
                    if (isset($this->pages[$page_id])) {
                        $this->pages[$page_id]['properties'] = array_merge(
                            $this->pages[$page_id]['properties'],
                            $properties
                        );
                        $this->pages[$page_id]['last_edited_time'] = date('c');
                        return $this->pages[$page_id];
                    }
                    
                    throw new \Exception('Page not found');
                }
                
                public function getDatabases() {
                    return array_values($this->databases);
                }
                
                public function getPages() {
                    return array_values($this->pages);
                }
            }
        }
        
        return new \MockNotionAPI();
    }
    
    /**
     * Setup test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        $this->stateManager = new StateManager();
        $this->projectsModule = new ProjectsModule($this->stateManager);
        $this->integrationsModule = new IntegrationsModule($this->stateManager);
        
        // Mock external APIs
        $this->mockLinearAPI();
        $this->mockNotionAPI();
        
        // Reset state for clean test
        $this->stateManager->reset_state();
    }
    
    /**
     * Test Linear connect and fetch issues
     */
    public function testLinearConnectAndFetchIssues() {
        // Configure Linear integration
        $linear_config = [
            'enabled' => true,
            'api_key' => 'lin_test_api_key_12345',
            'team_id' => 'team_789'
        ];
        
        $connect_result = $this->integrationsModule->connectLinear($linear_config);
        
        $this->assertTrue($connect_result['success']);
        $this->assertEquals('connected', $connect_result['status']);
        
        // Store API key securely
        $stored_api_key = $this->stateManager->getSecure('integrations', 'linear_api_key');
        $this->assertEquals('lin_test_api_key_12345', $stored_api_key);
        
        // Fetch issues from Linear
        $fetch_result = $this->integrationsModule->fetchLinearIssues();
        
        $this->assertTrue($fetch_result['success']);
        $this->assertArrayHasKey('issues', $fetch_result);
        $this->assertCount(2, $fetch_result['issues']);
        
        // Verify issue data structure
        $issue = $fetch_result['issues'][0];
        $this->assertArrayHasKey('id', $issue);
        $this->assertArrayHasKey('title', $issue);
        $this->assertArrayHasKey('description', $issue);
        $this->assertArrayHasKey('state', $issue);
    }
    
    /**
     * Test issue created in Linear → plugin project created
     */
    public function testLinearIssueToPluginProject() {
        // Setup Linear connection
        $linear_config = [
            'enabled' => true,
            'api_key' => 'lin_test_api_key',
            'team_id' => 'team_789'
        ];
        
        $this->integrationsModule->connectLinear($linear_config);
        
        // Simulate webhook from Linear when issue is created
        $linear_webhook_data = [
            'action' => 'create',
            'type' => 'Issue',
            'data' => [
                'id' => 'issue_new_123',
                'title' => 'New Feature Request',
                'description' => 'Implement user authentication',
                'state' => 'todo',
                'assignee' => 'user_456',
                'team' => 'team_789',
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ]
        ];
        
        // Process Linear webhook
        $webhook_result = $this->integrationsModule->processLinearWebhook($linear_webhook_data);
        
        $this->assertTrue($webhook_result['success']);
        $this->assertEquals('created', $webhook_result['action']);
        $this->assertArrayHasKey('project_id', $webhook_result);
        
        // Verify plugin project was created
        $plugin_project = $this->projectsModule->getProject($webhook_result['project_id']);
        $this->assertNotNull($plugin_project);
        $this->assertEquals('New Feature Request', $plugin_project['name']);
        $this->assertEquals('linear_issue_new_123', $plugin_project['external_id']);
        $this->assertEquals('todo', $plugin_project['status']);
        
        // Verify sync mapping was created
        $sync_mapping = $this->stateManager->getSecure('sync_mappings', 'linear_issue_new_123');
        $this->assertEquals($webhook_result['project_id'], $sync_mapping['plugin_project_id']);
        $this->assertEquals('issue', $sync_mapping['type']);
    }
    
    /**
     * Test plugin update → Linear issue updated
     */
    public function testPluginUpdateToLinearIssue() {
        // Setup Linear connection and create initial project
        $linear_config = [
            'enabled' => true,
            'api_key' => 'lin_test_api_key',
            'team_id' => 'team_789'
        ];
        
        $this->integrationsModule->connectLinear($linear_config);
        
        // Create project in plugin first
        $project_data = [
            'name' => 'Test Project Update',
            'description' => 'Project to be updated',
            'status' => 'todo',
            'priority' => 'high',
            'external_sync' => true
        ];
        
        $project_result = $this->projectsModule->createProject($project_data);
        $this->assertTrue($project_result['success']);
        
        $project_id = $project_result['project_id'];
        
        // Create Linear issue for the project
        $create_issue_result = $this->integrationsModule->createLinearIssueFromProject($project_id);
        $this->assertTrue($create_issue_result['success']);
        $this->assertArrayHasKey('linear_issue_id', $create_issue_result);
        
        $linear_issue_id = $create_issue_result['linear_issue_id'];
        
        // Update project in plugin
        $update_data = [
            'name' => 'Updated Test Project',
            'status' => 'in_progress',
            'priority' => 'medium'
        ];
        
        $update_result = $this->projectsModule->updateProject($project_id, $update_data);
        $this->assertTrue($update_result['success']);
        
        // Sync update to Linear
        $sync_result = $this->integrationsModule->syncProjectToLinear($project_id);
        
        $this->assertTrue($sync_result['success']);
        $this->assertEquals('updated', $sync_result['action']);
        
        // Verify Linear issue was updated
        $linear_issue = $this->integrationsModule->getLinearIssue($linear_issue_id);
        $this->assertEquals('Updated Test Project', $linear_issue['title']);
        $this->assertEquals('in_progress', $linear_issue['state']);
    }
    
    /**
     * Test activity logging for all sync operations
     */
    public function testActivityLoggingAllSyncOps() {
        // Setup integrations
        $linear_config = [
            'enabled' => true,
            'api_key' => 'lin_test_api_key',
            'team_id' => 'team_789'
        ];
        
        $this->integrationsModule->connectLinear($linear_config);
        
        // Create project and sync to Linear
        $project_data = [
            'name' => 'Logged Test Project',
            'description' => 'Test project with logging',
            'external_sync' => true
        ];
        
        $project_result = $this->projectsModule->createProject($project_data);
        $project_id = $project_result['project_id'];
        
        // Sync to Linear
        $this->integrationsModule->createLinearIssueFromProject($project_id);
        
        // Update project and sync
        $this->projectsModule->updateProject($project_id, ['status' => 'in_progress']);
        $this->integrationsModule->syncProjectToLinear($project_id);
        
        // Fetch activity logs
        $activity_logs = $this->integrationsModule->getActivityLogs();
        
        $this->assertNotEmpty($activity_logs);
        
        // Verify specific operations were logged
        $project_creation_logs = array_filter($activity_logs, function($log) use ($project_id) {
            return $log['action'] === 'project_created' && $log['entity_id'] === $project_id;
        });
        $this->assertNotEmpty($project_creation_logs);
        
        $linear_sync_logs = array_filter($activity_logs, function($log) {
            return $log['service'] === 'linear' && $log['action'] === 'sync';
        });
        $this->assertNotEmpty($linear_sync_logs);
        
        // Verify log structure
        foreach ($activity_logs as $log) {
            $this->assertArrayHasKey('timestamp', $log);
            $this->assertArrayHasKey('action', $log);
            $this->assertArrayHasKey('service', $log);
            $this->assertArrayHasKey('entity_id', $log);
        }
    }
    
    /**
     * Test Notion database mapping → project creation
     */
    public function testNotionDBMapToProjectCreation() {
        // Configure Notion integration
        $notion_config = [
            'enabled' => true,
            'api_key' => 'secret_notion_key_123',
            'database_id' => 'db_123'
        ];
        
        $connect_result = $this->integrationsModule->connectNotion($notion_config);
        $this->assertTrue($connect_result['success']);
        
        // Fetch Notion database structure
        $db_result = $this->integrationsModule->fetchNotionDatabase();
        $this->assertTrue($db_result['success']);
        
        // Create property mapping
        $mapping_config = [
            'database_id' => 'db_123',
            'property_mapping' => [
                'Name' => 'name',
                'Status' => 'status',
                'Priority' => 'priority'
            ]
        ];
        
        $mapping_result = $this->integrationsModule->createNotionMapping($mapping_config);
        $this->assertTrue($mapping_result['success']);
        
        // Create Notion page (should trigger project creation)
        $notion_page_data = [
            'Name' => [['text' => ['content' => 'Notion Project']]],
            'Status' => [['name' => 'Todo']],
            'Priority' => [['name' => 'High']]
        ];
        
        $page_result = $this->integrationsModule->createNotionPage($notion_page_data);
        $this->assertTrue($page_result['success']);
        $this->assertArrayHasKey('page_id', $page_result);
        $this->assertArrayHasKey('project_id', $page_result);
        
        // Verify plugin project was created
        $plugin_project = $this->projectsModule->getProject($page_result['project_id']);
        $this->assertNotNull($plugin_project);
        $this->assertEquals('Notion Project', $plugin_project['name']);
        $this->assertEquals('todo', $plugin_project['status']);
        $this->assertEquals('high', $plugin_project['priority']);
        
        // Verify sync mapping
        $sync_mapping = $this->stateManager->getSecure('sync_mappings', $page_result['page_id']);
        $this->assertEquals($page_result['project_id'], $sync_mapping['plugin_project_id']);
        $this->assertEquals('page', $sync_mapping['type']);
    }
    
    /**
     * Test bidirectional sync conflict handling
     */
    public function testBidirectionalSyncConflictHandling() {
        // Setup Linear connection
        $linear_config = [
            'enabled' => true,
            'api_key' => 'lin_test_api_key',
            'team_id' => 'team_789'
        ];
        
        $this->integrationsModule->connectLinear($linear_config);
        
        // Create initial project
        $project_data = [
            'name' => 'Conflict Test Project',
            'description' => 'Project to test conflicts',
            'status' => 'todo',
            'external_sync' => true
        ];
        
        $project_result = $this->projectsModule->createProject($project_data);
        $project_id = $project_result['project_id'];
        
        // Create Linear issue
        $create_issue_result = $this->integrationsModule->createLinearIssueFromProject($project_id);
        $linear_issue_id = $create_issue_result['linear_issue_id'];
        
        // Simulate conflicting updates (both Linear and plugin updated independently)
        
        // Update project in plugin (timestamp: earlier)
        $plugin_update_data = [
            'name' => 'Plugin Updated Name',
            'status' => 'in_progress',
            'updated_at' => time() - 100 // Older timestamp
        ];
        
        // Update issue in Linear (timestamp: later)
        $linear_update_data = [
            'name' => 'Linear Updated Name',
            'description' => 'Updated from Linear',
            'state' => 'done',
            'updated_at' => time() // More recent timestamp
        ];
        
        // Apply Linear update first (more recent)
        $this->integrationsModule->updateLinearIssue($linear_issue_id, $linear_update_data);
        
        // Then apply plugin update (should detect conflict)
        $conflict_result = $this->projectsModule->updateProjectWithConflictDetection(
            $project_id, 
            $plugin_update_data, 
            $linear_issue_id
        );
        
        $this->assertTrue($conflict_result['success']);
        $this->assertEquals('conflict_resolved', $conflict_result['resolution']);
        $this->assertEquals('linear_wins', $conflict_result['resolution_strategy']);
        
        // Verify final state (Linear wins)
        $final_project = $this->projectsModule->getProject($project_id);
        $this->assertEquals('Linear Updated Name', $final_project['name']);
        $this->assertEquals('done', $final_project['status']);
        $this->assertEquals('Updated from Linear', $final_project['description']);
    }
    
    /**
     * Test sync conflict with manual resolution
     */
    public function testSyncConflictWithManualResolution() {
        // Setup Linear connection
        $linear_config = [
            'enabled' => true,
            'api_key' => 'lin_test_api_key',
            'team_id' => 'team_789'
        ];
        
        $this->integrationsModule->connectLinear($linear_config);
        
        // Create initial project
        $project_data = [
            'name' => 'Manual Conflict Test',
            'description' => 'Test manual resolution',
            'status' => 'todo',
            'external_sync' => true
        ];
        
        $project_result = $this->projectsModule->createProject($project_data);
        $project_id = $project_result['project_id'];
        
        // Create Linear issue
        $create_issue_result = $this->integrationsModule->createLinearIssueFromProject($project_id);
        $linear_issue_id = $create_issue_result['linear_issue_id'];
        
        // Create conflict
        $plugin_update = [
            'name' => 'Plugin Name',
            'status' => 'in_progress'
        ];
        
        $linear_update = [
            'title' => 'Linear Name',
            'state' => 'done'
        ];
        
        // Simulate conflict detection
        $conflict_data = [
            'plugin_data' => $plugin_update,
            'linear_data' => $linear_update,
            'conflict_fields' => ['name', 'status']
        ];
        
        $resolution_result = $this->integrationsModule->resolveSyncConflict(
            $project_id, 
            $linear_issue_id, 
            $conflict_data, 
            'manual_merge'
        );
        
        $this->assertTrue($resolution_result['success']);
        $this->assertEquals('manual_merge', $resolution_result['strategy']);
        
        // Verify merged result
        $resolved_project = $this->projectsModule->getProject($project_id);
        $this->assertEquals('Linear Name', $resolved_project['name']); // Linear wins on name
        $this->assertEquals('in_progress', $resolved_project['status']); // Plugin wins on status
        
        // Verify Linear issue was updated with merged data
        $linear_issue = $this->integrationsModule->getLinearIssue($linear_issue_id);
        $this->assertEquals('Linear Name', $linear_issue['title']);
        $this->assertEquals('in_progress', $linear_issue['state']);
    }
    
    /**
     * Test bulk sync operations
     */
    public function testBulkSyncOperations() {
        // Setup Linear connection
        $linear_config = [
            'enabled' => true,
            'api_key' => 'lin_test_api_key',
            'team_id' => 'team_789'
        ];
        
        $this->integrationsModule->connectLinear($linear_config);
        
        // Create multiple projects
        $projects = [];
        for ($i = 1; $i <= 5; $i++) {
            $project_data = [
                'name' => "Bulk Project {$i}",
                'description' => "Test project {$i}",
                'status' => 'todo',
                'external_sync' => true
            ];
            
            $result = $this->projectsModule->createProject($project_data);
            $this->assertTrue($result['success']);
            $projects[] = $result['project_id'];
        }
        
        // Bulk sync to Linear
        $bulk_sync_result = $this->integrationsModule->bulkSyncToLinear($projects);
        
        $this->assertTrue($bulk_sync_result['success']);
        $this->assertEquals(5, $bulk_sync_result['total_projects']);
        $this->assertEquals(5, $bulk_sync_result['successful_syncs']);
        $this->assertEquals(0, $bulk_sync_result['failed_syncs']);
        
        // Verify all projects have Linear issue IDs
        foreach ($projects as $project_id) {
            $project = $this->projectsModule->getProject($project_id);
            $this->assertArrayHasKey('linear_issue_id', $project);
            $this->assertNotEmpty($project['linear_issue_id']);
        }
    }
    
    /**
     * Test sync error handling and retry mechanism
     */
    public function testSyncErrorHandlingAndRetry() {
        // Setup Linear connection
        $linear_config = [
            'enabled' => true,
            'api_key' => 'lin_test_api_key',
            'team_id' => 'team_789'
        ];
        
        $this->integrationsModule->connectLinear($linear_config);
        
        // Create project
        $project_data = [
            'name' => 'Retry Test Project',
            'description' => 'Test retry mechanism',
            'status' => 'todo',
            'external_sync' => true
        ];
        
        $project_result = $this->projectsModule->createProject($project_data);
        $project_id = $project_result['project_id'];
        
        // Simulate failed sync
        $failed_sync = $this->integrationsModule->simulateFailedSync($project_id);
        $this->assertFalse($failed_sync['success']);
        $this->assertArrayHasKey('error', $failed_sync);
        
        // Retry sync
        $retry_result = $this->integrationsModule->retryFailedSync($project_id);
        
        $this->assertTrue($retry_result['success']);
        $this->assertEquals('completed', $retry_result['status']);
        
        // Verify project was synced successfully
        $project = $this->projectsModule->getProject($project_id);
        $this->assertArrayHasKey('linear_issue_id', $project);
    }
}