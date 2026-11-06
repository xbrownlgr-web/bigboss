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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Dépôt public où sont publiés les APK (lien de téléchargement et numéro de version).
    'github' => [
        'releases_repo' => env('APK_RELEASES_REPO', 'gobifinancement-ui/big-boss'),
    ],

    'brevo' => [
        'key' => env('BREVO_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Alerte WhatsApp « nouvelle recharge/retrait » (WhatsAppCloudService) : uniquement ce
    // déclencheur, les autres alertes WhatsApp restent sur CallMeBot (WhatsAppNotifier).
    'whatsapp_cloud' => [
        'version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'token' => env('WHATSAPP_ACCESS_TOKEN'),
        'template' => env('WHATSAPP_TEMPLATE_NAME'),
        'admin_number' => env('ADMIN_WHATSAPP_NUMBER'),
    ],

];
