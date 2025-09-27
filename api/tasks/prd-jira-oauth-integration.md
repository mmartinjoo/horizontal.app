# PRD: Jira OAuth Integration

## 1. Introduction/Overview
This feature implements OAuth 2.0 authorization flow for Jira API integration, enabling team administrators to securely connect their Jira instances to Horizontal. Once authenticated, the system will be able to fetch issues, comments, projects, and user profiles from Jira on behalf of the team.

## 2. Goals
- Enable secure OAuth 2.0 authentication with Jira instances
- Provide team-level Jira integration (one OAuth connection per team)
- Support automatic token refresh for seamless API access
- Create a foundation for future Jira data fetching features
- Handle common OAuth errors gracefully

## 3. User Stories
- As a team administrator, I want to authorize our team's Jira instance so that Horizontal can access our project data
- As a team administrator, I want tokens to refresh automatically so that the integration continues working without manual intervention
- As a developer, I want clean API endpoints to initiate and handle OAuth flows so that I can build frontend integrations later
- As a system, I want to handle OAuth errors gracefully so that users receive clear feedback when authorization fails

## 4. Functional Requirements

### OAuth Flow Requirements
1. The system must implement OAuth 2.0 authorization code flow for Jira
2. The system must store OAuth tokens at the team level (not individual users)
3. The system must handle OAuth state parameter for CSRF protection
4. The system must support custom Jira instance URLs (e.g., company.atlassian.net)
5. The system must automatically refresh expired access tokens using refresh tokens
6. The system must provide callback URL handling for OAuth redirects

### API Endpoint Requirements
7. The system shall provide a POST endpoint to initiate OAuth flow
8. The system shall provide a GET endpoint to handle OAuth callbacks
9. The system shall provide a GET endpoint to check OAuth connection status
10. The system shall provide a DELETE endpoint to revoke OAuth connection
11. All endpoints must be scoped to the authenticated user's team

### Data Storage Requirements
12. The system must store access tokens, refresh tokens, and expiration times
13. The system must store the Jira instance URL for each team
14. The system must store OAuth state for validation
15. All tokens must be encrypted at rest

### Error Handling Requirements
16. The system must handle user denial of OAuth permissions
17. The system must handle network errors during OAuth flow
18. The system must handle invalid/expired refresh tokens
19. The system must log OAuth errors for debugging

## 5. Non-Goals (Out of Scope)
- Frontend UI for OAuth flow (API-only implementation)
- Actual Jira data fetching/caching (separate future task)
- User-level OAuth (only team-level)
- Support for Jira Server/Data Center (Atlassian Cloud only)
- Webhook handling from Jira
- Advanced error recovery mechanisms

## 6. Design Considerations
- Follow Laravel's existing Integration model pattern
- Use Laravel's built-in encryption for token storage
- Maintain consistency with existing multi-tenant architecture
- Ensure OAuth flow works with SPA frontends (handle CORS properly)

## 7. Technical Considerations

### Integration Points
- Extend existing `Integration` model or create `JiraIntegration` model
- Use existing team-scoping middleware patterns
- Integrate with Laravel's HTTP client for OAuth requests

### OAuth Scopes Required
- `read:jira-user` - Access user profile information
- `read:jira-work` - Read issues, comments, and projects

### Dependencies
- Laravel HTTP Client for OAuth API calls
- Laravel Encryption for token security
- Existing Team/User authentication system

### API Endpoints Structure
```
POST   /api/integrations/jira/oauth/authorize
GET    /api/integrations/jira/oauth/callback
GET    /api/integrations/jira/oauth/status
DELETE /api/integrations/jira/oauth/disconnect
```

### Database Schema
```sql
jira_integrations table:
- id
- jira_base_url (e.g., "https://company.atlassian.net")
- access_token (encrypted)
- refresh_token (encrypted)  
- expires_at
- scope
- created_at
- updated_at
```

## 8. Success Metrics
- OAuth flow completes successfully for valid Jira instances
- Tokens refresh automatically before expiration
- API endpoints return appropriate HTTP status codes
- Integration status can be checked reliably
- OAuth connections can be revoked cleanly

## 9. Open Questions
- Should we validate Jira instance URL format before OAuth flow?
- How long should we store revoked/expired integrations for audit purposes?
- Should we implement retry logic for failed token refresh attempts?
- Do we need to handle organization-level vs project-level permissions differently?

## 10. Implementation Notes
- Use environment variables for Jira OAuth client ID/secret configuration
- Implement proper OAuth state validation to prevent CSRF attacks
- Consider using Laravel's built-in rate limiting for OAuth endpoints
- Follow existing codebase patterns for exception handling and logging
