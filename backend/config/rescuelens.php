<?php

return [
    // Requests per minute per user or IP on public read endpoints. Raise only for load testing.
    'rate_limit_api' => (int) env('RATE_LIMIT_API_PER_MIN', 120),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'meta' => [
        'api_key' => env('META_MODEL_API_KEY'),
        'base_url' => rtrim(env('META_MODEL_API_BASE_URL', 'https://api.meta.ai/v1'), '/'),
        'vision_model' => env('META_VISION_MODEL', 'muse-spark-1.3'),
        'segmentation_model' => env('META_SEGMENTATION_MODEL', 'sam-3.1'),
        'timeout' => (int) env('AI_REQUEST_TIMEOUT', 45),
        'max_retries' => (int) env('AI_MAX_RETRIES', 2),
        'daily_budget' => (int) env('AI_DAILY_BUDGET', 500),
        'reasoning_effort' => env('AI_REASONING_EFFORT', 'high'),
        // Low temperature for consistent triage. Leave empty if the model rejects the parameter.
        'temperature' => env('AI_TEMPERATURE', '0.2'),
        'segmentation_enabled' => (bool) env('AI_ENABLE_SEGMENTATION', true),
    ],
    'public_location_decimals' => (int) env('RL_PUBLIC_LOCATION_DECIMALS', 3),
    'duplicate_radius_m' => (int) env('RL_DUPLICATE_RADIUS_M', 300),
    'duplicate_window_hours' => (int) env('RL_DUPLICATE_WINDOW_HOURS', 3),
    'stale_hours' => (int) env('RL_STALE_HOURS', 12),
    'upload_max_kb' => (int) env('RL_UPLOAD_MAX_KB', 8192),
    'max_image_pixels' => 40_000_000,
    'analysis_max_edge' => 1600,

    'webhooks' => [
        'urls' => env('RL_WEBHOOK_URLS', ''),
        'secret' => env('RL_WEBHOOK_SECRET', ''),
    ],

    // meta (default) | ollama (DEV ONLY, temporary, ignored when APP_ENV=production)
    'ai_provider' => env('AI_PROVIDER', 'meta'),
    'dev_ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'gemma4:31b-cloud'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 180),
    ],

    'camera' => [
        // Only https snapshot URLs on these hosts may be registered (comma separated). Empty means URL feeds are disabled.
        'allowed_hosts' => array_filter(array_map('trim', explode(',', (string) env('CAMERA_ALLOWED_HOSTS', '')))),
        'min_confidence' => (float) env('CAMERA_MIN_CONFIDENCE', 0.7),
        'feed_severities' => ['high', 'critical'],
        'max_bytes' => 8 * 1024 * 1024,
    ],
    'auto_post_undo_seconds' => 10,

    'facebook_app_id' => env('FACEBOOK_CLIENT_ID'),
];
