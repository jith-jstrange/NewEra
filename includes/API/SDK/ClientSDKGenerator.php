<?php
/**
 * Client SDK Generator
 *
 * Generates JavaScript/TypeScript client SDKs for the API
 *
 * @package Newera\API\SDK
 */

namespace Newera\API\SDK;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Client SDK Generator class
 */
class ClientSDKGenerator {
    /**
     * Generate JavaScript SDK
     *
     * @param array $options Generation options
     * @return string
     */
    public function generate_javascript_sdk($options = []) {
        $site_url = $options['site_url'] ?? get_site_url();
        $api_version = $options['api_version'] ?? 'v1';
        
        return <<<JS
/**
 * Newera API JavaScript Client SDK
 * Generated for API version {$api_version}
 */

class NeweraAPI {
    constructor(options = {}) {
        this.baseURL = options.baseURL || '{$site_url}';
        this.apiVersion = options.apiVersion || '{$api_version}';
        this.token = options.token || null;
        
        this.restBase = this.baseURL + '/wp-json/newera/' + this.apiVersion;
        this.graphqlEndpoint = this.baseURL + '/newera/graphql';
    }

    /**
     * Set authentication token
     */
    setToken(token) {
        this.token = token;
    }

    /**
     * Get authentication headers
     */
    getHeaders(includeAuth = true) {
        const headers = {
            'Content-Type': 'application/json'
        };

        if (includeAuth && this.token) {
            headers['Authorization'] = 'Bearer ' + this.token;
        }

        return headers;
    }

    /**
     * Make HTTP request
     */
    async request(endpoint, options = {}) {
        const url = endpoint.startsWith('http') ? endpoint : this.restBase + endpoint;
        
        const config = {
            method: options.method || 'GET',
            headers: this.getHeaders(options.includeAuth !== false)
        };

        if (options.body) {
            config.body = JSON.stringify(options.body);
        }

        if (options.query) {
            const params = new URLSearchParams(options.query);
            return url + '?' + params.toString();
        }

        const response = await fetch(url, config);
        
        if (!response.ok) {
            const error = await response.json().catch(() => ({ message: 'Request failed' }));
            throw new Error(error.message || 'Request failed');
        }

        return response.json();
    }

    /**
     * Make GraphQL request
     */
    async graphql(query, variables = {}) {
        const payload = {
            query: query,
            variables: variables
        };

        const response = await fetch(this.graphqlEndpoint, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({ message: 'GraphQL request failed' }));
            throw new Error(error.errors?.[0]?.message || 'GraphQL request failed');
        }

        return response.json();
    }

    // ========== CLIENTS ==========

    /**
     * Get clients list
     */
    async getClients(params = {}) {
        return this.request('/clients', { query: params });
    }

    /**
     * Get single client
     */
    async getClient(id) {
        return this.request(\`/clients/\${id}\`);
    }

    /**
     * Create client
     */
    async createClient(data) {
        return this.request('/clients', {
            method: 'POST',
            body: data
        });
    }

    /**
     * Update client
     */
    async updateClient(id, data) {
        return this.request(\`/clients/\${id}\`, {
            method: 'PUT',
            body: data
        });
    }

    /**
     * Delete client
     */
    async deleteClient(id) {
        return this.request(\`/clients/\${id}\`, {
            method: 'DELETE'
        });
    }

    // ========== PROJECTS ==========

    /**
     * Get projects list
     */
    async getProjects(params = {}) {
        return this.request('/projects', { query: params });
    }

    /**
     * Get single project
     */
    async getProject(id) {
        return this.request(\`/projects/\${id}\`);
    }

    /**
     * Create project
     */
    async createProject(data) {
        return this.request('/projects', {
            method: 'POST',
            body: data
        });
    }

    /**
     * Update project
     */
    async updateProject(id, data) {
        return this.request(\`/projects/\${id}\`, {
            method: 'PUT',
            body: data
        });
    }

    /**
     * Delete project
     */
    async deleteProject(id) {
        return this.request(\`/projects/\${id}\`, {
            method: 'DELETE'
        });
    }

    // ========== SUBSCRIPTIONS ==========

    /**
     * Get subscriptions list
     */
    async getSubscriptions(params = {}) {
        return this.request('/subscriptions', { query: params });
    }

    /**
     * Get single subscription
     */
    async getSubscription(id) {
        return this.request(\`/subscriptions/\${id}\`);
    }

    /**
     * Create subscription
     */
    async createSubscription(data) {
        return this.request('/subscriptions', {
            method: 'POST',
            body: data
        });
    }

    // ========== SETTINGS ==========

    /**
     * Get settings
     */
    async getSettings() {
        return this.request('/settings');
    }

    /**
     * Update settings
     */
    async updateSettings(data) {
        return this.request('/settings', {
            method: 'POST',
            body: data
        });
    }

    // ========== WEBHOOKS ==========

    /**
     * Get webhooks list
     */
    async getWebhooks(params = {}) {
        return this.request('/webhooks', { query: params });
    }

    /**
     * Get single webhook
     */
    async getWebhook(id) {
        return this.request(\`/webhooks/\${id}\`);
    }

    /**
     * Create webhook
     */
    async createWebhook(data) {
        return this.request('/webhooks', {
            method: 'POST',
            body: data
        });
    }

    /**
     * Update webhook
     */
    async updateWebhook(id, data) {
        return this.request(\`/webhooks/\${id}\`, {
            method: 'PUT',
            body: data
        });
    }

    /**
     * Delete webhook
     */
    async deleteWebhook(id) {
        return this.request(\`/webhooks/\${id}\`, {
            method: 'DELETE'
        });
    }

    // ========== ACTIVITY ==========

    /**
     * Get activity logs
     */
    async getActivity(params = {}) {
        return this.request('/activity', { query: params });
    }

    // ========== GRAPHQL QUERIES ==========

    /**
     * GraphQL: Get clients with connection pattern
     */
    async graphqlGetClients(params = {}) {
        const query = \`
            query GetClients(\$first: Int, \$after: String, \$filter: ClientFilter) {
                clients(first: \$first, after: \$after, filter: \$filter) {
                    edges {
                        cursor
                        node {
                            id
                            name
                            email
                            phone
                            company
                            status
                            notes
                            createdAt
                            updatedAt
                        }
                    }
                    nodes {
                        id
                        name
                        email
                        status
                    }
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                        startCursor
                        endCursor
                    }
                    totalCount
                }
            }
        \`;

        return this.graphql(query, params);
    }

    /**
     * GraphQL: Create client
     */
    async graphqlCreateClient(input) {
        const query = \`
            mutation CreateClient(\$input: CreateClientInput!) {
                createClient(input: \$input) {
                    client {
                        id
                        name
                        email
                        status
                    }
                    errors
                }
            }
        \`;

        return this.graphql(query, { input });
    }

    /**
     * GraphQL: Update client
     */
    async graphqlUpdateClient(input) {
        const query = \`
            mutation UpdateClient(\$input: UpdateClientInput!) {
                updateClient(input: \$input) {
                    client {
                        id
                        name
                        email
                        status
                    }
                    errors
                }
            }
        \`;

        return this.graphql(query, { input });
    }

    /**
     * GraphQL: Delete client
     */
    async graphqlDeleteClient(id) {
        const query = \`
            mutation DeleteClient(\$id: ID!) {
                deleteClient(id: \$id) {
                    deleted
                    errors
                }
            }
        \`;

        return this.graphql(query, { id });
    }

    /**
     * GraphQL: Get projects with connection pattern
     */
    async graphqlGetProjects(params = {}) {
        const query = \`
            query GetProjects(\$first: Int, \$after: String, \$filter: ProjectFilter) {
                projects(first: \$first, after: \$after, filter: \$filter) {
                    edges {
                        cursor
                        node {
                            id
                            name
                            description
                            clientId
                            status
                            startDate
                            endDate
                            budget
                            createdAt
                        }
                    }
                    nodes {
                        id
                        name
                        status
                    }
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                        startCursor
                        endCursor
                    }
                    totalCount
                }
            }
        \`;

        return this.graphql(query, params);
    }

    /**
     * GraphQL: Create project
     */
    async graphqlCreateProject(input) {
        const query = \`
            mutation CreateProject(\$input: CreateProjectInput!) {
                createProject(input: \$input) {
                    project {
                        id
                        name
                        description
                        status
                    }
                    errors
                }
            }
        \`;

        return this.graphql(query, { input });
    }

    /**
     * GraphQL: Get settings
     */
    async graphqlGetSettings() {
        const query = \`
            query GetSettings {
                settings {
                    apiEnabled
                    corsEnabled
                    rateLimitingEnabled
                    webhookDeliveryEnabled
                }
            }
        \`;

        return this.graphql(query);
    }

    /**
     * GraphQL: Update settings
     */
    async graphqlUpdateSettings(input) {
        const query = \`
            mutation UpdateSettings(\$input: UpdateSettingsInput!) {
                updateSettings(input: \$input) {
                    settings {
                        apiEnabled
                        corsEnabled
                        rateLimitingEnabled
                        webhookDeliveryEnabled
                    }
                    errors
                }
            }
        \`;

        return this.graphql(query, { input });
    }
}

// Export for different module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NeweraAPI;
} else if (typeof define === 'function' && define.amd) {
    define([], function() {
        return NeweraAPI;
    });
} else {
    window.NeweraAPI = NeweraAPI;
}
JS;
    }

    /**
     * Generate TypeScript definitions
     *
     * @param array $options Generation options
     * @return string
     */
    public function generate_typescript_definitions($options = []) {
        return <<<TS
/**
 * Newera API TypeScript Definitions
 * Generated for API version 1.0.0
 */

export interface Client {
    id: number;
    name: string;
    email: string;
    phone?: string;
    company?: string;
    status: 'active' | 'inactive' | 'prospect';
    notes?: string;
    created_at: string;
    updated_at: string;
}

export interface Project {
    id: number;
    name: string;
    description?: string;
    client_id: number;
    status: 'planning' | 'active' | 'paused' | 'completed' | 'cancelled';
    start_date?: string;
    end_date?: string;
    budget?: number;
    created_at: string;
    updated_at: string;
}

export interface Subscription {
    id: number;
    plan_name: string;
    amount: number;
    currency: string;
    billing_cycle: 'monthly' | 'yearly';
    status: 'active' | 'cancelled' | 'past_due';
    created_at: string;
}

export interface Settings {
    api_enabled: boolean;
    cors_enabled: boolean;
    rate_limiting_enabled: boolean;
    webhook_delivery_enabled: boolean;
}

export interface Webhook {
    id: number;
    url: string;
    events: string[];
    secret: string;
    active: boolean;
    created_at: string;
    updated_at: string;
}

export interface Activity {
    id: number;
    type: 'api_request' | 'webhook_delivery' | 'authentication' | 'error';
    description: string;
    timestamp: string;
    user_id: number;
}

export interface PaginationParams {
    page?: number;
    per_page?: number;
    orderby?: 'id' | 'name' | 'created_at' | 'updated_at';
    order?: 'asc' | 'desc';
}

export interface FilterParams {
    status?: string;
    client_id?: number;
    search?: string;
    type?: 'api_request' | 'webhook_delivery' | 'authentication' | 'error';
}

export interface APIResponse<T> {
    items: T[];
    total: number;
    page_count: number;
    current_page: number;
}

export interface APILogEntry {
    timestamp: number;
    method: string;
    endpoint: string;
    user_id?: number;
    ip: string;
    user_agent?: string;
    request_id: string;
    status_code?: number;
    execution_time?: number;
    response_size?: number;
}

export interface GraphQLResponse<T> {
    data?: T;
    errors?: GraphQLError[];
    extensions?: {
        executionTime: number;
    };
}

export interface GraphQLError {
    message: string;
    locations?: Array<{
        line: number;
        column: number;
    }>;
    path?: string[];
    extensions?: Record<string, any>;
}

// Connection types for GraphQL
export interface ClientConnection {
    edges: Array<{
        cursor: string;
        node: Client;
    }>;
    nodes: Client[];
    pageInfo: PageInfo;
    totalCount: number;
}

export interface ProjectConnection {
    edges: Array<{
        cursor: string;
        node: Project;
    }>;
    nodes: Project[];
    pageInfo: PageInfo;
    totalCount: number;
}

export interface SubscriptionConnection {
    edges: Array<{
        cursor: string;
        node: Subscription;
    }>;
    nodes: Subscription[];
    pageInfo: PageInfo;
    totalCount: number;
}

export interface WebhookConnection {
    edges: Array<{
        cursor: string;
        node: Webhook;
    }>;
    nodes: Webhook[];
    pageInfo: PageInfo;
    totalCount: number;
}

export interface ActivityConnection {
    edges: Array<{
        cursor: string;
        node: Activity;
    }>;
    nodes: Activity[];
    pageInfo: PageInfo;
    totalCount: number;
}

export interface PageInfo {
    hasNextPage: boolean;
    hasPreviousPage: boolean;
    startCursor?: string;
    endCursor?: string;
}

// Input types for mutations
export interface CreateClientInput {
    name: string;
    email: string;
    phone?: string;
    company?: string;
    status?: 'active' | 'inactive' | 'prospect';
    notes?: string;
}

export interface UpdateClientInput {
    id: number;
    name?: string;
    email?: string;
    phone?: string;
    company?: string;
    status?: 'active' | 'inactive' | 'prospect';
    notes?: string;
}

export interface CreateProjectInput {
    name: string;
    description?: string;
    clientId: number;
    status?: 'planning' | 'active' | 'paused' | 'completed' | 'cancelled';
    startDate?: string;
    endDate?: string;
    budget?: number;
}

export interface UpdateProjectInput {
    id: number;
    name?: string;
    description?: string;
    clientId?: number;
    status?: 'planning' | 'active' | 'paused' | 'completed' | 'cancelled';
    startDate?: string;
    endDate?: string;
    budget?: number;
}

export interface CreateSubscriptionInput {
    planName: string;
    amount: number;
    currency?: string;
    billingCycle: 'monthly' | 'yearly';
    status?: 'active' | 'cancelled' | 'past_due';
}

export interface UpdateSettingsInput {
    apiEnabled?: boolean;
    corsEnabled?: boolean;
    rateLimitingEnabled?: boolean;
    webhookDeliveryEnabled?: boolean;
}

export interface CreateWebhookInput {
    url: string;
    events: string[];
    secret?: string;
    active?: boolean;
}

export interface UpdateWebhookInput {
    id: number;
    url?: string;
    events?: string[];
    secret?: string;
    active?: boolean;
}

// Filter input types
export interface ClientFilter {
    status?: 'active' | 'inactive' | 'prospect';
    search?: string;
}

export interface ProjectFilter {
    status?: 'planning' | 'active' | 'paused' | 'completed' | 'cancelled';
    clientId?: number;
}

export interface ActivityFilter {
    type?: 'api_request' | 'webhook_delivery' | 'authentication' | 'error';
}

// Auth types
export interface AuthResponse {
    token: string;
    user: {
        id: number;
        username: string;
        email: string;
        roles: string[];
        capabilities: string[];
    };
    expires_in: number;
}

// Webhook delivery types
export interface WebhookDelivery {
    id: number;
    webhook_id: number;
    event: string;
    payload: WebhookPayload;
    status: 'pending' | 'success' | 'failed';
    attempt: number;
    next_attempt_at: string;
    delivered_at?: string;
    last_error?: string;
    created_at: string;
}

export interface WebhookPayload {
    event: string;
    timestamp: string;
    data: Record<string, any>;
    webhook_id: number;
    test?: boolean;
}

// Rate limiting types
export interface RateLimitInfo {
    allowed: boolean;
    remaining: number;
    limit: number;
    reset: number;
}

// Error types
export interface APIError {
    code: string;
    message: string;
    data?: Record<string, any>;
}

// Main API client interface
export interface NeweraAPIClient {
    // REST methods
    getClients(params?: PaginationParams & FilterParams): Promise<APIResponse<Client>>;
    getClient(id: number): Promise<Client>;
    createClient(data: CreateClientInput): Promise<Client>;
    updateClient(id: number, data: Partial<CreateClientInput>): Promise<Client>;
    deleteClient(id: number): Promise<void>;

    getProjects(params?: PaginationParams & FilterParams): Promise<APIResponse<Project>>;
    getProject(id: number): Promise<Project>;
    createProject(data: CreateProjectInput): Promise<Project>;
    updateProject(id: number, data: Partial<CreateProjectInput>): Promise<Project>;
    deleteProject(id: number): Promise<void>;

    getSubscriptions(params?: PaginationParams): Promise<APIResponse<Subscription>>;
    getSubscription(id: number): Promise<Subscription>;
    createSubscription(data: CreateSubscriptionInput): Promise<Subscription>;

    getSettings(): Promise<Settings>;
    updateSettings(data: Partial<UpdateSettingsInput>): Promise<Settings>;

    getWebhooks(params?: PaginationParams): Promise<APIResponse<Webhook>>;
    getWebhook(id: number): Promise<Webhook>;
    createWebhook(data: CreateWebhookInput): Promise<Webhook>;
    updateWebhook(id: number, data: Partial<CreateWebhookInput>): Promise<Webhook>;
    deleteWebhook(id: number): Promise<void>;

    getActivity(params?: PaginationParams & { type?: ActivityFilter['type'] }): Promise<APIResponse<Activity>>;

    // GraphQL methods
    graphqlGetClients(params?: { first?: number; after?: string; filter?: ClientFilter }): Promise<GraphQLResponse<{ clients: ClientConnection }>>;
    graphqlCreateClient(input: CreateClientInput): Promise<GraphQLResponse<{ createClient: { client: Client; errors: string[] } }>>;
    graphqlUpdateClient(input: UpdateClientInput): Promise<GraphQLResponse<{ updateClient: { client: Client; errors: string[] } }>>;
    graphqlDeleteClient(id: number): Promise<GraphQLResponse<{ deleteClient: { deleted: boolean; errors: string[] } }>>;

    graphqlGetProjects(params?: { first?: number; after?: string; filter?: ProjectFilter }): Promise<GraphQLResponse<{ projects: ProjectConnection }>>;
    graphqlCreateProject(input: CreateProjectInput): Promise<GraphQLResponse<{ createProject: { project: Project; errors: string[] } }>>;

    graphqlGetSettings(): Promise<GraphQLResponse<{ settings: Settings }>>;
    graphqlUpdateSettings(input: UpdateSettingsInput): Promise<GraphQLResponse<{ updateSettings: { settings: Settings; errors: string[] } }>>;

    // Auth methods
    authenticate(username: string, password: string): Promise<AuthResponse>;
    setToken(token: string): void;

    // Utility methods
    setBaseURL(url: string): void;
    setAPIVersion(version: string): void;
}

export default NeweraAPIClient;
TS;
    }

    /**
     * Generate React hooks
     *
     * @param array $options Generation options
     * @return string
     */
    public function generate_react_hooks($options = []) {
        return <<<TSX
/**
 * Newera API React Hooks
 * Generated for API version 1.0.0
 */

import { useState, useEffect, useCallback } from 'react';
import { NeweraAPIClient, Client, Project, Subscription, Settings, Webhook, Activity, APIResponse, GraphQLResponse } from './types';

export function useNeweraAPI(config: { baseURL?: string; token?: string } = {}) {
    const [client] = useState(() => new NeweraAPI(config));

    useEffect(() => {
        if (config.token) {
            client.setToken(config.token);
        }
    }, [config.token, client]);

    return client;
}

// Client hooks
export function useClients(params?: any) {
    const [data, setData] = useState<APIResponse<Client> | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    
    const client = useNeweraAPI();
    
    const fetchClients = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await client.getClients(params);
            setData(response);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch clients');
        } finally {
            setLoading(false);
        }
    }, [client, params]);

    useEffect(() => {
        fetchClients();
    }, [fetchClients]);

    return { data, loading, error, refetch: fetchClients };
}

export function useClient(id: number) {
    const [data, setData] = useState<Client | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    
    const client = useNeweraAPI();
    
    const fetchClient = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await client.getClient(id);
            setData(response);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch client');
        } finally {
            setLoading(false);
        }
    }, [client, id]);

    useEffect(() => {
        fetchClient();
    }, [fetchClient]);

    return { data, loading, error, refetch: fetchClient };
}

export function useCreateClient() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    
    const client = useNeweraAPI();
    
    const createClient = useCallback(async (data: any) => {
        try {
            setLoading(true);
            setError(null);
            const response = await client.createClient(data);
            return response;
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Failed to create client';
            setError(errorMessage);
            throw err;
        } finally {
            setLoading(false);
        }
    }, [client]);

    return { createClient, loading, error };
}

export function useUpdateClient() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    
    const client = useNeweraAPI();
    
    const updateClient = useCallback(async (id: number, data: any) => {
        try {
            setLoading(true);
            setError(null);
            const response = await client.updateClient(id, data);
            return response;
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Failed to update client';
            setError(errorMessage);
            throw err;
        } finally {
            setLoading(false);
        }
    }, [client]);

    return { updateClient, loading, error };
}

export function useDeleteClient() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    
    const client = useNeweraAPI();
    
    const deleteClient = useCallback(async (id: number) => {
        try {
            setLoading(true);
            setError(null);
            await client.deleteClient(id);
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Failed to delete client';
            setError(errorMessage);
            throw err;
        } finally {
            setLoading(false);
        }
    }, [client]);

    return { deleteClient, loading, error };
}

// Project hooks
export function useProjects(params?: any) {
    const [data, setData] = useState<APIResponse<Project> | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    
    const client = useNeweraAPI();
    
    const fetchProjects = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await client.getProjects(params);
            setData(response);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch projects');
        } finally {
            setLoading(false);
        }
    }, [client, params]);

    useEffect(() => {
        fetchProjects();
    }, [fetchProjects]);

    return { data, loading, error, refetch: fetchProjects };
}

// Settings hooks
export function useSettings() {
    const [data, setData] = useState<Settings | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    
    const client = useNeweraAPI();
    
    const fetchSettings = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await client.getSettings();
            setData(response);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch settings');
        } finally {
            setLoading(false);
        }
    }, [client]);

    useEffect(() => {
        fetchSettings();
    }, [fetchSettings]);

    const updateSettings = useCallback(async (data: any) => {
        try {
            setError(null);
            const response = await client.updateSettings(data);
            setData(response);
            return response;
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Failed to update settings';
            setError(errorMessage);
            throw err;
        }
    }, [client]);

    return { data, loading, error, updateSettings, refetch: fetchSettings };
}

// Activity hooks
export function useActivity(params?: any) {
    const [data, setData] = useState<APIResponse<Activity> | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    
    const client = useNeweraAPI();
    
    const fetchActivity = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await client.getActivity(params);
            setData(response);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch activity');
        } finally {
            setLoading(false);
        }
    }, [client, params]);

    useEffect(() => {
        fetchActivity();
    }, [fetchActivity]);

    return { data, loading, error, refetch: fetchActivity };
}

// GraphQL hooks
export function useGraphQLClients(params?: any) {
    const [data, setData] = useState<GraphQLResponse<any> | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    
    const client = useNeweraAPI();
    
    const fetchData = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await client.graphqlGetClients(params);
            setData(response);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch clients');
        } finally {
            setLoading(false);
        }
    }, [client, params]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    return { data, loading, error, refetch: fetchData };
}

export function useGraphQLCreateClient() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    
    const client = useNeweraAPI();
    
    const createClient = useCallback(async (input: any) => {
        try {
            setLoading(true);
            setError(null);
            const response = await client.graphqlCreateClient(input);
            return response;
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Failed to create client';
            setError(errorMessage);
            throw err;
        } finally {
            setLoading(false);
        }
    }, [client]);

    return { createClient, loading, error };
}
TSX;
    }

    /**
     * Generate complete SDK package
     *
     * @param array $options Generation options
     * @return array
     */
    public function generate_complete_sdk($options = []) {
        return [
            'javascript' => $this->generate_javascript_sdk($options),
            'typescript' => $this->generate_typescript_definitions($options),
            'react_hooks' => $this->generate_react_hooks($options)
        ];
    }

    /**
     * Save SDK files to directory
     *
     * @param string $directory Target directory
     * @param array $options Generation options
     * @return bool
     */
    public function save_sdk_to_directory($directory, $options = []) {
        if (!is_dir($directory)) {
            wp_mkdir_p($directory);
        }

        $sdk_files = $this->generate_complete_sdk($options);

        // Save JavaScript SDK
        file_put_contents($directory . '/newera-api.js', $sdk_files['javascript']);

        // Save TypeScript definitions
        file_put_contents($directory . '/types.ts', $sdk_files['typescript']);

        // Save React hooks
        file_put_contents($directory . '/hooks.tsx', $sdk_files['react_hooks']);

        // Save package.json
        $package_json = json_encode([
            'name' => 'newera-api-client',
            'version' => '1.0.0',
            'description' => 'JavaScript client for Newera API',
            'main' => 'newera-api.js',
            'types' => 'types.ts',
            'scripts' => [
                'test' => 'jest'
            ],
            'keywords' => ['api', 'client', 'wordpress', 'headless'],
            'author' => 'Newera',
            'license' => 'MIT'
        ], JSON_PRETTY_PRINT);
        file_put_contents($directory . '/package.json', $package_json);

        // Save README
        $readme = $this->generate_sdk_readme();
        file_put_contents($directory . '/README.md', $readme);

        return true;
    }

    /**
     * Generate SDK README
     *
     * @return string
     */
    private function generate_sdk_readme() {
        return <<<MD
# Newera API JavaScript Client

JavaScript/TypeScript client library for the Newera WordPress API.

## Installation

```bash
npm install newera-api-client
```

## Usage

### JavaScript

```javascript
import NeweraAPI from 'newera-api-client';

// Initialize the client
const api = new NeweraAPI({
    baseURL: 'https://your-site.com',
    apiVersion: 'v1',
    token: 'your-jwt-token'
});

// Get clients
const clients = await api.getClients();
console.log(clients);

// Create a client
const newClient = await api.createClient({
    name: 'Acme Corp',
    email: 'contact@acme.com',
    status: 'prospect'
});
```

### TypeScript

```typescript
import { NeweraAPIClient, Client, CreateClientInput } from 'newera-api-client';

const api: NeweraAPIClient = new NeweraAPI({
    baseURL: 'https://your-site.com',
    token: 'your-jwt-token'
});

const clients: APIResponse<Client> = await api.getClients();
const client: Client = await api.createClient({
    name: 'Acme Corp',
    email: 'contact@acme.com'
} as CreateClientInput);
```

### React Hooks

```tsx
import React from 'react';
import { useClients, useCreateClient } from 'newera-api-client';

function ClientsList() {
    const { data, loading, error } = useClients({ per_page: 10 });
    const { createClient, loading: creating } = useCreateClient();

    const handleCreate = async () => {
        try {
            await createClient({
                name: 'New Client',
                email: 'new@client.com'
            });
        } catch (error) {
            console.error('Failed to create client:', error);
        }
    };

    if (loading) return <div>Loading...</div>;
    if (error) return <div>Error: {error}</div>;

    return (
        <div>
            <button onClick={handleCreate} disabled={creating}>
                Create Client
            </button>
            <ul>
                {data?.items.map(client => (
                    <li key={client.id}>{client.name} - {client.email}</li>
                ))}
            </ul>
        </div>
    );
}
```

## GraphQL Usage

```javascript
// Using GraphQL
const clients = await api.graphqlGetClients({
    first: 10,
    filter: { status: 'active' }
});

const result = await api.graphqlCreateClient({
    name: 'GraphQL Client',
    email: 'graphql@client.com'
});
```

## API Methods

### Clients
- `getClients(params)` - Get paginated clients list
- `getClient(id)` - Get single client
- `createClient(data)` - Create new client
- `updateClient(id, data)` - Update client
- `deleteClient(id)` - Delete client

### Projects
- `getProjects(params)` - Get paginated projects list
- `getProject(id)` - Get single project
- `createProject(data)` - Create new project
- `updateProject(id, data)` - Update project
- `deleteProject(id)` - Delete project

### Subscriptions
- `getSubscriptions(params)` - Get paginated subscriptions list
- `getSubscription(id)` - Get single subscription
- `createSubscription(data)` - Create new subscription

### Settings
- `getSettings()` - Get API settings
- `updateSettings(data)` - Update API settings

### Webhooks
- `getWebhooks(params)` - Get paginated webhooks list
- `getWebhook(id)` - Get single webhook
- `createWebhook(data)` - Create new webhook
- `updateWebhook(id, data)` - Update webhook
- `deleteWebhook(id)` - Delete webhook

### Activity
- `getActivity(params)` - Get activity logs

## Authentication

All API requests require authentication. You can set the token when initializing the client or using the `setToken` method:

```javascript
api.setToken('your-jwt-token');
```

## Error Handling

The client throws errors for failed requests:

```javascript
try {
    const clients = await api.getClients();
} catch (error) {
    if (error.message.includes('401')) {
        console.log('Authentication required');
    } else if (error.message.includes('404')) {
        console.log('Resource not found');
    } else {
        console.log('API error:', error.message);
    }
}
```

## Rate Limiting

The API implements rate limiting. The client includes rate limit information in responses (for REST endpoints) and you should handle 429 status codes appropriately.

## Webhooks

Configure webhooks to receive real-time notifications about API events:

```javascript
const webhook = await api.createWebhook({
    url: 'https://your-app.com/webhook',
    events: ['client.created', 'client.updated'],
    active: true
});
```

## Support

For API support and documentation, visit your site's `/wp-json/newera/v1/doc` endpoint.

## License

MIT
MD;
    }
}