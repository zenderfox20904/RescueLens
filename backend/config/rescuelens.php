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

    // llama (default): Meta Llama open weights over an OpenAI compatible endpoint (local Ollama or a hosted Llama).
    // meta: Meta Model API. ollama: legacy DEV ONLY native Ollama provider, ignored when APP_ENV=production.
    'ai_provider' => env('AI_PROVIDER', 'llama'),
    'llama' => [
        // Local Ollama by default. For hosted Llama (Baseten, Modal, Fireworks) set the base URL, key and model id.
        'base_url' => env('LLAMA_BASE_URL', 'http://localhost:11434/v1'),
        'api_key' => env('LLAMA_API_KEY', ''),
        'model' => env('LLAMA_MODEL', 'llama3.2-vision'),
        // Name shown to users next to "Meta AI".
        'label' => env('LLAMA_MODEL_LABEL', 'Llama 3.2 Vision'),
        'timeout' => (int) env('LLAMA_TIMEOUT', 240),
    ],
    'dev_ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2-vision'),
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
