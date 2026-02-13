<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$att = App\Models\BiblioAttachment::latest()->first();
$b = $att ? App\Models\Biblio::find($att->biblio_id) : null;
var_export([
  'att_id' => $att?->id,
  'biblio_id' => $att?->biblio_id,
  'institution_id' => $b?->institution_id,
  'file_path' => $att?->file_path,
]);
