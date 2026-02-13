<?php

return [
    'auto_seed_users_on_empty' => env('NB_AUTO_SEED_USERS_ON_EMPTY', true),
    'allow_dangerous_db_commands' => env('NB_ALLOW_DANGEROUS_DB_COMMANDS', false),
    'interop' => [
        'rate_limit' => [
            'oai' => [
                'per_minute' => env('NB_OAI_RATE_PER_MINUTE', 180),
                'per_second' => env('NB_OAI_RATE_PER_SECOND', 12),
            ],
            'sru' => [
                'per_minute' => env('NB_SRU_RATE_PER_MINUTE', 240),
                'per_second' => env('NB_SRU_RATE_PER_SECOND', 20),
            ],
        ],
        'health_thresholds' => [
            'warning' => [
                'p95_ms' => env('NB_INTEROP_HEALTH_WARN_P95_MS', 1500),
                'invalid_token' => env('NB_INTEROP_HEALTH_WARN_INVALID_TOKEN', 20),
                'rate_limited' => env('NB_INTEROP_HEALTH_WARN_RATE_LIMITED', 20),
            ],
            'critical' => [
                'p95_ms' => env('NB_INTEROP_HEALTH_CRIT_P95_MS', 3000),
                'invalid_token' => env('NB_INTEROP_HEALTH_CRIT_INVALID_TOKEN', 50),
                'rate_limited' => env('NB_INTEROP_HEALTH_CRIT_RATE_LIMITED', 50),
            ],
        ],
        'alerts' => [
            'critical_streak_minutes' => env('NB_INTEROP_ALERT_CRITICAL_STREAK_MINUTES', 5),
            'cooldown_minutes' => env('NB_INTEROP_ALERT_COOLDOWN_MINUTES', 15),
            'email_to' => env('NB_INTEROP_ALERT_EMAIL_TO', ''),
            'webhook_url' => env('NB_INTEROP_ALERT_WEBHOOK_URL', ''),
        ],
        'db_retention_days' => env('NB_INTEROP_DB_RETENTION_DAYS', 120),
    ],
];
