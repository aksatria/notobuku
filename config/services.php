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

    // ========================================
    // PUSTAKAWAN DIGITAL SERVICES
    // ========================================
    
    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen2.5:3b'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 30),
        'connect_timeout' => 10,
        'fallback_enabled' => true,
        'creative_mode' => true,
        'expert_prompt' => true,
        'freedom_level' => 'high',
        'temperature' => 0.85,
        'max_tokens' => 800,
        'retry_attempts' => 1,
        'retry_delay' => 100,
    ],
    
    'external' => [
        'google_books' => filter_var(env('GOOGLE_BOOKS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'open_library' => filter_var(env('OPEN_LIBRARY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'cache_ttl' => (int) env('AI_EXTERNAL_CACHE_TTL', 1800),
    ],
    
    'google_books' => [
        'api_key' => env('GOOGLE_BOOKS_API_KEY', ''),
    ],
    
    'pustakawan' => [
        'max_messages_per_conversation' => (int) env('AI_CONVERSATION_HISTORY', 50),
        'conversation_ttl_days' => 30,
        'search_results_limit' => 10,
        'recommendations_limit' => 5,
        'max_question_length' => (int) env('AI_MAX_QUESTION_LENGTH', 500),
        'max_response_length' => (int) env('AI_MAX_RESPONSE_LENGTH', 1000),
        'ai_only' => filter_var(env('PUSTAKAWAN_AI_ONLY', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'ai_features' => [
        'freedom_mode' => true,
        'expert_mode' => true,
        'unlimited_recommendations' => true,
        'global_literature' => true,
        'no_catalog_restrictions' => true,
        'literature_expert' => 'high',
        'creative_responses' => 0.85,
        'include_analysis' => true,
        'provide_context' => true,
        'immediate_fallback' => true,
        'graceful_degradation' => true,
        'always_available' => true,
    ],

    'ai_expertise' => [
        'knowledge_base' => 'expanded',
        'include_classics' => true,
        'include_bestsellers' => true,
        'include_contemporary' => true,
        'include_international' => true,
        'include_local' => true,
    ],

    'ai_ui' => [
        'welcome_mode' => 'expert',
        'followup_questions' => true,
        'context_awareness' => true,
        'personalized_recommendations' => true,
        'interactive_responses' => true,
        'provide_actions' => true,
        'show_ai_status' => true,
        'show_freedom_level' => true,
    ],

    'ai_catalog' => [
        'suggestions' => true,
        'highlight_available' => true,
        'suggest_unavailable' => true,
        'request_book_feature' => true,
        'show_alternatives' => true,
    ],

    'ai_security' => [
        'rate_limit' => 20,
        'rate_limit_duration' => 1,
        'max_conversations' => 20,
        'min_confidence' => 0.7,
    ],

    'ai_debug' => [
        'debug_mode' => true,
        'log_freedom_level' => true,
        'track_analytics' => true,
        'test_mode' => false,
        'use_mock_ai' => filter_var(env('AI_DEBUG_USE_MOCK_AI', false), FILTER_VALIDATE_BOOLEAN),
        'force_mock_responses' => filter_var(env('AI_DEBUG_FORCE_MOCK_RESPONSES', false), FILTER_VALIDATE_BOOLEAN),
        'skip_ollama_check' => filter_var(env('AI_DEBUG_SKIP_OLLAMA_CHECK', false), FILTER_VALIDATE_BOOLEAN),
        'log_all_responses' => true,
    ],

    'ai_cache' => [
        'ai_responses' => true,
        'search_results' => true,
        'duration_ai' => 300,
        'duration_search' => 300,
        'duration_external' => 1800,
    ],

    'ai_performance' => [
        'max_response_time' => 5,
        'min_response_time' => 0.5,
        'concurrent_requests' => 5,
        'queue_enabled' => false,
    ],

];
