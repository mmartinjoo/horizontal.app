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

    // TODO: merge with Google
    'google_drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
        'access_token' => env('GOOGLE_DRIVE_ACCESS_TOKEN'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'project_id' => env('GOOGLE_PROJECT_ID'),
        'auth_uri' => env('GOOGLE_AUTH_URI'),
        'token_uri' => env('GOOGLE_TOKEN_URI'),
        'auth_provider_x509_cert_url' => env('GOOGLE_AUTH_PROVIDER_X509_CERT_URL'),
        'redirect_uris' => ['https://tenant2-horizontal.loca.lt' . env('GOOGLE_REDIRECT_URI')],
    ],

    'jira' => [
        'app_id' => env('JIRA_APP_ID'),
        'client_id' => env('JIRA_CLIENT_ID'),
        'client_secret' => env('JIRA_CLIENT_SECRET'),
        'redirect_uri' => env('JIRA_REDIRECT_URI'),
    ],

    'slack' => [
        'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
        'base_url' => env('SLACK_BASE_URL'),
    ],

    'linear' => [
        'api_token' => env('LINEAR_API_TOKEN'),
        'client_id' => env('LINEAR_CLIENT_ID'),
        'client_secret' => env('LINEAR_CLIENT_SECRET'),
        'redirect_uri' => env('LINEAR_REDIRECT_URL'),
    ],

    'github' => [
        'base_url' => env('GITHUB_BASE_URL'),
        'app_id' => env('GITHUB_APP_ID'),
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'private_key' => env('GITHUB_PRIVATE_KEY'),
    ],
];
