<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'fireworks' => [
        'api_key' => env('FIREWORKS_API_KEY'),
    ],

    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
    ],

    'jira' => [
        'app_id' => env('JIRA_APP_ID'),
        'client_id' => env('JIRA_CLIENT_ID'),
        'client_secret' => env('JIRA_CLIENT_SECRET'),
        'redirect_uri' => env('JIRA_REDIRECT_URI'),
    ],

    'slack' => [
        'app_id' => env('SLACK_APP_ID'),
        'client_id' => env('SLACK_CLIENT_ID'),
        'client_secret' => env('SLACK_CLIENT_SECRET'),
        'signing_secret' => env('SLACK_SIGNING_SECRET'),
        'base_url' => env('SLACK_BASE_URL'),
        'redirect_uri' => env('SLACK_REDIRECT_URI'),
    ],

    'linear' => [
        'api_token' => env('LINEAR_API_TOKEN'),
        'client_id' => env('LINEAR_CLIENT_ID'),
        'client_secret' => env('LINEAR_CLIENT_SECRET'),
        'redirect_uri' => env('LINEAR_REDIRECT_URL'),
    ],

    // this is for the API integration
    'github_integration' => [
        'base_url' => rtrim(env('GITHUB_BASE_URL'), '/'),
        'app_id' => env('GITHUB_APP_ID'),
        'app_name' => env('GITHUB_APP_NAME'),
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'private_key' => env('GITHUB_PRIVATE_KEY'),
    ],

    // this is for OAuth login
    // socialite requires a key called `github`
    'github' => [
        'app_id' => env('GITHUB_AUTH_APP_ID'),
        'client_id' => env('GITHUB_AUTH_CLIENT_ID'),
        'client_secret' => env('GITHUB_AUTH_CLIENT_SECRET'),
        'private_key' => env('GITHUB_AUTH_PRIVATE_KEY'),
        'redirect' => env('GITHUB_AUTH_REDIRECT_URL'),
    ],

    'google_chat' => [
        'client_id' => env('GOOGLE_CHAT_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CHAT_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_CHAT_OAUTH_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
        'project_id' => env('GOOGLE_CHAT_PROJECT_ID'),
        'auth_uri' => env('GOOGLE_CHAT_AUTH_URI'),
        'token_uri' => env('GOOGLE_CHAT_TOKEN_URI'),
        'auth_provider_x509_cert_url' => env('GOOGLE_CHAT_AUTH_PROVIDER_X509_CERT_URL'),
        'redirect_uris' => [env('GOOGLE_CHAT_REDIRECT_URI')],
    ],

    'google_drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_DRIVE_OAUTH_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
        'project_id' => env('GOOGLE_DRIVE_PROJECT_ID'),
        'auth_uri' => env('GOOGLE_DRIVE_AUTH_URI'),
        'token_uri' => env('GOOGLE_DRIVE_TOKEN_URI'),
        'auth_provider_x509_cert_url' => env('GOOGLE_DRIVE_AUTH_PROVIDER_X509_CERT_URL'),
        'redirect_uris' => [env('GOOGLE_DRIVE_REDIRECT_URI')],
    ],

    // this refers to the Socialite login provider
    'google' => [
        'client_id' => env('GOOGLE_AUTH_CLIENT_ID'),
        'client_secret' => env('GOOGLE_AUTH_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_AUTH_REDIRECT_URL'),
    ],
];
