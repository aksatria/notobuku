<?php

namespace App\Services\PustakawanDigital;

use Illuminate\Support\Facades\DB;

class AiAnalyticsService extends BaseService
{
    public function trackRequest(array $payload): void
    {
        try {
            DB::table('ai_analytics')->insert([
                'user_id' => $payload['user_id'] ?? null,
                'conversation_id' => $payload['conversation_id'] ?? null,
                'intent' => $payload['intent'] ?? null,
                'response_type' => $payload['response_type'] ?? null,
                'response_time_ms' => $payload['response_time_ms'] ?? null,
                'has_local_results' => (bool) ($payload['has_local_results'] ?? false),
                'ai_mode' => $payload['ai_mode'] ?? 'mock',
                'question' => $payload['question'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->log('Failed to track AI analytics', [
                'error' => $e->getMessage(),
            ], 'warning');
        }
    }
}
