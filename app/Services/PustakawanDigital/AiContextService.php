<?php

namespace App\Services\PustakawanDigital;

use Illuminate\Support\Facades\DB;

class AiContextService extends BaseService
{
    public function getContextForUser(int $userId): array
    {
        $row = DB::table('ai_user_contexts')
            ->where('user_id', $userId)
            ->first();

        if (!$row) {
            return [
                'interests' => [],
                'recent_searches' => [],
                'preferred_response' => null,
            ];
        }

        return [
            'interests' => $this->decodeJson($row->interests),
            'recent_searches' => $this->decodeJson($row->recent_searches),
            'preferred_response' => $row->preferred_response,
        ];
    }

    public function updateFromQuestion(int $userId, string $question, string $intent): array
    {
        $context = $this->getContextForUser($userId);
        $keywords = $this->extractKeywords($question);

        $interests = $context['interests'] ?? [];
        foreach ($keywords as $kw) {
            if (!in_array($kw, $interests, true)) {
                $interests[] = $kw;
            }
        }
        $interests = array_slice($interests, -10);

        $recentSearches = $context['recent_searches'] ?? [];
        if (in_array($intent, ['search', 'recommend', 'hybrid', 'hybrid_free', 'ai_only', 'ask'], true)) {
            $recentSearches[] = $question;
        }
        $recentSearches = array_slice($recentSearches, -10);

        $preferredResponse = $context['preferred_response'];
        if ($preferredResponse === null && $intent === 'search') {
            $preferredResponse = 'search_first';
        }

        DB::table('ai_user_contexts')->updateOrInsert(
            ['user_id' => $userId],
            [
                'interests' => json_encode($interests),
                'recent_searches' => json_encode($recentSearches),
                'preferred_response' => $preferredResponse,
                'last_updated_at' => now(),
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        return [
            'interests' => $interests,
            'recent_searches' => $recentSearches,
            'preferred_response' => $preferredResponse,
        ];
    }

    private function decodeJson($value): array
    {
        if (empty($value)) {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
