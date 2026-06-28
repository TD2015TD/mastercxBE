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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mitel' => [
        'base_url'         => env('MITEL_BASE_URL', 'https://micc.telediant.com.my/miccsdk'),
        'auth_url'         => env('MITEL_AUTH_URL', 'https://micc.telediant.com.my/AuthorizationServer/Token'),
        'client_id'        => env('MITEL_CLIENT_ID', 'CXCRM'),
        'client_secret'    => env('MITEL_CLIENT_SECRET'),
        'timeout'          => env('MITEL_API_TIMEOUT', 30),
        'escalation_queue' => env('MITEL_ESCALATION_QUEUE', 'ChatbotEscalation'),
        'email_queue'      => env('MITEL_EMAIL_QUEUE', 'KASTAM-Email'),
    ],

    'elasticsearch' => [
        'host' => env('ELASTICSEARCH_HOST', 'localhost:9200'),
        'username' => env('ELASTICSEARCH_USERNAME'),
        'password' => env('ELASTICSEARCH_PASSWORD'),
    ],

    'superset' => [
        'url' => env('SUPERSET_URL', 'http://localhost:8088'),
        'username' => env('SUPERSET_USERNAME', ''),
        'password' => env('SUPERSET_PASSWORD', ''),
    ],

    'ai_chat' => [
        'url'                 => env('AI_CHAT_SERVICE_URL', 'http://localhost:8000'),
        'ws_url'              => env('AI_CHAT_SERVICE_WS_URL', 'ws://localhost:8000'),
        'customer_widget_url' => env('AI_CHAT_CUSTOMER_WIDGET_URL', 'https://chat.telediant.com.my/customer.html'),
    ],

    'chat' => [
        'agent_url' => env('CHAT_AGENT_BASE_URL', 'https://chat.telediant.com.my'),
    ],

];
