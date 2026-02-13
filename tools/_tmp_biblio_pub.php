<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$b = App\Models\Biblio::with(['authors','subjects'])->find(562);
if (!$b) { echo "notfound\n"; exit; }
echo "publisher:" . ($b->publisher ?? '') . "\n";
foreach ($b->authors as $a) {
  echo "author:" . $a->name . " role:" . ($a->pivot->role ?? '') . "\n";
}
