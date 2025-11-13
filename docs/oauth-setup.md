# OAuth Integration Setup Guide

This document explains how to configure OAuth integrations to properly receive refresh tokens.

## Google Drive & Google Chat

### Requirements

Google integrations require two parameters to consistently receive refresh tokens:

1. **`access_type=offline`** - Requests offline access (refresh token capability)
2. **`prompt=consent`** - Forces the consent screen to appear every time

### Current Status

✅ **CONFIGURED** - Both parameters are now included in `GoogleOAuthService::generateAuthorizationUrl()`

### Why This Matters

- **Without `prompt=consent`**: Google only returns a refresh token on the FIRST authorization
- **With `prompt=consent`**: Google returns a refresh token on EVERY authorization, including re-authorizations

### User Impact

Users will see the Google consent screen every time they connect, even if they've connected before. This is necessary to receive refresh tokens reliably.

### Re-authorizing Existing Users

Existing users who connected before this fix will NOT have refresh tokens. They need to:

1. Disconnect their integration
2. Re-connect using the new OAuth flow
3. They will then receive a refresh token

---

## Slack

### Requirements

Slack requires **Token Rotation** to be enabled in your Slack app configuration to receive refresh tokens.

### Current Status

⚠️ **ACTION REQUIRED** - Token rotation must be enabled in your Slack app settings

### How to Enable Token Rotation

1. Go to https://api.slack.com/apps
2. Select your Horizontal app
3. Navigate to **"OAuth & Permissions"**
4. Scroll to **"Token Rotation"** section
5. Click **"Opt into token rotation"**
6. Save changes

### Verification

After enabling token rotation:

1. Have a test user disconnect their Slack integration
2. Re-connect Slack
3. Check the database - `slack_integrations.refresh_token` should now be populated

### Why This Matters

- **Without token rotation**: Slack only provides long-lived access tokens that eventually expire
- **With token rotation**: Slack provides refresh tokens and shorter-lived access tokens

### Code Implementation

The code already supports Slack token refresh (see `SlackOAuthService::refreshAccessToken()`), but it won't work until token rotation is enabled in your Slack app configuration.

---

## Jira & Linear

### Status

✅ **WORKING** - These integrations are correctly configured and already receive refresh tokens.

### Jira Configuration

- Uses `prompt=consent` parameter ✅
- Includes `offline_access` scope ✅
- No changes needed

### Linear Configuration

- Linear's API returns refresh tokens by default ✅
- No special configuration needed

---

## Token Refresh Behavior

All integrations now have token managers that:

1. **Proactively refresh** - Tokens are refreshed 30 minutes before expiration
2. **On-demand refresh** - Tokens are checked before each API call
3. **Fallback handling** - 401 responses trigger refresh and retry
4. **Token rotation support** - Handles refresh token rotation when providers issue new ones

### Scheduled Jobs

Token refresh jobs run every 30 minutes via Laravel's scheduler:

```php
Schedule::job(new RefreshJiraTokensJob)->everyThirtyMinutes();
Schedule::job(new RefreshLinearTokensJob)->everyThirtyMinutes();
Schedule::job(new RefreshSlackTokensJob)->everyThirtyMinutes();
Schedule::job(new RefreshGoogleDriveTokensJob)->everyThirtyMinutes();
Schedule::job(new RefreshGoogleChatTokensJob)->everyThirtyMinutes();
```

---

## Identifying Integrations Without Refresh Tokens

### Manual Check

Run this query to find integrations missing refresh tokens:

```sql
-- Google Drive
SELECT id, user_email, expires_at, refresh_token
FROM google_drive_integrations
WHERE refresh_token IS NULL;

-- Slack
SELECT id, user_email, expires_at, refresh_token
FROM slack_integrations
WHERE refresh_token IS NULL;

-- Google Chat
SELECT id, user_email, expires_at, refresh_token
FROM google_chat_integrations
WHERE refresh_token IS NULL;
```

### Using Artisan Command

You can create an Artisan command to identify and report these:

```bash
php artisan integrations:check-refresh-tokens
```

This command will:
- Check all OAuth integrations
- Report which ones are missing refresh tokens
- Provide re-authorization instructions

---

## Migration Strategy for Existing Users

### Option 1: Proactive Migration

1. Identify all integrations without refresh tokens
2. Send email notifications to affected users
3. Display in-app banner prompting re-connection
4. Track re-authorization completion

### Option 2: Reactive Migration

1. Wait for tokens to expire naturally
2. When token expires and no refresh token exists:
   - Show error message to user
   - Prompt them to re-connect
   - They'll get a refresh token on re-connection

### Recommended Approach

Use **Option 1** (Proactive) for critical integrations like Slack and Google Drive, since these are frequently accessed and token expiration would disrupt workflows.

Use **Option 2** (Reactive) for less critical integrations.

---

## Testing

### Test Refresh Token Receipt

1. Clear any existing integration in your test environment
2. Go through the OAuth flow
3. Check the database to verify `refresh_token` is populated:

```sql
SELECT id, user_email, access_token IS NOT NULL as has_access,
       refresh_token IS NOT NULL as has_refresh, expires_at
FROM [integration_table]
WHERE id = [your_test_id];
```

### Test Token Refresh

1. Manually expire a token:
```sql
UPDATE google_drive_integrations
SET expires_at = NOW() - INTERVAL '1 hour'
WHERE id = [test_id];
```

2. Trigger an API call or run the refresh job manually:
```bash
php artisan queue:work --once
```

3. Verify the token was refreshed:
```sql
SELECT expires_at FROM google_drive_integrations WHERE id = [test_id];
-- expires_at should now be ~1 hour in the future
```

---

## Troubleshooting

### Google: "This app isn't verified" Warning

If users see this warning:
- This is normal for apps in development
- Users can click "Advanced" → "Go to [App Name] (unsafe)" to proceed
- For production, submit your app for Google verification

### Slack: Still Not Receiving Refresh Tokens

1. Verify token rotation is enabled in Slack app settings
2. Check that you're using the `/oauth/v2/authorize` endpoint (not the legacy endpoint)
3. Ensure your Slack app has the necessary scopes

### Token Refresh Failures

Check logs for errors:
```bash
php artisan pail # View real-time logs
```

Common issues:
- Invalid refresh token (user may need to re-authorize)
- Revoked access (user manually disconnected)
- App credentials changed
- Network issues with provider's token endpoint

---

## Additional Resources

- [Google OAuth 2.0 Documentation](https://developers.google.com/identity/protocols/oauth2)
- [Slack OAuth Documentation](https://api.slack.com/authentication/oauth-v2)
- [Slack Token Rotation Guide](https://api.slack.com/authentication/rotation)
- [Jira OAuth 2.0 (3LO)](https://developer.atlassian.com/cloud/jira/platform/oauth-2-3lo-apps/)
- [Linear OAuth Documentation](https://developers.linear.app/docs/oauth)
