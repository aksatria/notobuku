<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckAiSetup extends Command
{
    protected $signature = 'ai:check';
    protected $description = 'Check AI Pustakawan Digital setup';

    public function handle()
    {
        $this->info('🔍 Checking Pustakawan Digital Setup...');
        
        $checks = [
            'Database Tables' => $this->checkTables(),
            'Migrations Status' => $this->checkMigrations(),
            'Models Availability' => $this->checkModels(),
            'Services Directory' => $this->checkServices(),
        ];
        
        $this->table(['Component', 'Status'], $checks);
        
        if (in_array('FAIL', array_column($checks, 1))) {
            $this->error('❌ Some checks failed!');
            return 1;
        }
        
        $this->info('✅ All checks passed! Ready to create VIEW.');
        return 0;
    }
    
    private function checkTables(): array
    {
        try {
            $tables = ['ai_conversations', 'ai_messages', 'book_requests'];
            $results = [];
            
            foreach ($tables as $table) {
                $exists = Schema::hasTable($table);
                $results[] = [$table, $exists ? '✅ EXISTS' : '❌ MISSING'];
            }
            
            $this->table(['Table', 'Status'], $results);
            return ['All AI Tables', '✅ OK'];
        } catch (\Exception $e) {
            return ['Database Tables', '❌ ERROR: ' . $e->getMessage()];
        }
    }
    
    private function checkMigrations(): array
    {
        try {
            $migrations = DB::table('migrations')
                ->where('migration', 'like', '%ai%')
                ->orWhere('migration', 'like', '%book_request%')
                ->get();
            
            if ($migrations->count() === 3) {
                return ['Migrations', '✅ OK (3 records)'];
            }
            
            return ['Migrations', '⚠️ WARNING: ' . $migrations->count() . ' records'];
        } catch (\Exception $e) {
            return ['Migrations', '❌ ERROR'];
        }
    }
    
    private function checkModels(): array
    {
        $models = [
            'App\Models\AiConversation',
            'App\Models\AiMessage', 
            'App\Models\BookRequest',
        ];
        
        foreach ($models as $model) {
            if (!class_exists($model)) {
                return ['Models', '❌ MISSING: ' . $model];
            }
        }
        
        return ['Models', '✅ OK'];
    }
    
    private function checkServices(): array
    {
        $services = [
            'ChatService',
            'SearchService', 
            'ExternalApiService',
            'MockAiService',
            'RecommendationService',
            'SummaryService',
        ];
        
        foreach ($services as $service) {
            $class = 'App\Services\PustakawanDigital\\' . $service;
            if (!class_exists($class)) {
                return ['Services', '❌ MISSING: ' . $service];
            }
        }
        
        return ['Services', '✅ OK'];
    }
}