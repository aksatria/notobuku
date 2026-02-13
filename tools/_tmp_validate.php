<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$b = App\Models\Biblio::with(['authors','subjects','identifiers'])->find(562);
$svc = new App\Services\MarcValidationService();
$messages = $svc->validateForExport($b);
foreach ($messages as $m) {
  if (str_contains($m, 'Authority')) {
    echo $m . "\n";
  }
  if (str_contains($m, 'Relator')) {
    echo $m . "\n";
  }
}
