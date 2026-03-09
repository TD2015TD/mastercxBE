# Auto Session Extension for Call Center Application

## Overview

This CRM application now includes automatic session extension for call center agents. As long as agents are actively using the system, their sessions will automatically extend without requiring manual re-authentication.

## How It Works

### 1. JWT Token-Based Authentication

The application uses JWT (JSON Web Tokens) for authentication with the following configuration:

- **Token Lifetime (JWT_TTL)**: 60 minutes by default
- **Refresh Window (JWT_REFRESH_TTL)**: 20,160 minutes (2 weeks) by default
- **Auto-Refresh Threshold**: 30 minutes (1,800 seconds) by default

### 2. Automatic Token Refresh Mechanism

The `AutoRefreshJwtToken` middleware automatically handles token refreshing:

#### When Does Auto-Refresh Happen?

The middleware checks every API request and refreshes the token when either:

1. **Token is close to expiring**: When the token has less than 30 minutes (configurable) remaining before expiration
2. **Token has just expired**: If a token expired very recently and is still within the refresh window

#### How Does It Work?

```
User makes API request → Middleware checks token expiry time
                       ↓
    Is token expiring in < 30 minutes?
                       ↓
              Yes → Auto-refresh token
                 → Send new token in response header
                 → Continue with request
                       ↓
              No → Continue with request normally
```

### 3. Client-Side Integration

When the frontend receives a response, it should check for these headers:

```javascript
// Example: Intercepting responses to get refreshed tokens
axios.interceptors.response.use(
  (response) => {
    // Check if token was refreshed
    if (response.headers['x-token-refreshed'] === 'true') {
      const newToken = response.headers['authorization'].replace('Bearer ', '');
      // Store the new token
      localStorage.setItem('access_token', newToken);
    }
    return response;
  },
  (error) => {
    return Promise.reject(error);
  }
);
```

### 4. Response Headers

When a token is auto-refreshed, the response includes:

- **Authorization**: `Bearer <new_token>` - The new JWT token
- **X-Token-Refreshed**: `true` - Indicates that the token was refreshed

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# JWT Token lifetime in minutes (default: 60)
JWT_TTL=60

# How long tokens can be refreshed in minutes (default: 20160 = 2 weeks)
JWT_REFRESH_TTL=20160

# Auto-refresh threshold in seconds (default: 1800 = 30 minutes)
# Tokens will auto-refresh when they have less than this time remaining
JWT_AUTO_REFRESH_THRESHOLD=1800

# Grace period for token blacklisting in seconds (default: 0)
# Prevents issues with concurrent requests during refresh
JWT_BLACKLIST_GRACE_PERIOD=10
```

### Adjusting for Different Use Cases

#### For Call Centers (Current Setup)
- **JWT_TTL**: 60 minutes - Short token lifetime for security
- **JWT_AUTO_REFRESH_THRESHOLD**: 1800 seconds (30 min) - Ensures tokens refresh well before expiration
- **JWT_REFRESH_TTL**: 20160 minutes (2 weeks) - Long refresh window

#### For Extended Sessions (Alternative)
If you want even longer initial tokens:
```env
JWT_TTL=240  # 4 hours
JWT_AUTO_REFRESH_THRESHOLD=3600  # Refresh when 1 hour remains
```

#### For High Security (Alternative)
If you want more frequent refreshes:
```env
JWT_TTL=30  # 30 minutes
JWT_AUTO_REFRESH_THRESHOLD=600  # Refresh when 10 minutes remains
```

## Implementation Details

### Files Modified/Created

1. **New Middleware**: [`app/Http/Middleware/AutoRefreshJwtToken.php`](app/Http/Middleware/AutoRefreshJwtToken.php)
   - Handles automatic token refresh logic
   - Checks token expiration time
   - Refreshes tokens when needed
   - Adds new token to response headers

2. **JWT Configuration**: [`config/jwt.php`](config/jwt.php)
   - Added `auto_refresh_threshold` configuration
   - Configurable via `JWT_AUTO_REFRESH_THRESHOLD` environment variable

3. **Middleware Registration**: [`bootstrap/app.php`](bootstrap/app.php)
   - Registered `jwt.auto.refresh` middleware alias
   - Maps to `AutoRefreshJwtToken` class

4. **Route Protection**: [`routes/api.php`](routes/api.php:87)
   - All protected routes now use both `jwt.verify` and `jwt.auto.refresh` middleware
   - Ensures automatic session extension for all authenticated endpoints

5. **Environment Configuration**: [`.env.example`](.env.example)
   - Added JWT configuration variables with documentation

## User Experience

### For Call Center Agents

1. **Login**: Agent logs in once at the start of their shift
2. **Work Continuously**: As long as they make API requests (viewing tickets, updating customer info, etc.)
3. **Automatic Extension**: Their session automatically extends without interruption
4. **No Manual Refresh Needed**: No need to click "refresh token" or re-login
5. **Seamless Experience**: Tokens refresh in the background transparently

### Session Expiration Scenarios

#### Active Agent
- Makes requests every few minutes
- Token auto-refreshes every ~30 minutes
- Can work entire shift without re-authentication

#### Inactive Agent
- Steps away from desk
- No API requests for 60+ minutes
- Token expires but can still be refreshed if within 2-week window
- If beyond 2 weeks: Must re-authenticate

#### After Logout
- Agent explicitly logs out
- Token is blacklisted immediately
- Must re-authenticate to access system

## Security Considerations

### Token Blacklisting

- Tokens are blacklisted on logout
- Old tokens cannot be reused after refresh
- Grace period prevents race conditions

### Grace Period

The `JWT_BLACKLIST_GRACE_PERIOD` setting (default: 10 seconds) prevents issues when:
- Multiple concurrent requests are in flight
- Token refresh happens during request processing
- Ensures smooth transition between old and new tokens

### Refresh Window

The 2-week refresh window (`JWT_REFRESH_TTL`) means:
- Agents can resume work within 2 weeks without re-login
- After 2 weeks of inactivity, must re-authenticate
- Balances convenience with security

## Testing

### Test Auto-Refresh Behavior

1. **Login and Get Token**
   ```bash
   curl -X POST http://localhost:8000/api/v1/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email":"agent@example.com","password":"password"}'
   ```

2. **Make Requests with Token**
   ```bash
   curl -X GET http://localhost:8000/api/v1/auth/me \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -v  # -v to see response headers
   ```

3. **Check Response Headers**
   - Look for `X-Token-Refreshed: true`
   - Look for new token in `Authorization` header

### Test Token Expiration

1. Set `JWT_TTL=1` (1 minute) in `.env`
2. Login and get token
3. Wait 30 seconds
4. Make a request
5. Should see `X-Token-Refreshed: true`

## Troubleshooting

### Tokens Not Refreshing

1. **Check middleware is applied**
   - Verify routes use `jwt.auto.refresh` middleware
   - Check [`routes/api.php`](routes/api.php:87)

2. **Check configuration**
   - Ensure `JWT_AUTO_REFRESH_THRESHOLD` is set
   - Verify `.env` has correct values

3. **Check token validity**
   - Token must be within refresh window
   - Check `JWT_REFRESH_TTL` setting

### Concurrent Request Issues

If you see errors during token refresh:
- Increase `JWT_BLACKLIST_GRACE_PERIOD`
- Default is 10 seconds, try 30 seconds

### Frontend Not Receiving New Tokens

1. **Check response headers**
   - Ensure frontend reads `Authorization` header
   - Look for `X-Token-Refreshed` flag

2. **Update axios interceptors**
   - Add response interceptor to capture new tokens
   - Store new tokens in localStorage/state

## Migration Guide

If you're updating an existing deployment:

1. **Update Environment Variables**
   ```bash
   # Add to .env file
   JWT_AUTO_REFRESH_THRESHOLD=1800
   JWT_BLACKLIST_GRACE_PERIOD=10
   ```

2. **Clear Token Cache** (if using Redis/cache)
   ```bash
   php artisan cache:clear
   ```

3. **Update Frontend Code**
   - Add response interceptor to capture refreshed tokens
   - See client-side integration example above

4. **Test Thoroughly**
   - Test login flow
   - Test auto-refresh behavior
   - Test concurrent requests
   - Test logout and token blacklisting

## Monitoring

### Logs to Monitor

The middleware will log errors for:
- Failed token refresh attempts
- Invalid tokens
- Expired tokens beyond refresh window

Check Laravel logs at `storage/logs/laravel.log`

### Metrics to Track

Consider monitoring:
- Token refresh frequency
- Token expiration rates
- Authentication failures
- Session duration analytics

## Future Enhancements

Potential improvements:

1. **Activity-Based Refresh**
   - Track user activity level
   - Adjust refresh frequency based on activity

2. **Configurable Refresh per Role**
   - Different refresh settings for different user roles
   - Admins vs agents vs supervisors

3. **Token Refresh Analytics**
   - Dashboard showing token refresh patterns
   - Identify issues with session management

4. **WebSocket Integration**
   - Push new tokens via WebSocket
   - Real-time session management

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Review middleware: [`app/Http/Middleware/AutoRefreshJwtToken.php`](app/Http/Middleware/AutoRefreshJwtToken.php)
- Check configuration: [`config/jwt.php`](config/jwt.php)
