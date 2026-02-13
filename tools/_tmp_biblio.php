<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$b = App\Models\Biblio::with(['authors','subjects'])->find(562);
if (!$b) {
    echo "notfound\n";
    exit(0);
}

echo $b->title . "\n";
$authors = $b->authors->pluck('name')->implode(', ');
$subjects = $b->subjects->pluck('term')->implode('; ');
echo "authors:" . $authors . "\n";
echo "subjects:" . $subjects . "\n";
