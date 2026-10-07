<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'scraper' => [
        // Python interpreter that runs scrapers/*_scraper.py (e.g. "py", "python3" or a full path)
        'python' => env('SCRAPER_PYTHON', 'python'),
        // Local time of the daily scrape + import
        'daily_at' => env('SCRAPER_DAILY_AT', '06:00'),
        'timeout' => (int) env('SCRAPER_TIMEOUT', 1800),
        // Folder the scrapers save their <store>_products.csv files to before they are imported
        'output_dir' => env('SCRAPER_OUTPUT_DIR', base_path('scrapers')),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
