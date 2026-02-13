<?php

namespace App\Services\PustakawanDigital;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

abstract class BaseService
{
    protected function log(string $message, array $context = [], string $level = 'info')
    {
        $context['service'] = class_basename(static::class);
        
        switch ($level) {
            case 'debug':
                Log::debug($message, $context);
                break;
            case 'warning':
                Log::warning($message, $context);
                break;
            case 'error':
                Log::error($message, $context);
                break;
            case 'info':
            default:
                Log::info($message, $context);
                break;
        }
    }
    
    protected function cache(string $key, \Closure $callback, int $ttl = 3600)
    {
        $cacheKey = 'pustakawan_' . md5($key);
        
        return Cache::remember($cacheKey, $ttl, $callback);
    }
    
    protected function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        
        return $text;
    }
    
    protected function extractKeywords(string $text): array
    {
        $text = $this->normalizeText($text);
        
        $stopwords = [
            'yang', 'dengan', 'untuk', 'dari', 'dan', 'atau', 'di', 'ke', 'pada',
            'ada', 'ini', 'itu', 'saya', 'aku', 'kamu', 'dia', 'mereka', 'kita',
            'kami', 'anda', 'tentang', 'seperti', 'dalam', 'bisa', 'boleh', 'mau',
            'ingin', 'tolong', 'cari', 'carikan', 'mencari', 'pencarian', 'buku',
            'novel', 'referensi', 'materi', 'notobuku', 'perpustakaan', 'koleksi',
        ];
        
        $words = preg_split('/\s+/', $text);
        $keywords = array_diff($words, $stopwords);
        $keywords = array_filter($keywords, fn($w) => mb_strlen($w) >= 2);
        
        return array_values(array_unique($keywords));
    }
    
    protected function calculateSimilarity(string $text1, string $text2): float
    {
        $text1 = $this->normalizeText($text1);
        $text2 = $this->normalizeText($text2);
        
        if ($text1 === $text2) {
            return 1.0;
        }
        
        // Simple word overlap similarity
        $words1 = array_unique(preg_split('/\s+/', $text1));
        $words2 = array_unique(preg_split('/\s+/', $text2));
        
        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));
        
        if (empty($union)) {
            return 0.0;
        }
        
        return count($intersection) / count($union);
    }
}