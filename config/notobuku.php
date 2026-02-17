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
                'p95_ms' => env('NB_INTEROP_HEALTH_WARN_P95_MS', 1000),
                'invalid_token' => env('NB_INTEROP_HEALTH_WARN_INVALID_TOKEN', 10),
                'rate_limited' => env('NB_INTEROP_HEALTH_WARN_RATE_LIMITED', 10),
            ],
            'critical' => [
                'p95_ms' => env('NB_INTEROP_HEALTH_CRIT_P95_MS', 1800),
                'invalid_token' => env('NB_INTEROP_HEALTH_CRIT_INVALID_TOKEN', 25),
                'rate_limited' => env('NB_INTEROP_HEALTH_CRIT_RATE_LIMITED', 25),
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
    'circulation' => [
        'sla' => [
            'exclude_weekends' => env('NB_CIRC_SLA_EXCLUDE_WEEKENDS', true),
        ],
        'health_thresholds' => [
            'warning' => [
                'p95_ms' => env('NB_CIRC_HEALTH_WARN_P95_MS', 1500),
                'failure_rate_pct' => env('NB_CIRC_HEALTH_WARN_FAILURE_RATE_PCT', 7),
            ],
            'critical' => [
                'p95_ms' => env('NB_CIRC_HEALTH_CRIT_P95_MS', 3000),
                'failure_rate_pct' => env('NB_CIRC_HEALTH_CRIT_FAILURE_RATE_PCT', 15),
            ],
        ],
        'alerts' => [
            'cooldown_minutes' => env('NB_CIRC_ALERT_COOLDOWN_MINUTES', 20),
            'email_to' => env('NB_CIRC_ALERT_EMAIL_TO', ''),
            'webhook_url' => env('NB_CIRC_ALERT_WEBHOOK_URL', ''),
        ],
        'exceptions' => [
            'overdue_extreme_days' => env('NB_CIRC_EXCEPTION_OVERDUE_EXTREME_DAYS', 30),
        ],
        'escalation' => [
            'warning_hours' => env('NB_CIRC_ESC_WARN_HOURS', 24),
            'critical_hours' => env('NB_CIRC_ESC_CRIT_HOURS', 72),
            'cooldown_minutes' => env('NB_CIRC_ESC_COOLDOWN_MINUTES', 60),
            'warning_email_to' => env('NB_CIRC_ESC_WARNING_EMAIL_TO', ''),
            'critical_email_to' => env('NB_CIRC_ESC_CRITICAL_EMAIL_TO', ''),
            'webhook_url' => env('NB_CIRC_ESC_WEBHOOK_URL', ''),
        ],
        'pic_reminder' => [
            'sla_hours' => env('NB_CIRC_PIC_REMINDER_SLA_HOURS', 24),
            'cooldown_minutes' => env('NB_CIRC_PIC_REMINDER_COOLDOWN_MINUTES', 120),
            'fallback_email_to' => env('NB_CIRC_PIC_REMINDER_FALLBACK_EMAIL_TO', ''),
        ],
        'unified' => [
            'enabled' => env('NB_CIRC_UNIFIED_ENABLED', true),
            'offline_queue_enabled' => env('NB_CIRC_UNIFIED_OFFLINE_QUEUE_ENABLED', true),
            'shortcuts_enabled' => env('NB_CIRC_UNIFIED_SHORTCUTS_ENABLED', true),
        ],
    ],
    'loans' => [
        'default_days' => env('NB_LOAN_DEFAULT_DAYS', 7),
        'max_items' => env('NB_LOAN_MAX_ITEMS', 3),
        'max_renewals' => env('NB_LOAN_MAX_RENEWALS', 2),
        'extend_days' => env('NB_LOAN_EXTEND_DAYS', 7),
        'fine_rate_per_day' => env('NB_LOAN_FINE_RATE_PER_DAY', 1000),
        'grace_days' => env('NB_LOAN_GRACE_DAYS', 0),
        'can_renew_if_reserved' => env('NB_LOAN_CAN_RENEW_IF_RESERVED', false),
        'roles' => [
            'member' => [
                'default_days' => env('NB_LOAN_MEMBER_DEFAULT_DAYS', 7),
                'max_items' => env('NB_LOAN_MEMBER_MAX_ITEMS', 3),
                'max_renewals' => env('NB_LOAN_MEMBER_MAX_RENEWALS', 2),
                'extend_days' => env('NB_LOAN_MEMBER_EXTEND_DAYS', 7),
                'fine_rate_per_day' => env('NB_LOAN_MEMBER_FINE_RATE_PER_DAY', 1000),
                'grace_days' => env('NB_LOAN_MEMBER_GRACE_DAYS', 0),
            ],
            'student' => [
                'default_days' => env('NB_LOAN_STUDENT_DEFAULT_DAYS', 7),
                'max_items' => env('NB_LOAN_STUDENT_MAX_ITEMS', 3),
                'max_renewals' => env('NB_LOAN_STUDENT_MAX_RENEWALS', 2),
                'extend_days' => env('NB_LOAN_STUDENT_EXTEND_DAYS', 7),
                'fine_rate_per_day' => env('NB_LOAN_STUDENT_FINE_RATE_PER_DAY', 1000),
                'grace_days' => env('NB_LOAN_STUDENT_GRACE_DAYS', 0),
            ],
            'staff' => [
                'default_days' => env('NB_LOAN_STAFF_DEFAULT_DAYS', 14),
                'max_items' => env('NB_LOAN_STAFF_MAX_ITEMS', 5),
                'max_renewals' => env('NB_LOAN_STAFF_MAX_RENEWALS', 3),
                'extend_days' => env('NB_LOAN_STAFF_EXTEND_DAYS', 7),
                'fine_rate_per_day' => env('NB_LOAN_STAFF_FINE_RATE_PER_DAY', 1000),
                'grace_days' => env('NB_LOAN_STAFF_GRACE_DAYS', 0),
            ],
        ],
    ],
    'reservations' => [
        'default_hold_hours' => env('NB_RESERVATION_DEFAULT_HOLD_HOURS', 48),
        'rule_default' => [
            'max_active_reservations' => env('NB_RESERVATION_MAX_ACTIVE', 5),
            'max_queue_per_biblio' => env('NB_RESERVATION_MAX_QUEUE_PER_BIBLIO', 30),
            'hold_hours' => env('NB_RESERVATION_HOLD_HOURS', 48),
        ],
        'auto_priority' => [
            'member_type_weights' => [
                'disabilitas' => (int) env('NB_RESERVATION_PRIORITY_DISABILITAS', 60),
                'dosen' => (int) env('NB_RESERVATION_PRIORITY_DOSEN', 30),
                'staff' => (int) env('NB_RESERVATION_PRIORITY_STAFF', 20),
                'member' => (int) env('NB_RESERVATION_PRIORITY_MEMBER', 0),
            ],
        ],
        'notification' => [
            'channels' => array_values(array_filter(array_map('trim', explode(',', (string) env('NB_RESERVATION_NOTIFY_CHANNELS', 'inapp,email'))))),
            'max_attempts' => (int) env('NB_RESERVATION_NOTIFY_MAX_ATTEMPTS', 5),
            'retry_base_minutes' => (int) env('NB_RESERVATION_NOTIFY_RETRY_BASE_MINUTES', 3),
            'whatsapp_webhook' => env('NB_RESERVATION_NOTIFY_WA_WEBHOOK', ''),
            'push_webhook' => env('NB_RESERVATION_NOTIFY_PUSH_WEBHOOK', ''),
            'fallback_email_to' => env('NB_RESERVATION_NOTIFY_FALLBACK_EMAIL', ''),
        ],
        'kpi' => [
            'window_days' => (int) env('NB_RESERVATION_KPI_WINDOW_DAYS', 30),
            'alert_fulfillment_min_pct' => (float) env('NB_RESERVATION_ALERT_FULFILLMENT_MIN_PCT', 70),
            'alert_backlog_max' => (int) env('NB_RESERVATION_ALERT_BACKLOG_MAX', 100),
            'alert_expiry_max_pct' => (float) env('NB_RESERVATION_ALERT_EXPIRY_MAX_PCT', 20),
            'alert_cooldown_minutes' => (int) env('NB_RESERVATION_ALERT_COOLDOWN_MINUTES', 30),
            'alert_email_to' => env('NB_RESERVATION_ALERT_EMAIL_TO', ''),
            'alert_webhook_url' => env('NB_RESERVATION_ALERT_WEBHOOK_URL', ''),
        ],
    ],
    'opac' => [
        'public_institution_id' => env('NB_OPAC_PUBLIC_INSTITUTION_ID', 1),
        'rate_limit' => [
            'search' => [
                'per_minute' => env('NB_OPAC_PUBLIC_SEARCH_RATE_PER_MINUTE', 120),
                'per_second' => env('NB_OPAC_PUBLIC_SEARCH_RATE_PER_SECOND', 8),
            ],
            'detail' => [
                'per_minute' => env('NB_OPAC_PUBLIC_DETAIL_RATE_PER_MINUTE', 180),
                'per_second' => env('NB_OPAC_PUBLIC_DETAIL_RATE_PER_SECOND', 12),
            ],
            'seo' => [
                'per_minute' => env('NB_OPAC_PUBLIC_SEO_RATE_PER_MINUTE', 30),
                'per_second' => env('NB_OPAC_PUBLIC_SEO_RATE_PER_SECOND', 2),
            ],
            'write' => [
                'per_minute' => env('NB_OPAC_PUBLIC_WRITE_RATE_PER_MINUTE', 20),
                'per_second' => env('NB_OPAC_PUBLIC_WRITE_RATE_PER_SECOND', 2),
            ],
        ],
        'sitemap' => [
            'max_urls' => env('NB_OPAC_SITEMAP_MAX_URLS', 5000),
            'cache_seconds' => env('NB_OPAC_SITEMAP_CACHE_SECONDS', 900),
        ],
        'prefetch' => [
            'enabled' => env('NB_OPAC_PREFETCH_ENABLED', true),
            'top_queries' => env('NB_OPAC_PREFETCH_TOP_QUERIES', 6),
        ],
        'slo' => [
            'availability_target_pct' => env('NB_OPAC_SLO_AVAILABILITY_TARGET_PCT', 99.5),
            'latency_budget_ms' => env('NB_OPAC_SLO_LATENCY_BUDGET_MS', 800),
            'burn_rate_warning' => env('NB_OPAC_SLO_BURN_RATE_WARNING', 1.5),
            'burn_rate_critical' => env('NB_OPAC_SLO_BURN_RATE_CRITICAL', 3.0),
            'alert_cooldown_minutes' => env('NB_OPAC_SLO_ALERT_COOLDOWN_MINUTES', 15),
            'alert_email_to' => env('NB_OPAC_SLO_ALERT_EMAIL_TO', ''),
            'alert_webhook_url' => env('NB_OPAC_SLO_ALERT_WEBHOOK_URL', ''),
        ],
    ],
    'backup' => [
        'core_tables' => [
            'institutions',
            'branches',
            'biblio',
            'items',
            'members',
            'loans',
            'loan_items',
            'serial_issues',
            'search_queries',
            'search_synonyms',
        ],
        'snapshot_dir' => env('NB_BACKUP_SNAPSHOT_DIR', 'backups/core'),
        'retain_files' => env('NB_BACKUP_RETAIN_FILES', 30),
    ],
    'catalog' => [
        'quality_gate' => [
            'enabled' => env('NB_CATALOG_QUALITY_GATE_ENABLED', true),
        ],
        'ops_email_to' => env('NB_CATALOG_OPS_EMAIL_TO', ''),
        'zero_result_governance' => [
            'enabled' => env('NB_SEARCH_ZERO_GOV_ENABLED', true),
            'limit' => env('NB_SEARCH_ZERO_GOV_LIMIT', 500),
            'min_search_count' => env('NB_SEARCH_ZERO_GOV_MIN_SEARCH_COUNT', 2),
            'age_hours' => env('NB_SEARCH_ZERO_GOV_AGE_HOURS', 24),
            'force_close_open' => env('NB_SEARCH_ZERO_GOV_FORCE_CLOSE_OPEN', true),
        ],
    ],
    'readiness' => [
        'minimum_traffic' => [
            'opac_searches' => env('NB_READINESS_MIN_OPAC_SEARCHES', 200),
            'interop_points' => env('NB_READINESS_MIN_INTEROP_POINTS', 240),
            'scale_samples' => env('NB_READINESS_MIN_SCALE_SAMPLES', 60),
        ],
    ],
    'uat' => [
        'dir' => env('NB_UAT_DIR', 'uat/checklists'),
        'auto_signoff' => [
            'enabled' => env('NB_UAT_AUTO_SIGNOFF_ENABLED', true),
            'strict_ready' => env('NB_UAT_AUTO_SIGNOFF_STRICT_READY', true),
            'operator' => env('NB_UAT_AUTO_SIGNOFF_OPERATOR', 'SYSTEM AUTO'),
        ],
    ],
];
