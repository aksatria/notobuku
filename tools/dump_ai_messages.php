<?php
use Illuminate\Support\Facades\DB;
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = DB::table('ai_messages')->orderBy('id','desc')->limit(3)->get(['id','content']);
foreach ($rows as $row) {
    echo "#{$row->id}\n{$row->content}\n----\n";
}
