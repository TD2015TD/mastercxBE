<?php

namespace App\Services;

use App\Models\MitelConfiguration;
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
        $mitelBaseUrl = rtrim(MitelConfiguration::getValue('MITEL_BASE_URL', 'https://micc.telediant.com.my'), '/');

        $this->baseUrl = $mitelBaseUrl . '/miccsdk';
        $this->authUrl = $mitelBaseUrl . '/AuthorizationServer/Token';
        $this->clientId = MitelConfiguration::getValue('MITEL_CLIENT_ID', 'CXCRM');
        $this->clientSecret = MitelConfiguration::getValue('MITEL_CLIENT_SECRET', '');
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

    // ========================================================================
    // CONVERSATION/CALL TRANSFER METHODS
    // ========================================================================

    /**
     * Perform a blind transfer of a conversation
     *
     * @param string $employeeId Employee ID or 'me'
     * @param string $conversationId Conversation/Call ID
     * @param string $destination Transfer destination (extension or phone number)
     * @return array The response from the Mitel API
     * @throws Exception If the API request fails
     */
    public function blindTransfer(string $employeeId, string $conversationId, string $destination): array
    {
        $this->log('INFO', 'Performing blind transfer', [
            'employee_id' => $employeeId,
            'conversation_id' => $conversationId,
            'destination' => $destination
        ]);

        try {
            $payload = [
                'conversationAction' => 'BlindTransfer',
                'conversationId' => $conversationId,
                'destination' => $destination
            ];

            $response = $this->makeRequest('PUT', "/api/v1/employees/{$employeeId}/conversations/{$conversationId}", $payload);
            $this->log('INFO', 'Blind transfer successful', ['conversation_id' => $conversationId, 'destination' => $destination]);
            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Blind transfer failed', [
                'conversation_id' => $conversationId,
                'destination' => $destination,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Initiate a consultation transfer (create consultation call)
     *
     * @param string $employeeId Employee ID or 'me'
     * @param string $conversationId Original conversation/Call ID
     * @param string $destination Transfer destination (extension or phone number)
     * @return array The response from the Mitel API containing consultation conversation ID
     * @throws Exception If the API request fails
     */
    public function initiateConsultTransfer(string $employeeId, string $conversationId, string $destination): array
    {
        $this->log('INFO', 'Initiating consultation transfer', [
            'employee_id' => $employeeId,
            'conversation_id' => $conversationId,
            'destination' => $destination
        ]);

        try {
            $payload = [
                'conversationAction' => 'InitiateConsult',
                'conversationId' => $conversationId,
                'destination' => $destination
            ];

            $response = $this->makeRequest('PUT', "/api/v1/employees/{$employeeId}/conversations/{$conversationId}", $payload);
            $this->log('INFO', 'Consultation transfer initiated', ['conversation_id' => $conversationId, 'response' => $response]);
            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to initiate consultation transfer', [
                'conversation_id' => $conversationId,
                'destination' => $destination,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Complete a consultation transfer
     *
     * @param string $employeeId Employee ID or 'me'
     * @param string $conversationId Original conversation/Call ID
     * @return array The response from the Mitel API
     * @throws Exception If the API request fails
     */
    public function completeConsultTransfer(string $employeeId, string $conversationId): array
    {
        $this->log('INFO', 'Completing consultation transfer', [
            'employee_id' => $employeeId,
            'conversation_id' => $conversationId
        ]);

        try {
            $payload = [
                'conversationAction' => 'CompleteTransfer',
                'conversationId' => $conversationId
            ];

            $response = $this->makeRequest('PUT', "/api/v1/employees/{$employeeId}/conversations/{$conversationId}", $payload);
            $this->log('INFO', 'Consultation transfer completed', ['conversation_id' => $conversationId]);
            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to complete consultation transfer', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Cancel a consultation transfer
     *
     * @param string $employeeId Employee ID or 'me'
     * @param string $conversationId Consultation conversation/Call ID to cancel
     * @return array The response from the Mitel API
     * @throws Exception If the API request fails
     */
    public function cancelConsultTransfer(string $employeeId, string $conversationId): array
    {
        $this->log('INFO', 'Canceling consultation transfer', [
            'employee_id' => $employeeId,
            'conversation_id' => $conversationId
        ]);

        try {
            $payload = [
                'conversationAction' => 'CancelConsult',
                'conversationId' => $conversationId
            ];

            $response = $this->makeRequest('PUT', "/api/v1/employees/{$employeeId}/conversations/{$conversationId}", $payload);
            $this->log('INFO', 'Consultation transfer canceled', ['conversation_id' => $conversationId]);
            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to cancel consultation transfer', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get agent group presence information
     * Returns agent groups with their presence states (Voice, OpenMedia, etc.)
     *
     * @param string $employeeId
     * @return array
     * @throws Exception
     */
    public function getAgentGroupPresence(string $employeeId): array
    {
        try {
            $this->log('INFO', 'Fetching agent group presence', [
                'employee_id' => $employeeId
            ]);

            $response = $this->makeRequest('GET', "/api/v1/employees/{$employeeId}/agentgrouppresence");

            $this->log('INFO', 'Agent group presence retrieved successfully', [
                'employee_id' => $employeeId,
                'agent_groups_count' => count($response['agentGroups'] ?? [])
            ]);

            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get agent group presence', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get integrated softphone credentials for WebRTC connection
     * Returns credentials for connecting to Mitel Border Gateway
     *
     * @param string $agentId
     * @return array
     * @throws Exception
     */
    public function getIntegratedSoftphoneCredentials(string $agentId): array
    {
        try {
            $this->log('INFO', 'Fetching integrated softphone credentials', [
                'agent_id' => $agentId
            ]);

            $response = $this->makeRequest('GET', "/api/v1/agents/{$agentId}/integratedsoftphone");

            $this->log('INFO', 'Integrated softphone credentials retrieved successfully', [
                'agent_id' => $agentId,
                'gateway_type' => $response['gatewayMediaServerType'] ?? null
            ]);

            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get integrated softphone credentials', [
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get busy reason codes for the agent
     * Returns available reason codes when agent changes status to busy
     *
     * @param string $employeeId
     * @return array
     * @throws Exception
     */
    public function getBusyReasonCodes(string $employeeId): array
    {
        try {
            $this->log('INFO', 'Fetching busy reason codes', [
                'employee_id' => $employeeId
            ]);

            $response = $this->makeRequest('GET', "/api/v1/employees/{$employeeId}/busyreasoncodes");

            $this->log('INFO', 'Busy reason codes retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $response['count'] ?? 0
            ]);

            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get busy reason codes', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Search directory for employees/agents
     * Returns directory entries matching the search query
     *
     * @param string $searchQuery
     * @param bool $voiceOnly
     * @param int $skip
     * @param int $top
     * @return array
     * @throws Exception
     */
    public function searchDirectory(string $searchQuery = '', bool $voiceOnly = true, int $skip = 0, int $top = 25, string $searchType = 'text'): array
    {
        try {
            $this->log('INFO', 'Searching directory', [
                'search_query' => $searchQuery,
                'search_type' => $searchType,
                'voice_only' => $voiceOnly,
                'skip' => $skip,
                'top' => $top
            ]);

            // Build query parameters
            $params = [
                '$filter' => "Type eq Mitel.MiccSdk.Models.DirectoryEntryType'Employee'",
                'voiceOnly' => $voiceOnly ? 'true' : 'false',
                '$orderby' => 'name asc',
                '$skip' => $skip,
                '$top' => $top
            ];

            // Add search query if provided
            if (!empty($searchQuery)) {
                // Support both text and ID-based search
                if ($searchType === 'id') {
                    // Search by ID: 'id: {uuid}'
                    $params['$search'] = "'id: " . $searchQuery . "'";
                } else {
                    // Search by text (name/extension): 'media:voice;{query}'
                    $params['$search'] = "'media:voice;" . $searchQuery . "'";
                }
            }

            // Build query string
            $queryString = http_build_query($params);
            $endpoint = "/api/v1/directory/?" . $queryString;

            $response = $this->makeRequest('GET', $endpoint);

            $this->log('INFO', 'Directory search completed', [
                'search_query' => $searchQuery,
                'search_type' => $searchType,
                'count' => $response['count'] ?? 0
            ]);

            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to search directory', [
                'search_query' => $searchQuery,
                'search_type' => $searchType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Make an outbound call (create conversation)
     *
     * @param string $employeeId Employee ID (use 'me' for current employee)
     * @param string $to Destination agent ID or phone number
     * @param string $from Caller ID (optional)
     * @param string $type Call type (Voice, Email, etc.)
     * @param bool $dialAsIs Whether to dial as-is without modifications
     * @param array $lightDirectoryEntryModels Optional directory entry models
     * @return array
     * @throws Exception
     */
    public function makeOutboundCall(
        string $employeeId,
        string $to,
        string $from = '',
        string $type = 'Voice',
        bool $dialAsIs = false,
        array $lightDirectoryEntryModels = []
    ): array {
        try {
            $this->log('INFO', 'Making outbound call', [
                'employee_id' => $employeeId,
                'to' => $to,
                'type' => $type,
                'dial_as_is' => $dialAsIs
            ]);

            $payload = [
                'to' => $to,
                'from' => $from,
                'type' => $type,
                'lightDirectoryEntryModels' => $lightDirectoryEntryModels
            ];

            // Build query parameters
            $queryParams = [];
            if ($dialAsIs !== null) {
                $queryParams['dialAsIs'] = $dialAsIs ? 'true' : 'false';
            }

            $queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
            $endpoint = "/api/v1/employees/{$employeeId}/conversations{$queryString}";

            $response = $this->makeRequest('POST', $endpoint, $payload);

            $this->log('INFO', 'Outbound call initiated successfully', [
                'employee_id' => $employeeId,
                'to' => $to,
                'response' => $response
            ]);

            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to make outbound call', [
                'employee_id' => $employeeId,
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // --- OPEN MEDIA API FUNCTIONS ---

    /**
     * OData: Get all active open media items currently being executed.
     * For historical queries use $search='{term}' to query previously executed items.
     * Endpoint: GET /api/v1/openmedia
     */
    public function getOpenMediaItems(array $odataParams = []): array
    {
        $this->log('INFO', 'Fetching active open media items', ['params' => array_keys($odataParams)]);
        try {
            $response = $this->makeRequest('GET', '/api/v1/openmedia', $odataParams);
            $this->log('INFO', 'Open media items fetched', ['count' => $response['count'] ?? count($response)]);
            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to fetch open media items', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Reroute a failed interaction or complete an interaction by a third-party system.
     * Endpoint: PUT /api/v1/openmedia/{id}
     *
     * @param  string $id   Mitel interaction ID
     * @param  array  $data Payload — e.g. ['interactionState' => 'Handled'] to complete
     */
    public function updateOpenMediaInteraction(string $id, array $data): array
    {
        $this->log('INFO', 'Updating open media interaction', ['id' => $id, 'keys' => array_keys($data)]);
        try {
            $response = $this->makeRequest('PUT', "/api/v1/openmedia/{$id}", $data);
            $this->log('INFO', 'Open media interaction updated', ['id' => $id]);
            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to update open media interaction', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * OData: Get all active open media queues with links to active conversations.
     * Endpoint: GET /api/v1/openmedia/queues
     */
    public function getOpenMediaQueues(array $odataParams = []): array
    {
        $this->log('INFO', 'Fetching open media queues', ['params' => array_keys($odataParams)]);
        try {
            $response = $this->makeRequest('GET', '/api/v1/openmedia/queues', $odataParams);
            $this->log('INFO', 'Open media queues fetched');
            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to fetch open media queues', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Escalate a citizen chat session to the Mitel queue via the OpenMedia endpoint.
     *
     * @param  string      $queue          Mitel queue name (e.g. "KASTAM-Chat")
     * @param  array       $vars           VariableData: Name, IC, Topic, FaqPath, etc.
     * @param  string|null $id             Pre-generated interaction ID (so targetUri can be built before calling)
     * @param  string|null $targetUri      URL shown/embedded to agent when they accept the interaction
     * @param  bool        $targetUriEmbedded  Embed targetUri as iframe inside Mitel Ignite (default true)
     * @param  string|null $subject        Subject line shown in agent inbox
     * @param  string|null $from           Originator label shown to agent
     */
    public function escalateToAgent(
        string $queue,
        array $vars = [],
        ?string $id = null,
        ?string $targetUri = null,
        bool $targetUriEmbedded = true,
        ?string $subject = null,
        ?string $from = null
    ): array {
        $this->log('INFO', 'Escalating chat to Mitel queue', [
            'queue'      => $queue,
            'id'         => $id,
            'target_uri' => $targetUri,
            'vars'       => array_keys($vars),
        ]);

        try {
            $payload = [
                'queue'        => $queue,
                'variableData' => $vars,
            ];

            if ($id)        $payload['id']                = $id;
            if ($targetUri) $payload['targetUri']         = $targetUri;
            if ($targetUri) $payload['targetUriEmbedded'] = $targetUriEmbedded;
            if ($subject)   $payload['subject']           = $subject;
            if ($from)      $payload['from']              = $from;

            $response = $this->makeRequest('POST', '/api/v1/openmedia', $payload);

            $this->log('INFO', 'Escalation raw response', ['response' => $response]);

            $interactionId = $response['id']
                ?? $response['Id']
                ?? $response['interactionId']
                ?? $response['InteractionId']
                ?? $id
                ?? null;

            $positionInQueue = $response['positionInQueue']
                ?? $response['PositionInQueue']
                ?? null;

            $this->log('INFO', 'Escalation successful', [
                'interaction_id'    => $interactionId,
                'position_in_queue' => $positionInQueue,
            ]);

            $response['_interaction_id']    = $interactionId;
            $response['_position_in_queue'] = $positionInQueue;

            return $response;
        } catch (Exception $e) {
            $this->log('ERROR', 'Escalation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @deprecated OpenMedia has no message-sending endpoint.
     * Messages are delivered to the agent via the targetUri iframe set during escalation.
     *
     * @param  string $interactionId  The Mitel interaction ID returned by escalateToAgent
     * @param  string $content        The plain-text message from the customer
     */
    /**
     * Get the current state of an OpenMedia interaction directly from Mitel.
     * Returns the full interaction object including conversationState.
     */
    public function getOpenMediaInteraction(string $interactionId): array
    {
        $this->log('INFO', 'Fetching OpenMedia interaction state', ['interaction_id' => $interactionId]);

        // Mitel has no GET /openmedia/{id} endpoint — list all active items and match by id.
        try {
            $response = $this->makeRequest('GET', '/api/v1/openmedia');

            // Mitel returns HAL format: { _embedded: { items: [...] }, count: N, _links: {...} }
            $items = $response['_embedded']['items']
                ?? $response['value']
                ?? [];

            $interaction = null;
            foreach ($items as $item) {
                $itemId = $item['id'] ?? $item['Id'] ?? $item['interactionId'] ?? $item['InteractionId'] ?? null;
                if ($itemId === $interactionId) {
                    $interaction = $item;
                    break;
                }
            }

            if (!$interaction) {
                // Not in the active list — interaction was completed/removed in Mitel.
                // Return a sentinel so callers can treat this as a definitive "ended" state
                // rather than a transient API failure.
                $this->log('INFO', 'OpenMedia interaction not in active list — treating as ended', ['interaction_id' => $interactionId]);
                return ['conversationState' => 'notfound'];
            }

            $this->log('INFO', 'OpenMedia interaction found', [
                'interaction_id' => $interactionId,
                'state'          => $interaction['conversationState'] ?? $interaction['state'] ?? 'unknown',
            ]);
            return $interaction;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get OpenMedia interaction', [
                'interaction_id' => $interactionId,
                'error'          => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send a customer message into a Mitel OpenMedia chat interaction.
     *
     * Tries endpoints in order until one succeeds:
     *  1. POST /api/v1/chat/{id}/messages   (WebChat-specific endpoint)
     *  2. PUT  /api/v1/employees/{agentId}/conversations/{id}  (agent-side relay)
     *
     * @param  string      $interactionId  Mitel interaction ID
     * @param  string      $content        Customer's message text
     * @param  string|null $agentId        Agent ID (from getOpenMediaInteraction), needed for fallback
     */
    public function sendOpenMediaMessage(string $interactionId, string $content, ?string $agentId = null): array
    {
        $this->log('INFO', 'Sending OpenMedia message', [
            'interaction_id' => $interactionId,
            'content_length' => strlen($content),
            'agent_id'       => $agentId,
        ]);

        // Attempt 1 — WebChat messages endpoint
        $endpoints = [
            ['method' => 'POST', 'uri' => "/api/v1/chat/{$interactionId}/messages",
             'body' => ['content' => $content, 'contentType' => 'text/plain', 'fromExternalParty' => true]],
            ['method' => 'POST', 'uri' => "/api/v1/openmedia/{$interactionId}/externalmessages",
             'body' => ['content' => $content, 'contentType' => 'text/plain']],
        ];

        // Attempt 3 — relay via agent conversation (if agentId known)
        if ($agentId) {
            $endpoints[] = [
                'method' => 'PUT',
                'uri'    => "/api/v1/employees/{$agentId}/conversations/{$interactionId}",
                'body'   => ['conversationAction' => 'Chat', 'content' => $content],
            ];
        }

        $lastError = null;
        foreach ($endpoints as $ep) {
            try {
                $response = $this->makeRequest($ep['method'], $ep['uri'], $ep['body']);
                $this->log('INFO', 'OpenMedia message sent', [
                    'interaction_id' => $interactionId,
                    'endpoint'       => $ep['uri'],
                ]);
                return $response;
            } catch (Exception $e) {
                $this->log('WARN', 'OpenMedia message endpoint failed, trying next', [
                    'endpoint' => $ep['uri'],
                    'error'    => substr($e->getMessage(), 0, 120),
                ]);
                $lastError = $e;
            }
        }

        $this->log('ERROR', 'All OpenMedia message endpoints failed', ['interaction_id' => $interactionId]);
        throw $lastError;
    }
}