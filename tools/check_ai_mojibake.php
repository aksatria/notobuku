<?php
use Illuminate\Support\Facades\DB;
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = DB::table('ai_messages')->where('content', 'like', '%Ã%')->count();
$sample = DB::table('ai_messages')->where('content', 'like', '%Ã%')->limit(1)->value('content');

echo "count={$count}\n";
if ($sample) { echo "sample=\n" . $sample . "\n"; }
