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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'bot_sso_secret' => env('BOT_SSO_SECRET', ''),

    // Web chat Aorta: mesin balasan otomatis = bot-baileys (/engine/reply, ditandatangani
    // HMAC pakai BOT_SSO_SECRET). Riwayat chat disimpan di DB ini.
    'webchat' => [
        'bot_url' => env('WEBCHAT_BOT_URL', 'https://bot.aorta-edu.com'),
        // Base URL lampiran (PDF/gambar) untuk browser; kosong = sama dengan WEBCHAT_BOT_URL.
        'bot_public_url' => env('WEBCHAT_BOT_PUBLIC_URL', ''),
        // Nomor WhatsApp admin tujuan "Chat Admin via WhatsApp" (format bebas, mis. 0812... / 62812...)
        'admin_wa' => env('WEBCHAT_ADMIN_WA', ''),
        // Base URL web Aorta untuk link riwayat chat di pesan WA admin
        'site_url' => env('WEBCHAT_SITE_URL', 'https://aorta-edu.com'),
    ],

];
