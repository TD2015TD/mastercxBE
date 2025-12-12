<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Cache;

/**
 * Service class for interacting with the Mitel MiContact Center Web SDK API.
 */
class MitelService
{
    private string $baseUrl;
    private string $authUrl;
    private string $clientId;
    private string $clientSecret;
    private int $timeout;
    private ?string $accessToken = null;
    private string $cacheKey = 'mitel_access_token';
    private string $cacheExpiryKey = 'mitel_access_token_expiry';

    public function __construct()
    {
        // NOTE: Configuration values would typically be loaded from Laravel's config/services.php
        $this->baseUrl = config('services.mitel.base_url', 'https://your-micc-server/miccsdk');
        $this->authUrl = config('services.mitel.auth_url', 'https://your-micc-server/miccsdk/connect/token');
        $this->clientId = config('services.mitel.client_id', 'your-client-id');
        $this->clientSecret = config('services.mitel.client_secret', 'your-client-secret');
        $this->timeout = config('services.mitel.timeout', 30);

        $this->log('INFO', 'MitelService initialized', [
            'base_url' => $this->baseUrl,
            'auth_url' => $this->authUrl,
            'client_id' => $this->clientId,
            'timeout' => $this->timeout . 's'
        ]);
    }

    /**
     * Log message with Log4j-style pattern
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = now()->format('Y-m-d H:i:s.v');
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2]['function'] ?? 'unknown';
        $class = $trace[2]['class'] ?? 'MitelService';
        $logMessage = sprintf("[%s] %s %s.%s - %s", $timestamp, $level, $class, $caller, $message);

        // Map log levels to Laravel's Log facade
        switch ($level) {
            case 'ERROR':
                Log::error($logMessage, $context);
                break;
            case 'WARN':
                Log::warning($logMessage, $context);
                break;
            case 'DEBUG':
                Log::debug($logMessage, $context);
                break;
            case 'INFO':
            default:
                Log::info($logMessage, $context);
                break;
        }
    }

    /**
     * Handle non-successful HTTP responses.
     */
    private function handleResponse($response, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        if (!$response->successful()) {
            $this->log('ERROR', 'Mitel API request failed', [
                'status_code' => $response->status(),
                'reason_phrase' => $response->reason(),
                'response_body' => $response->body(),
                'duration_ms' => $duration
            ]);
            throw new Exception('Mitel API request failed: ' . $response->body());
        }

        $this->log('DEBUG', 'Mitel API request successful', [
            'status_code' => $response->status(),
            'duration_ms' => $duration
        ]);
    }

    /**
     * Authenticate with Mitel and cache the access token.
     */
    public function authenticate(?string $impersonate = null): string
    {
        $startTime = microtime(true);
        $this->log('INFO', 'Attempting to authenticate with Mitel', ['impersonate' => $impersonate]);

        $headers = [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        if ($impersonate) {
            $headers['Impersonate'] = $impersonate;
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders($headers)
            ->asForm()
            ->post($this->authUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $this->handleResponse($response, $startTime);

        $data = $response->json();
        $accessToken = $data['access_token'];

        $this->log('INFO', 'Authentication successful', [
            'impersonate' => $impersonate,
            'expires_in' => $data['expires_in'] ?? 'unknown',
        ]);

        return $accessToken;
    }

    /**
     * Get or refresh the access token.
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken && Cache::has($this->cacheKey)) {
            return $this->accessToken;
        }

        if (Cache::has($this->cacheKey)) {
            $this->accessToken = Cache::get($this->cacheKey);
            return $this->accessToken;
        }

        // If no token or token expired, authenticate
        return $this->authenticate();
    }

    /**
     * Make a request to the Mitel API.
     */
    private function makeRequest(string $method, string $uri, array $body = [], array $headers = [], bool $isForm = false): array
    {
        $accessToken = $this->getAccessToken();
        $startTime = microtime(true);

        $request = Http::timeout($this->timeout)
            ->withToken($accessToken)
            ->withHeaders(array_merge([
                'Accept' => 'application/json',
            ], $headers));

        $this->log('DEBUG', "Making API request", ['method' => $method, 'uri' => $uri, 'body_keys' => array_keys($body)]);

        if ($isForm) {
            $request->asForm();
        } else {
            // Default to JSON body for non-GET/non-FORM requests
            $request->asJson();
        }

        switch (strtoupper($method)) {
            case 'GET':
                $response = $request->get($this->baseUrl . $uri, $body);
                break;
            case 'POST':
                $response = $request->post($this->baseUrl . $uri, $body);
                break;
            case 'PUT':
                $response = $request->put($this->baseUrl . $uri, $body);
                break;
            case 'DELETE':
                $response = $request->delete($this->baseUrl . $uri, $body);
                break;
            default:
                throw new Exception("Unsupported HTTP method: $method");
        }

        $this->handleResponse($response, $startTime);

        // Handle different response types
        if ($response->header('Content-Type') && str_contains($response->header('Content-Type'), 'application/json')) {
            return $response->json();
        }

        return []; // Empty success response (e.g., 204 No Content, or 200 with no JSON body)
    }

    // --- EMPLOYEE API FUNCTIONS ---

    /**
     * OData: Get all employees.
     * Endpoint: /api/v1/employees
     */
    public function getEmployees(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving all employees', ['odata_params' => $odataParams]);
        return $this->makeRequest('GET', '/api/v1/employees', $odataParams);
    }

    /**
     * OData: Get an employee by ID, username, or 'me'.
     * Endpoint: /api/v1/employees/{id}
     */
    public function getEmployeeById(string $id, array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving employee details', ['employee_id' => $id, 'odata_params' => $odataParams]);
        return $this->makeRequest('GET', "/api/v1/employees/{$id}", $odataParams);
    }
    
    /**
     * Get employee statistics.
     * Endpoint: /api/v1/employees/{id}/stats
     */
    public function getEmployeeStats(string $employeeId): array
    {
        $this->log('INFO', 'Retrieving employee statistics', ['employee_id' => $employeeId]);
        return $this->makeRequest('GET', "/api/v1/employees/{$employeeId}/stats");
    }

    /**
     * Update the state of a list of employees.
     * Payload: array of PresenceStateUpdateModel {id, state, reason}
     * Endpoint: /api/v1/employees/state
     */
    public function putEmployeeState(array $updates): array
    {
        $employeeIds = array_column($updates, 'id');
        $this->log('INFO', 'Updating employee state (bulk)', ['employee_ids' => $employeeIds, 'updates_count' => count($updates)]);
        
        // The API expects a list of PresenceStateUpdateModel objects in the body
        return $this->makeRequest('PUT', '/api/v1/employees/state', $updates);
    }
    
    /**
     * OData: Get agent groups for an employee
     * Endpoint: /api/v1/employees/{id}/agentgroups
     */
    public function getEmployeeAgentGroups(string $employeeId, array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving employee agent groups', ['employee_id' => $employeeId, 'odata_params' => $odataParams]);
        return $this->makeRequest('GET', "/api/v1/employees/{$employeeId}/agentgroups", $odataParams);
    }

    /**
     * Get the state of an employee by ID.
     */
    public function getEmployeeStateById(string $id): array
    {
        $this->log('INFO', 'Fetching employee state', ['id' => $id]);

        try {
            $response = $this->makeRequest('GET', "/api/v1/employees/{$id}/state");
            $this->log('INFO', 'Employee state fetched successfully', ['id' => $id, 'state' => $response]);
            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to fetch employee state', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    // --- REASON CODE API FUNCTIONS ---

    /**
     * OData: Get all reason codes (Busy, DND, etc.).
     * Endpoint: /api/v1/reasoncodes
     */
    public function getReasonCodes(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving reason codes', ['odata_params' => $odataParams]);
        return $this->makeRequest('GET', '/api/v1/reasoncodes', $odataParams);
    }

    /**
     * Get a single reason code.
     * Endpoint: /api/v1/reasoncodes/{id}
     */
    public function getReasonCodeById(string $id): array
    {
        $this->log('INFO', 'Retrieving single reason code', ['reason_code_id' => $id]);
        return $this->makeRequest('GET', "/api/v1/reasoncodes/{$id}");
    }

    // --- CASES API FUNCTIONS ---

    /**
     * OData: Get all cases.
     * Endpoint: /api/v1/cases
     */
    public function getCases(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving cases', ['odata_params' => $odataParams]);
        return $this->makeRequest('GET', '/api/v1/cases', $odataParams);
    }

    /**
     * Get a single case.
     * Endpoint: /api/v1/cases/{id}/{monthYearCreated}
     */
    public function getCaseById(string $id, string $monthYearCreated): array
    {
        $this->log('INFO', 'Retrieving single case', ['case_id' => $id, 'month_year' => $monthYearCreated]);
        return $this->makeRequest('GET', "/api/v1/cases/{$id}/{$monthYearCreated}");
    }

    // --- CHAT API FUNCTIONS ---

    /**
     * Get a list of Personal Reply Template folders.
     * Endpoint: /api/v1/chat/replytemplatefolders
     */
    public function getChatReplyTemplateFolders(): array
    {
        $this->log('INFO', 'Retrieving chat reply template folders');
        return $this->makeRequest('GET', '/api/v1/chat/replytemplatefolders');
    }

    /**
     * Get a list of Personal Reply Templates.
     * Endpoint: /api/v1/chat/replytemplates
     */
    public function getChatReplyTemplates(): array
    {
        $this->log('INFO', 'Retrieving chat reply templates');
        return $this->makeRequest('GET', '/api/v1/chat/replytemplates');
    }

    /**
     * Get Cloudlink chat transcript.
     * Endpoint: /api/v1/chat/{interactionId}/transcript/{employeeId}
     */
    public function getCloudlinkChatTranscript(string $interactionId, string $employeeId): array
    {
        $this->log('INFO', 'Retrieving chat transcript', ['interaction_id' => $interactionId, 'employee_id' => $employeeId]);
        return $this->makeRequest('GET', "/api/v1/chat/{$interactionId}/transcript/{$employeeId}");
    }

    // --- DNIS API FUNCTIONS ---

    /**
     * OData: Get DNIS numbers.
     * Endpoint: /api/v1/dnis
     */
    public function getDnis(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving DNIS numbers', ['odata_params' => $odataParams]);
        return $this->makeRequest('GET', '/api/v1/dnis', $odataParams);
    }

    /**
     * Get a single DNIS number.
     * Endpoint: /api/v1/dnis/{id}
     */
    public function getDnisById(string $id): array
    {
        $this->log('INFO', 'Retrieving single DNIS number', ['dnis_id' => $id]);
        return $this->makeRequest('GET', "/api/v1/dnis/{$id}");
    }

    // --- EMAIL API FUNCTIONS ---

    /**
     * Get a reusable email for the given 'to' address.
     * Endpoint: /api/v1/email/reusablecase
     */
    public function getEmailReusableCase(string $to): array
    {
        $this->log('INFO', 'Retrieving reusable email case', ['to' => $to]);
        return $this->makeRequest('GET', '/api/v1/email/reusablecase', ['to' => $to]);
    }

    /**
     * Get an email conversation.
     * Endpoint: /api/v1/email/{conversationId}/{getEmailType}/{monthYearCreated}
     */
    public function getEmail(string $conversationId, string $getEmailType, string $monthYearCreated): array
    {
        $this->log('INFO', 'Retrieving email conversation', ['id' => $conversationId, 'type' => $getEmailType, 'month_year' => $monthYearCreated]);
        return $this->makeRequest('GET', "/api/v1/email/{$conversationId}/{$getEmailType}/{$monthYearCreated}");
    }

    // --- GLOBAL REPLY TEMPLATE API FUNCTIONS ---

    /**
     * OData: Get global reply templates.
     * Endpoint: /api/v1/globalreplytemplate
     */
    public function getGlobalReplyTemplates(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving global reply templates', ['odata_params' => $odataParams]);
        return $this->makeRequest('GET', '/api/v1/globalreplytemplate', $odataParams);
    }

    /**
     * Create a new Global Reply Template.
     * Endpoint: /api/v1/globalreplytemplate
     */
    public function createGlobalReplyTemplate(array $model): array
    {
        $this->log('INFO', 'Creating global reply template', ['template_name' => $model['name'] ?? 'N/A']);
        return $this->makeRequest('POST', '/api/v1/globalreplytemplate', $model);
    }

    /**
     * Get a single global reply template.
     * Endpoint: /api/v1/globalreplytemplate/{id}
     */
    public function getGlobalReplyTemplateById(string $id): array
    {
        $this->log('INFO', 'Retrieving single global reply template', ['template_id' => $id]);
        return $this->makeRequest('GET', "/api/v1/globalreplytemplate/{$id}");
    }

    /**
     * Update a Global Reply Template.
     * Endpoint: /api/v1/globalreplytemplate/{id}
     */
    public function updateGlobalReplyTemplate(string $id, array $model): array
    {
        $this->log('INFO', 'Updating global reply template', ['template_id' => $id]);
        return $this->makeRequest('PUT', "/api/v1/globalreplytemplate/{$id}", $model);
    }

    /**
     * Delete a Global Reply Template.
     * Endpoint: /api/v1/globalreplytemplate/{id}
     */
    public function deleteGlobalReplyTemplate(string $id): array
    {
        $this->log('INFO', 'Deleting global reply template', ['template_id' => $id]);
        return $this->makeRequest('DELETE', "/api/v1/globalreplytemplate/{$id}");
    }
    
    // --- WARNING REASON API FUNCTIONS ---

    /**
     * OData: Get warning reasons.
     * Endpoint: /api/v1/warningreasons
     */
    public function getWarningReasons(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving warning reasons', ['odata_params' => $odataParams]);
        return $this->makeRequest('GET', '/api/v1/warningreasons', $odataParams);
    }

    /**
     * Get a warning reason.
     * Endpoint: /api/v1/warningreasons/{id}
     */
    public function getWarningReasonById(string $id): array
    {
        $this->log('INFO', 'Retrieving single warning reason', ['reason_id' => $id]);
        return $this->makeRequest('GET', "/api/v1/warningreasons/{$id}");
    }

    // --- EXTENSION API FUNCTIONS ---

    /**
     * OData: Get all extensions.
     * Endpoint: /api/v1/extensions
     */
    public function getExtensions(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving extensions', ['odata_params' => $odataParams]);
        return $this->makeRequest('GET', '/api/v1/extensions', $odataParams);
    }

    /**
     * Get an extension.
     * Endpoint: /api/v1/extensions/{id}
     */
    public function getExtensionById(string $id, array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving single extension', ['extension_id' => $id, 'odata_params' => $odataParams]);
        return $this->makeRequest('GET', "/api/v1/extensions/{$id}", $odataParams);
    }

    // --- AGENT GROUP API FUNCTIONS ---

    /**
     * OData: Get all agent groups.
     * Endpoint: /api/v1/agents/groups
     */
    public function getAgentGroups(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving agent groups', ['odata_params' => $odataParams]);
        return $this->makeRequest('GET', '/api/v1/agents/groups', $odataParams);
    }

    /**
     * OData: Get an agent group.
     * Endpoint: /api/v1/agents/groups/{id}
     */
    public function getAgentGroupById(string $id): array
    {
        $this->log('INFO', 'Retrieving single agent group', ['group_id' => $id]);
        return $this->makeRequest('GET', "/api/v1/agents/groups/{$id}");
    }

    /**
     * Get all agent states for an agent group.
     * Endpoint: /api/v1/agents/groups/{id}/states
     */
    public function getAgentGroupStates(string $id): array
    {
        $this->log('INFO', 'Retrieving agent states for group', ['group_id' => $id]);
        return $this->makeRequest('GET', "/api/v1/agents/groups/{$id}/states");
    }
    
    // --- QUEUE API FUNCTIONS ---

    /**
     * OData: Get all queues.
     * Endpoint: /api/v1/queues/groups
     */
    public function getQueueGroups(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving queue groups', ['odata_params' => $odataParams]);
        return $this->makeRequest('GET', '/api/v1/queues/groups', $odataParams);
    }

    /**
     * Get a queue group state (Queue Now).
     * Endpoint: /api/v1/queues/groups/{id}/state
     */
    public function getQueueGroupStateById(string $id): array
    {
        $this->log('INFO', 'Retrieving queue group state', ['queue_id' => $id]);
        return $this->makeRequest('GET', "/api/v1/queues/groups/{$id}/state");
    }

    // --- IMPERSONATION FUNCTION ---

    /**
     * Impersonate an employee login.
     * Endpoint: /api/v1/impersonate/login
     */
    public function impersonateLogin(string $username): array
    {
        $this->log('INFO', 'Attempting impersonate login', ['username' => $username]);

        $response = Http::withHeaders([
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Impersonate' => $username,
        ])->asForm()->post($this->authUrl, [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        $this->handleResponse($response, microtime(true));

        $data = $response->json();
        $this->log('INFO', 'Impersonate login successful', ['username' => $username, 'expires_in' => $data['expires_in']]);

        return $data;
    }

    /**
     * Update the state of an employee by ID.
     *
     * @param string $id Employee ID, username, or 'me'.
     * @param array $stateData The state data to update (e.g., state, reason).
     * @return array The response from the Mitel API.
     * @throws Exception If the API request fails.
     */
    public function putEmployeeStateById(string $id, array $stateData): array
    {
        $this->log('INFO', 'payload', $stateData);
        $this->log('INFO', 'Updating employee state', ['id' => $id, 'state_data' => $stateData]);

        try {
            $response = $this->makeRequest('PUT', "/api/v1/employees/{$id}/state", $stateData);
            $this->log('INFO', 'Employee state updated successfully', ['id' => $id, 'response' => $response]);
            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to update employee state', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}