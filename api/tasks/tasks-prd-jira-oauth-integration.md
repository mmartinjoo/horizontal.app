# Tasks: Jira OAuth Integration

## Relevant Files

- `app/Models/JiraIntegration.php` - Model for storing Jira OAuth tokens and configuration
- `app/Services/Jira/JiraOAuthService.php` - Core OAuth 2.0 flow implementation
- `app/Services/Jira/JiraTokenManager.php` - Token refresh and expiration handling
- `app/Http/Controllers/JiraIntegrationController.php` - API endpoints for OAuth flow
- `app/Http/Requests/JiraOAuthRequest.php` - Request validation for OAuth endpoints
- `database/migrations/YYYY_MM_DD_create_jira_integrations_table.php` - Database migration
- `routes/api.php` - API route definitions
- `config/services.php` - Jira OAuth configuration

## Tasks

- [ ] 1.0 Database Setup and Migration
  - [x] 1.1 Create JiraIntegration model with fillable fields and relationships
  - [x] 1.2 Create migration for jira_integrations table with encrypted token fields
  - [x] 1.4 Add model factory for testing purposes
  - [x] 1.5 Run migration and verify table structure

- [ ] 2.0 OAuth Service Layer
  - [x] 2.1 Create JiraOAuthService with authorization URL generation
  - [x] 2.2 Implement OAuth state parameter generation and validation
  - [x] 2.3 Add authorization code exchange for access token
  - [x] 2.4 Implement Jira instance URL validation and normalization
  - [x] 2.5 Add OAuth scope configuration (read:jira-user, read:jira-work)
  - [x] 2.6 Create service provider binding for dependency injection

- [ ] 3.0 API Controller and Routes
  - [x] 3.1 Create JiraIntegrationController with team-scoped middleware
  - [x] 3.2 Implement POST /api/integrations/jira/oauth/authorize endpoint
  - [x] 3.3 Implement GET /api/integrations/jira/oauth/callback endpoint
  - [x] 3.4 Implement GET /api/integrations/jira/oauth/status endpoint
  - [x] 3.5 Implement DELETE /api/integrations/jira/oauth/disconnect endpoint
  - [x] 3.6 Add API routes with proper authentication middleware
  - [x] 3.7 Create request validation classes for OAuth parameters

- [ ] 4.0 Token Management System
  - [x] 4.1 Create JiraTokenManager for automatic token refresh logic
  - [x] 4.2 Implement token expiration checking and refresh workflow
  - [x] 4.3 Add encrypted storage and retrieval of access/refresh tokens
  - [x] 4.4 Handle refresh token rotation and secure storage
  - [x] 4.5 Add token revocation and cleanup functionality
  - [x] 4.6 Implement retry logic for failed token refresh attempts

- [ ] 5.0 Team-Level Integration Logic
  - [x] 5.1 Add team relationship to JiraIntegration model
  - [x] 5.2 Implement team-scoped queries and authorization checks
  - [x] 5.3 Ensure one integration per team constraint (unique index)
  - [x] 5.4 Add middleware for team-based access control
  - [x] 5.5 Handle team deletion scenarios (cascade or prevent)

## Configuration Requirements

### Environment Variables (.env)
```
JIRA_CLIENT_ID=your_jira_client_id
JIRA_CLIENT_SECRET=your_jira_client_secret
JIRA_REDIRECT_URI=http://localhost:8000/api/integrations/jira/oauth/callback
```

### Services Configuration (config/services.php)
```php
'jira' => [
    'client_id' => env('JIRA_CLIENT_ID'),
    'client_secret' => env('JIRA_CLIENT_SECRET'),
    'redirect_uri' => env('JIRA_REDIRECT_URI'),
    'scopes' => ['read:jira-user', 'read:jira-work'],
],
```

## Implementation Notes

- Use Laravel's `Crypt` facade for token encryption/decryption
- Implement proper OAuth state validation to prevent CSRF attacks
- Follow existing codebase patterns for exception handling
- Use Laravel's HTTP client with timeout and retry configuration
- Ensure all endpoints return consistent JSON API responses
- Add proper logging for OAuth flow debugging
- Consider rate limiting for OAuth endpoints to prevent abuse
- DON'T write or run tests
