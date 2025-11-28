# Mitel/MiContact Center Integration API

## Overview

This document describes the Mitel/MiContact Center Web SDK integration for the JKDM CRM system. The integration provides real-time telephony capabilities including:

- **Agent State Management** - Login, logout, availability status
- **Conversation Handling** - Accept, transfer, hold, end calls/chats/emails
- **OpenMedia Integration** - Custom channel support (Email, Chat, SMS)
- **Real-time Queue Monitoring** - Live queue statistics
- **WebSocket Broadcasting** - Real-time event notifications

## Authentication

All Mitel API endpoints require JWT authentication. Include the bearer token in the Authorization header:

```
Authorization: Bearer {your-jwt-token}
```

## Environment Configuration

Add these variables to your `.env` file:

```env
# Mitel MiContact Center Configuration
MITEL_BASE_URL=https://your-mitel-server.com/miccsdk
MITEL_API_TOKEN=your-mitel-api-token
MITEL_API_TIMEOUT=30

# WebSocket Broadcasting Configuration
BROADCAST_DRIVER=pusher  # or reverb, redis, log
PUSHER_APP_KEY=your-pusher-key
PUSHER_APP_SECRET=your-pusher-secret
PUSHER_APP_ID=your-pusher-app-id
PUSHER_APP_CLUSTER=mt1
```

---

## API Endpoints

### Agent State Management

#### Get Agent State
```http
GET /api/v1/mitel/agents/{agentId}/state
```

**Response:**
```json
{
  "success": true,
  "data": {
    "Id": "guid",
    "CurrentState": "Available",
    "EnteredStateOn": "2024-11-28T10:30:00Z",
    "Reason": null,
    "AcdConversationsToday": 15,
    "LoggedInDurationToday": "PT6H30M"
  }
}
```

#### Update Agent State
```http
PUT /api/v1/mitel/agents/{agentId}/state
```

**Request Body:**
```json
{
  "state": "Available",
  "reason": "Break",
  "device_id": "optional-device-id"
}
```

**States:** `Available`, `Busy`, `DoNotDisturb`, `Logout`

**Response:**
```json
{
  "success": true,
  "message": "Agent state updated successfully",
  "data": {
    "agent_id": "123",
    "state": "Available"
  }
}
```

**Events:** Broadcasts `agent.state.changed` event via WebSocket

---

### Conversation Management

#### Get Agent Conversations
```http
GET /api/v1/mitel/agents/{agentId}/conversations?type=active
```

**Query Parameters:**
- `type` (optional): `active`, `drafts`, `failed`, or empty for all

**Response:**
```json
{
  "success": true,
  "data": {
    "_embedded": {
      "Items": [
        {
          "id": "conversation-guid",
          "MediaType": "Voice",
          "State": "ACD",
          "StartTime": "2024-11-28T10:45:00Z"
        }
      ]
    }
  }
}
```

#### Create Conversation (Outbound)
```http
POST /api/v1/mitel/agents/{agentId}/conversations
```

**Request Body (Voice):**
```json
{
  "media_type": "Voice",
  "destination": "+60123456789",
  "account_code": "SALES001"
}
```

**Request Body (Email):**
```json
{
  "media_type": "Email",
  "destination": "customer@example.com",
  "subject": "RE: Your Inquiry",
  "body": "Email content here...",
  "account_code": "SUPPORT001"
}
```

**Request Body (SMS):**
```json
{
  "media_type": "SMS",
  "destination": "+60123456789",
  "body": "Your OTP is 123456"
}
```

#### Update Conversation
```http
PUT /api/v1/mitel/agents/{agentId}/conversations/{conversationId}
```

**Request Body:**
```json
{
  "action": "Accept",
  "transfer_to": "queue-id-or-agent",
  "account_code": "SALES001"
}
```

**Actions:**
- `Accept` - Accept incoming conversation
- `Decline` - Decline/reject conversation
- `Hold` - Put conversation on hold
- `RemoveHold` - Resume from hold
- `Transfer` - Transfer to another agent/queue
- `End` - End conversation
- `Pick` - Pick up conversation

**Events:** Broadcasts `conversation.updated` event via WebSocket

---

### Queue Management

#### Get Queue State
```http
GET /api/v1/mitel/queues/{queueId}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "Id": "queue-guid",
    "Name": "Customer Support",
    "WaitingCount": 5,
    "AvailableAgents": 12,
    "LongestWaitTime": "PT2M30S"
  }
}
```

#### Get Agent Queues
```http
GET /api/v1/mitel/agents/{agentId}/queues
```

**Response:** List of queues the agent is member of

---

### OpenMedia Integration

#### Create OpenMedia Interaction
```http
POST /api/v1/mitel/openmedia
```

**Request Body:**
```json
{
  "queue_id": "queue-guid",
  "priority": 5,
  "subject": "Customer Inquiry",
  "body": "Interaction details...",
  "contact_name": "Ahmad Abdullah",
  "contact_email": "ahmad@example.com",
  "contact_phone": "+60123456789",
  "variable_data": {
    "Department": "SALES TAX",
    "Category": "Registration",
    "TicketNumber": "ST-2024-001"
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "OpenMedia interaction created successfully",
  "data": {
    "id": "interaction-guid",
    "State": "InQueue"
  }
}
```

#### Update OpenMedia Interaction
```http
PUT /api/v1/mitel/openmedia/{interactionId}
```

**Request Body:**
```json
{
  "action": "Complete",
  "queue_id": "optional-new-queue-for-reroute"
}
```

**Actions:** `Complete`, `Reroute`

---

### Directory/Contacts

#### Get Directory Entries
```http
GET /api/v1/mitel/directory?search=Ahmad
```

**Response:**
```json
{
  "success": true,
  "data": {
    "_embedded": {
      "Items": [
        {
          "Name": "Ahmad Abdullah",
          "Email": "ahmad@example.com",
          "Phone": "+60123456789"
        }
      ]
    }
  }
}
```

---

### Webhook Receiver

#### Mitel Webhook Endpoint
```http
POST /api/v1/mitel/webhook
```

**Request Body:**
```json
{
  "eventType": "AgentStateChanged",
  "data": {
    "agent_id": "123",
    "state": "Available",
    "timestamp": "2024-11-28T10:30:00Z"
  }
}
```

**Event Types:**
- `AgentStateChanged`
- `ConversationUpdated`
- `QueueStateChanged`

This endpoint receives real-time events from Mitel and broadcasts them via WebSocket.

---

## WebSocket Integration

### Available Channels

| Channel | Description | Authorization |
|---------|-------------|---------------|
| `agents` | Global agent updates | All authenticated users |
| `agent.{agentId}` | Individual agent updates | Agent or supervisors |
| `queues` | Global queue updates | All authenticated users |
| `queue.{queueId}` | Individual queue updates | All authenticated users |
| `conversation.{conversationId}` | Conversation updates | Assigned agent |
| `supervisor.{supervisorId}` | Supervisor monitoring | Supervisors only |
| `tickets` | Global ticket updates | All authenticated users |

### Events

#### agent.state.changed
```json
{
  "agent_id": "123",
  "state": "Available",
  "reason": "Break",
  "timestamp": "2024-11-28T10:30:00Z"
}
```

#### conversation.updated
```json
{
  "agent_id": "123",
  "conversation_id": "conv-guid",
  "action": "Accept",
  "state": "ACD",
  "media_type": "Voice",
  "timestamp": "2024-11-28T10:45:00Z"
}
```

#### queue.state.changed
```json
{
  "queue_id": "queue-guid",
  "queue_name": "Customer Support",
  "waiting_count": 5,
  "available_agents": 12,
  "longest_wait_time": 150,
  "timestamp": "2024-11-28T10:50:00Z"
}
```

#### ticket.notification
```json
{
  "ticket_id": 1,
  "ticket_number": "ST-2024-001",
  "event_type": "assigned",
  "priority": "High",
  "status": "Open",
  "assigned_to": "123",
  "subject": "Tax Registration Issue",
  "timestamp": "2024-11-28T11:00:00Z"
}
```

### WebSocket Testing Endpoints

#### Connection Info
```http
GET /api/v1/websocket/info
```

#### Health Check
```http
GET /api/v1/websocket/health
```

#### Test Broadcast
```http
POST /api/v1/websocket/test
```

**Request Body:**
```json
{
  "event_type": "agent_state",
  "data": {
    "agent_id": "123",
    "state": "Available"
  }
}
```

---

## Frontend Integration Example

### Using Laravel Echo (Pusher)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.VITE_PUSHER_APP_KEY,
    cluster: process.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            Authorization: `Bearer ${authToken}`
        }
    }
});

// Listen to agent state changes
window.Echo.channel('agents')
    .listen('.agent.state.changed', (e) => {
        console.log('Agent state changed:', e);
        updateAgentUI(e);
    });

// Listen to specific agent channel
window.Echo.channel(`agent.${agentId}`)
    .listen('.conversation.updated', (e) => {
        console.log('Conversation updated:', e);
        updateConversationUI(e);
    });

// Listen to queue updates
window.Echo.channel(`queue.${queueId}`)
    .listen('.queue.state.changed', (e) => {
        console.log('Queue state changed:', e);
        updateQueueStats(e);
    });
```

---

## Error Handling

All API responses follow this format:

**Success Response:**
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {}
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error message"
}
```

**HTTP Status Codes:**
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `404` - Not Found
- `500` - Internal Server Error

---

## Setup Instructions

### 1. Install Broadcasting Package
```bash
composer require pusher/pusher-php-server
```

### 2. Configure Broadcasting
Update `.env` with your Pusher credentials or configure Laravel Reverb

### 3. Queue Worker
Start queue worker for processing broadcasts:
```bash
php artisan queue:work --queue=default,broadcasts
```

### 4. Test WebSocket Connection
```bash
curl -X POST http://localhost:8000/api/v1/websocket/test \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "event_type": "agent_state",
    "data": {
      "agent_id": "123",
      "state": "Available"
    }
  }'
```

---

## Monitoring & Logging

All Mitel API interactions are logged in `storage/logs/laravel.log`:

```
[2024-11-28 10:30:00] production.INFO: Mitel API Request
[2024-11-28 10:30:00] production.INFO: Mitel Webhook Received
[2024-11-28 10:30:00] production.ERROR: Mitel API Error
```

---

## Security Considerations

1. **API Token Security** - Store Mitel API token securely in `.env`
2. **Webhook Verification** - Implement signature verification for webhooks
3. **Channel Authorization** - Define proper authorization rules in `channels.php`
4. **Rate Limiting** - Apply rate limits to prevent abuse
5. **HTTPS Only** - Use HTTPS for all Mitel API communications

---

## Support & Troubleshooting

### Common Issues

**Issue:** WebSocket not connecting
- Check broadcasting driver configuration
- Verify Pusher/Reverb credentials
- Ensure queue worker is running

**Issue:** Mitel API timeout
- Increase `MITEL_API_TIMEOUT` in `.env`
- Check network connectivity to Mitel server
- Verify API token validity

**Issue:** Events not broadcasting
- Confirm event classes implement `ShouldBroadcast`
- Check queue worker is processing jobs
- Verify channel authorization in `channels.php`

---

## Additional Resources

- [Mitel MiContact Center Web SDK Documentation](https://mitel.com)
- [Laravel Broadcasting Documentation](https://laravel.com/docs/11.x/broadcasting)
- [Laravel Echo Documentation](https://laravel.com/docs/11.x/broadcasting#client-side-installation)
- [Pusher Channels Documentation](https://pusher.com/docs/channels)
