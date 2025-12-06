<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MitelService
{
    private string $baseUrl;
    private string $authUrl;
    private string $clientId;
    private string $clientSecret;
    private int $timeout;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->baseUrl = config('services.mitel.base_url');
        $this->authUrl = config('services.mitel.auth_url');
        $this->clientId = config('services.mitel.client_id');
        $this->clientSecret = config('services.mitel.client_secret');
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
     * Pattern: [TIMESTAMP] LEVEL ClassName.methodName - Message {context}
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = now()->format('Y-m-d H:i:s.v');
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1] ?? $trace[0];
        $method = $caller['function'] ?? 'unknown';
        $class = isset($caller['class']) ? class_basename($caller['class']) : 'MitelService';
        
        $logMessage = sprintf(
            "[%s] %s %s.%s - %s",
            $timestamp,
            strtoupper($level),
            $class,
            $method,
            $message
        );

        // Format context for better readability
        if (!empty($context)) {
            $contextStr = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $logMessage .= "\nContext: " . $contextStr;
        }

        match (strtolower($level)) {
            'debug' => Log::debug($logMessage),
            'info' => Log::info($logMessage),
            'warning', 'warn' => Log::warning($logMessage),
            'error' => Log::error($logMessage),
            'critical' => Log::critical($logMessage),
            default => Log::info($logMessage),
        };
    }

    /**
     * Authenticate with Mitel OAuth2 and get access token
     * @param string|null $impersonateUser Username to impersonate (optional)
     */
    private function authenticate(?string $impersonateUser = null): string
    {
        $startTime = microtime(true);
        
        if ($this->accessToken && !$impersonateUser) {
            $this->log('DEBUG', 'Using cached access token');
            return $this->accessToken;
        }

        $this->log('INFO', 'Initiating Mitel authentication', [
            'auth_url' => $this->authUrl,
            'client_id' => $this->clientId,
            'grant_type' => 'client_credentials',
            'impersonate' => $impersonateUser ?? 'none'
        ]);

        try {
            $payload = [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ];

            // Add impersonation header if user is specified
            $headers = [];
            if ($impersonateUser) {
                $headers['Impersonate'] = $impersonateUser;
                $this->log('DEBUG', 'Adding impersonation header', ['user' => $impersonateUser]);
            }

            $request = Http::timeout($this->timeout)
                ->asForm();

            if (!empty($headers)) {
                $request = $request->withHeaders($headers);
            }

            $response = $request->post($this->authUrl, $payload);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if (!$response->successful()) {
                $this->log('ERROR', 'Mitel authentication failed', [
                    'status_code' => $response->status(),
                    'reason_phrase' => $response->reason(),
                    'response_body' => $response->body(),
                    'duration_ms' => $duration
                ]);
                throw new Exception('Failed to authenticate with Mitel: ' . $response->body());
            }

            $data = $response->json();
            $this->accessToken = $data['access_token'] ?? null;

            if (!$this->accessToken) {
                $this->log('ERROR', 'Access token missing in Mitel response', [
                    'response_keys' => array_keys($data),
                    'duration_ms' => $duration
                ]);
                throw new Exception('Access token not found in Mitel response');
            }

            $this->log('INFO', 'Mitel authentication successful', [
                'token_length' => strlen($this->accessToken),
                'token_type' => $data['token_type'] ?? 'unknown',
                'expires_in' => $data['expires_in'] ?? 'unknown',
                'duration_ms' => $duration
            ]);

            return $this->accessToken;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->log('ERROR', 'Mitel authentication exception', [
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'duration_ms' => $duration
            ]);
            throw $e;
        }
    }

    /**
     * Make authenticated request to Mitel API
     * @return array|int|string|bool Response data (usually array, but can be scalar for some endpoints)
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array|int|string|bool
    {
        $startTime = microtime(true);
        $requestId = uniqid('mitel_', true);
        
        $this->log('INFO', 'Starting Mitel API request', [
            'request_id' => $requestId,
            'method' => strtoupper($method),
            'endpoint' => $endpoint,
            'has_payload' => !empty($data),
            'payload_keys' => !empty($data) ? array_keys($data) : []
        ]);

        try {
            $token = $this->authenticate();
            $url = $this->baseUrl . $endpoint;

            $this->log('DEBUG', 'Sending HTTP request', [
                'request_id' => $requestId,
                'full_url' => $url,
                'method' => strtoupper($method),
                'headers' => [
                    'Authorization' => 'Bearer ' . substr($token, 0, 20) . '...',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'payload' => $data
            ]);

            $request = Http::timeout($this->timeout)
                ->withToken($token)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);

            $response = match (strtoupper($method)) {
                'GET' => $request->get($url, $data),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'DELETE' => $request->delete($url, $data),
                default => throw new Exception("Unsupported HTTP method: $method"),
            };

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if (!$response->successful()) {
                $this->log('ERROR', 'Mitel API request failed', [
                    'request_id' => $requestId,
                    'method' => strtoupper($method),
                    'endpoint' => $endpoint,
                    'status_code' => $response->status(),
                    'reason_phrase' => $response->reason(),
                    'response_headers' => $response->headers(),
                    'response_body' => $response->body(),
                    'duration_ms' => $duration
                ]);
                throw new Exception('Mitel API request failed: ' . $response->body());
            }

            $responseData = $response->json();
            
            $this->log('INFO', 'Mitel API request successful', [
                'request_id' => $requestId,
                'method' => strtoupper($method),
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'response_size_bytes' => strlen($response->body()),
                'response_keys' => is_array($responseData) ? array_keys($responseData) : 'scalar',
                'duration_ms' => $duration
            ]);

            return $responseData;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->log('ERROR', 'Mitel API request exception', [
                'request_id' => $requestId,
                'method' => strtoupper($method),
                'endpoint' => $endpoint,
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'duration_ms' => $duration,
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get agent status
     */
    public function getAgentStatus(string $agentId): array
    {
        $this->log('INFO', 'Retrieving agent status', ['agent_id' => $agentId]);
        $result = $this->makeRequest('GET', "/agents/{$agentId}/status");
        $this->log('DEBUG', 'Agent status retrieved', [
            'agent_id' => $agentId,
            'status' => $result['status'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Set agent status (Available, Away, etc.)
     */
    public function setAgentStatus(string $agentId, string $status): array
    {
        $this->log('INFO', 'Setting agent status', [
            'agent_id' => $agentId,
            'new_status' => $status
        ]);
        $result = $this->makeRequest('POST', "/agents/{$agentId}/status", [
            'status' => $status
        ]);
        $this->log('INFO', 'Agent status updated successfully', [
            'agent_id' => $agentId,
            'status' => $status
        ]);
        return $result;
    }

    /**
     * Get agent statistics
     */
    public function getAgentStats(string $agentId): array
    {
        $this->log('INFO', 'Retrieving agent statistics', ['agent_id' => $agentId]);
        $result = $this->makeRequest('GET', "/agents/{$agentId}/statistics");
        $this->log('DEBUG', 'Agent statistics retrieved', [
            'agent_id' => $agentId,
            'stats_keys' => array_keys($result)
        ]);
        return $result;
    }

    /**
     * Make outbound call
     */
    public function makeCall(string $agentId, string $phoneNumber): array
    {
        $this->log('INFO', 'Initiating outbound call', [
            'agent_id' => $agentId,
            'destination' => $phoneNumber
        ]);
        $result = $this->makeRequest('POST', "/agents/{$agentId}/call", [
            'destination' => $phoneNumber
        ]);
        $this->log('INFO', 'Outbound call initiated', [
            'agent_id' => $agentId,
            'destination' => $phoneNumber,
            'call_id' => $result['call_id'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Answer incoming call
     */
    public function answerCall(string $agentId, string $callId): array
    {
        $this->log('INFO', 'Answering incoming call', [
            'agent_id' => $agentId,
            'call_id' => $callId
        ]);
        $result = $this->makeRequest('POST', "/agents/{$agentId}/calls/{$callId}/answer");
        $this->log('INFO', 'Call answered successfully', [
            'agent_id' => $agentId,
            'call_id' => $callId
        ]);
        return $result;
    }

    /**
     * End call
     */
    public function endCall(string $agentId, string $callId): array
    {
        $this->log('INFO', 'Ending call', [
            'agent_id' => $agentId,
            'call_id' => $callId
        ]);
        $result = $this->makeRequest('POST', "/agents/{$agentId}/calls/{$callId}/end");
        $this->log('INFO', 'Call ended successfully', [
            'agent_id' => $agentId,
            'call_id' => $callId
        ]);
        return $result;
    }

    /**
     * Hold call
     */
    public function holdCall(string $agentId, string $callId): array
    {
        $this->log('INFO', 'Placing call on hold', [
            'agent_id' => $agentId,
            'call_id' => $callId
        ]);
        $result = $this->makeRequest('POST', "/agents/{$agentId}/calls/{$callId}/hold");
        $this->log('INFO', 'Call placed on hold', [
            'agent_id' => $agentId,
            'call_id' => $callId
        ]);
        return $result;
    }

    /**
     * Resume call
     */
    public function resumeCall(string $agentId, string $callId): array
    {
        $this->log('INFO', 'Resuming call from hold', [
            'agent_id' => $agentId,
            'call_id' => $callId
        ]);
        $result = $this->makeRequest('POST', "/agents/{$agentId}/calls/{$callId}/resume");
        $this->log('INFO', 'Call resumed successfully', [
            'agent_id' => $agentId,
            'call_id' => $callId
        ]);
        return $result;
    }

    /**
     * Transfer call
     */
    public function transferCall(string $agentId, string $callId, string $destination): array
    {
        $this->log('INFO', 'Transferring call', [
            'agent_id' => $agentId,
            'call_id' => $callId,
            'destination' => $destination
        ]);
        $result = $this->makeRequest('POST', "/agents/{$agentId}/calls/{$callId}/transfer", [
            'destination' => $destination
        ]);
        $this->log('INFO', 'Call transferred successfully', [
            'agent_id' => $agentId,
            'call_id' => $callId,
            'destination' => $destination
        ]);
        return $result;
    }

    /**
     * Get active calls for agent
     */
    public function getActiveCalls(string $agentId): array
    {
        $this->log('INFO', 'Retrieving active calls', ['agent_id' => $agentId]);
        $result = $this->makeRequest('GET', "/agents/{$agentId}/calls");
        $callCount = is_array($result) ? count($result) : 0;
        $this->log('DEBUG', 'Active calls retrieved', [
            'agent_id' => $agentId,
            'call_count' => $callCount
        ]);
        return $result;
    }

    /**
     * Get call history
     */
    public function getCallHistory(string $agentId, ?string $startDate = null, ?string $endDate = null): array
    {
        $this->log('INFO', 'Retrieving call history', [
            'agent_id' => $agentId,
            'start_date' => $startDate ?? 'not specified',
            'end_date' => $endDate ?? 'not specified'
        ]);
        
        $params = [];
        if ($startDate) {
            $params['start_date'] = $startDate;
        }
        if ($endDate) {
            $params['end_date'] = $endDate;
        }

        $result = $this->makeRequest('GET', "/agents/{$agentId}/history", $params);
        $historyCount = is_array($result) ? count($result) : 0;
        
        $this->log('DEBUG', 'Call history retrieved', [
            'agent_id' => $agentId,
            'history_count' => $historyCount,
            'date_range' => ($startDate && $endDate) ? "$startDate to $endDate" : 'all time'
        ]);
        
        return $result;
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats(string $queueId): array
    {
        $this->log('INFO', 'Retrieving queue statistics', ['queue_id' => $queueId]);
        $result = $this->makeRequest('GET', "/queues/{$queueId}/statistics");
        $this->log('DEBUG', 'Queue statistics retrieved', [
            'queue_id' => $queueId,
            'stats_keys' => array_keys($result)
        ]);
        return $result;
    }

    /**
     * Get all queues
     */
    public function getQueues(): array
    {
        $this->log('INFO', 'Retrieving all queues');
        $result = $this->makeRequest('GET', '/queues');
        $queueCount = is_array($result) ? count($result) : 0;
        $this->log('DEBUG', 'All queues retrieved', ['queue_count' => $queueCount]);
        return $result;
    }

    // ==================== EMPLOYEE MANAGEMENT ====================

    /**
     * Get all employees (OData support)
     */
    public function getEmployees(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving all employees', ['odata_params' => $odataParams]);
        $result = $this->makeRequest('GET', '/api/v1/employees', $odataParams);
        $employeeCount = $result['items'] ?? $result['value'] ?? 0;
        $this->log('DEBUG', 'Employees retrieved', ['employee_count' => is_array($employeeCount) ? count($employeeCount) : $employeeCount]);
        return $result;
    }

    /**
     * Get employee by ID, username, or 'me'
     */
    public function getEmployee(string $id): array
    {
        $this->log('INFO', 'Retrieving employee details', ['employee_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/employees/{$id}");
        $this->log('DEBUG', 'Employee details retrieved', [
            'employee_id' => $id,
            'username' => $result['username'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Get employee state (presence status)
     */
    public function getEmployeeState(string $id): array
    {
        $this->log('INFO', 'Retrieving employee state', ['employee_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/employees/{$id}/state");
        $this->log('DEBUG', 'Employee state retrieved', [
            'employee_id' => $id,
            'state' => $result['state'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Update employee state (Login/Logout, Available/Busy, etc.)
     */
    public function updateEmployeeState(string $id, array $stateData): array
    {
        $this->log('INFO', 'Updating employee state', [
            'employee_id' => $id,
            'new_state' => $stateData['state'] ?? 'not specified'
        ]);
        $result = $this->makeRequest('PUT', "/api/v1/employees/{$id}/state", $stateData);
        $this->log('INFO', 'Employee state updated successfully', [
            'employee_id' => $id,
            'state' => $stateData['state'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Get employee queues
     */
    public function getEmployeeQueues(string $id): array
    {
        $this->log('INFO', 'Retrieving employee queues', ['employee_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/employees/{$id}/queues");
        $queueCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'Employee queues retrieved', [
            'employee_id' => $id,
            'queue_count' => $queueCount
        ]);
        return $result;
    }

    /**
     * Get employee agent groups
     */
    public function getEmployeeAgentGroups(string $id): array
    {
        $this->log('INFO', 'Retrieving employee agent groups', ['employee_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/employees/{$id}/agentgroups");
        $groupCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'Employee agent groups retrieved', [
            'employee_id' => $id,
            'group_count' => $groupCount
        ]);
        return $result;
    }

    /**
     * Get employee conversations (inbox)
     */
    public function getEmployeeConversations(string $id, string $type = '', bool $refreshMonitors = false): array
    {
        $this->log('INFO', 'Retrieving employee conversations', [
            'employee_id' => $id,
            'type' => $type ?: 'all',
            'refresh_monitors' => $refreshMonitors
        ]);
        $params = [];
        if ($type) {
            $params['type'] = $type; // active, drafts, failed
        }
        if ($refreshMonitors) {
            $params['refreshMonitors'] = true;
        }
        $result = $this->makeRequest('GET', "/api/v1/employees/{$id}/conversations", $params);
        $conversationCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'Employee conversations retrieved', [
            'employee_id' => $id,
            'conversation_count' => $conversationCount,
            'type' => $type ?: 'all'
        ]);
        return $result;
    }

    /**
     * Get specific conversation for employee
     */
    public function getEmployeeConversation(string $id, string $conversationId, int $waitTimeMs = 2000): array
    {
        $this->log('INFO', 'Retrieving employee conversation', [
            'employee_id' => $id,
            'conversation_id' => $conversationId
        ]);
        $result = $this->makeRequest('GET', "/api/v1/employees/{$id}/conversations/{$conversationId}", [
            'waitTimeMs' => $waitTimeMs
        ]);
        $this->log('DEBUG', 'Employee conversation retrieved', [
            'employee_id' => $id,
            'conversation_id' => $conversationId,
            'media' => $result['media'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Update conversation (Accept, Decline, Hold, Transfer, End, etc.)
     */
    public function updateConversation(string $employeeId, string $conversationId, array $updateData): array
    {
        $this->log('INFO', 'Updating conversation', [
            'employee_id' => $employeeId,
            'conversation_id' => $conversationId,
            'action' => $updateData['conversationAction'] ?? 'not specified'
        ]);
        $result = $this->makeRequest('PUT', "/api/v1/employees/{$employeeId}/conversations/{$conversationId}", $updateData);
        $this->log('INFO', 'Conversation updated successfully', [
            'employee_id' => $employeeId,
            'conversation_id' => $conversationId,
            'action' => $updateData['conversationAction'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Start new conversation (Outbound call, SMS)
     */
    public function createConversation(string $employeeId, array $conversationData): array
    {
        $this->log('INFO', 'Creating new conversation', [
            'employee_id' => $employeeId,
            'media' => $conversationData['media'] ?? 'unknown',
            'destination' => $conversationData['destination'] ?? 'not specified'
        ]);
        $result = $this->makeRequest('POST', "/api/v1/employees/{$employeeId}/conversations", $conversationData);
        $this->log('INFO', 'Conversation created successfully', [
            'employee_id' => $employeeId,
            'conversation_id' => $result['id'] ?? 'unknown',
            'media' => $conversationData['media'] ?? 'unknown'
        ]);
        return $result;
    }

    // ==================== AGENT MANAGEMENT ====================

    /**
     * Get all agents with OData support
     */
    public function getAgents(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving all agents', ['odata_params' => $odataParams]);
        $result = $this->makeRequest('GET', '/api/v1/agents', $odataParams);
        $agentCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'Agents retrieved', ['agent_count' => $agentCount]);
        return $result;
    }

    /**
     * Get agent by ID or name
     */
    public function getAgent(string $id): array
    {
        $this->log('INFO', 'Retrieving agent details', ['agent_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/agents/{$id}");
        $this->log('DEBUG', 'Agent details retrieved', [
            'agent_id' => $id,
            'name' => $result['name'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Get all agent states
     */
    public function getAgentStates(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving all agent states');
        $result = $this->makeRequest('GET', '/api/v1/agents/states', $odataParams);
        $stateCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'Agent states retrieved', ['state_count' => $stateCount]);
        return $result;
    }

    /**
     * Get agent state by ID
     */
    public function getAgentState(string $id): array
    {
        $this->log('INFO', 'Retrieving agent state', ['agent_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/agents/{$id}/state");
        $this->log('DEBUG', 'Agent state retrieved', [
            'agent_id' => $id,
            'state' => $result['state'] ?? 'unknown',
            'logged_in' => $result['loggedIn'] ?? false
        ]);
        return $result;
    }

    /**
     * Get agent groups
     */
    public function getAgentGroups(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving all agent groups');
        $result = $this->makeRequest('GET', '/api/v1/agents/groups', $odataParams);
        $groupCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'Agent groups retrieved', ['group_count' => $groupCount]);
        return $result;
    }

    /**
     * Get agent group by ID
     */
    public function getAgentGroup(string $id): array
    {
        $this->log('INFO', 'Retrieving agent group', ['group_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/agents/groups/{$id}");
        $this->log('DEBUG', 'Agent group retrieved', [
            'group_id' => $id,
            'name' => $result['name'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Get agent group states (all agents in group)
     */
    public function getAgentGroupStates(string $id): array
    {
        $this->log('INFO', 'Retrieving agent group states', ['group_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/agents/groups/{$id}/states");
        $agentCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'Agent group states retrieved', [
            'group_id' => $id,
            'agent_count' => $agentCount
        ]);
        return $result;
    }

    /**
     * Get concurrent agent logins and license info
     */
    public function getConcurrentAgents(): array
    {
        $this->log('INFO', 'Retrieving concurrent agent logins');
        $result = $this->makeRequest('GET', '/api/v1/agents/logins');
        $this->log('DEBUG', 'Concurrent agent info retrieved', [
            'logged_in' => $result['loggedIn'] ?? 0,
            'licensed' => $result['licensed'] ?? 0
        ]);
        return $result;
    }

    // ==================== QUEUE MANAGEMENT ====================

    /**
     * Get queue by ID or name
     */
    public function getQueue(string $id): array
    {
        $this->log('INFO', 'Retrieving queue details', ['queue_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/queues/{$id}");
        $this->log('DEBUG', 'Queue details retrieved', [
            'queue_id' => $id,
            'name' => $result['name'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Get queue state (real-time stats)
     */
    public function getQueueState(string $id): array
    {
        $this->log('INFO', 'Retrieving queue state', ['queue_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/queues/{$id}/state");
        $this->log('DEBUG', 'Queue state retrieved', [
            'queue_id' => $id,
            'calls_waiting' => $result['callsWaiting'] ?? 0,
            'agents_available' => $result['agentsAvailable'] ?? 0
        ]);
        return $result;
    }

    /**
     * Get queue conversations (calls waiting)
     */
    public function getQueueConversations(string $id): array
    {
        $this->log('INFO', 'Retrieving queue conversations', ['queue_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/queues/{$id}/conversations");
        $conversationCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'Queue conversations retrieved', [
            'queue_id' => $id,
            'conversation_count' => $conversationCount
        ]);
        return $result;
    }

    // ==================== CONVERSATION/INTERACTION MANAGEMENT ====================

    /**
     * Get all active conversations (OData)
     */
    public function getConversations(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving all conversations');
        $result = $this->makeRequest('GET', '/api/v1/conversations', $odataParams);
        $conversationCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'Conversations retrieved', ['conversation_count' => $conversationCount]);
        return $result;
    }

    /**
     * Get conversation by ID
     */
    public function getConversation(string $id): array
    {
        $this->log('INFO', 'Retrieving conversation', ['conversation_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/conversations/{$id}");
        $this->log('DEBUG', 'Conversation retrieved', [
            'conversation_id' => $id,
            'media' => $result['media'] ?? 'unknown',
            'state' => $result['state'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Add call details data to conversation (for WebIgnite)
     */
    public function addCallDetailsData(string $conversationId, array $callDetails): array
    {
        $this->log('INFO', 'Adding call details data', [
            'conversation_id' => $conversationId,
            'data_keys' => array_keys($callDetails)
        ]);
        $result = $this->makeRequest('POST', "/api/v1/conversations/{$conversationId}/addCallDetailsData", $callDetails);
        $this->log('INFO', 'Call details data added successfully', [
            'conversation_id' => $conversationId
        ]);
        return $result;
    }

    // ==================== DIRECTORY & CONTACT MANAGEMENT ====================

    /**
     * Search directory entries (OData)
     */
    public function getDirectoryEntries(array $odataParams = []): array
    {
        $this->log('INFO', 'Searching directory entries', ['odata_params' => $odataParams]);
        $result = $this->makeRequest('GET', '/api/v1/directory', $odataParams);
        $entryCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'Directory entries retrieved', ['entry_count' => $entryCount]);
        return $result;
    }

    /**
     * Get directory entry by ID
     */
    public function getDirectoryEntry(string $id): array
    {
        $this->log('INFO', 'Retrieving directory entry', ['entry_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/directory/{$id}");
        $this->log('DEBUG', 'Directory entry retrieved', [
            'entry_id' => $id,
            'name' => $result['name'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Create directory entry
     */
    public function createDirectoryEntry(array $entryData): array
    {
        $this->log('INFO', 'Creating directory entry', [
            'name' => $entryData['name'] ?? 'not specified'
        ]);
        $result = $this->makeRequest('POST', '/api/v1/directory', $entryData);
        $this->log('INFO', 'Directory entry created', [
            'entry_id' => $result['id'] ?? 'unknown',
            'name' => $result['name'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Update directory entry
     */
    public function updateDirectoryEntry(string $id, array $entryData): array
    {
        $this->log('INFO', 'Updating directory entry', ['entry_id' => $id]);
        $result = $this->makeRequest('PUT', "/api/v1/directory/{$id}", $entryData);
        $this->log('INFO', 'Directory entry updated', ['entry_id' => $id]);
        return $result;
    }

    // ==================== HISTORY & REPORTING ====================

    /**
     * Search historical interactions (OData with $search)
     */
    public function getHistory(array $odataParams, int $offset = 0, bool $global = false): array
    {
        $this->log('INFO', 'Searching interaction history', [
            'odata_params' => $odataParams,
            'offset' => $offset,
            'global' => $global
        ]);
        $params = array_merge($odataParams, [
            'offset' => $offset,
            'global' => $global
        ]);
        $result = $this->makeRequest('GET', '/api/v1/history', $params);
        $historyCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'History retrieved', ['record_count' => $historyCount]);
        return $result;
    }

    /**
     * Get history count
     */
    public function getHistoryCount(bool $global = false): int
    {
        $this->log('INFO', 'Retrieving history count', ['global' => $global]);
        $data = $this->makeRequest('GET', '/api/v1/history/count', ['global' => $global]);
        $count = is_numeric($data) ? (int)$data : (isset($data['count']) ? (int)$data['count'] : 0);
        $this->log('DEBUG', 'History count retrieved', ['count' => $count]);
        return $count;
    }

    // ==================== SYSTEM INFORMATION ====================

    /**
     * Get all sites/tenants
     */
    public function getSites(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving sites/tenants');
        $result = $this->makeRequest('GET', '/api/v1/sites', $odataParams);
        $siteCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'Sites retrieved', ['site_count' => $siteCount]);
        return $result;
    }

    /**
     * Get site by ID or name
     */
    public function getSite(string $id): array
    {
        $this->log('INFO', 'Retrieving site details', ['site_id' => $id]);
        $result = $this->makeRequest('GET', "/api/v1/sites/{$id}");
        $this->log('DEBUG', 'Site details retrieved', [
            'site_id' => $id,
            'name' => $result['name'] ?? 'unknown'
        ]);
        return $result;
    }

    /**
     * Get media servers
     */
    public function getMediaServers(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving media servers');
        $result = $this->makeRequest('GET', '/api/v1/mediaservers', $odataParams);
        $serverCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'Media servers retrieved', ['server_count' => $serverCount]);
        return $result;
    }

    /**
     * Get reason codes
     */
    public function getReasonCodes(array $odataParams = []): array
    {
        $this->log('INFO', 'Retrieving reason codes');
        $result = $this->makeRequest('GET', '/api/v1/reasoncodes', $odataParams);
        $codeCount = is_array($result['items'] ?? []) ? count($result['items']) : 0;
        $this->log('DEBUG', 'Reason codes retrieved', ['code_count' => $codeCount]);
        return $result;
    }
}
