<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path = 'attachments/engineering-mechanics-ringkasan.pdf';
$full = __DIR__ . '/../storage/app/public/' . $path;
if (!file_exists($full)) {
    echo "FILE_NOT_FOUND";
    exit(1);
}
$size = filesize($full);
$att = App\Models\BiblioAttachment::create([
    'biblio_id' => 394,
    'title' => 'Ringkasan & Daftar Isi (Contoh)',
    'file_path' => $path,
    'file_name' => 'engineering-mechanics-ringkasan.pdf',
    'mime_type' => 'application/pdf',
    'file_size' => $size,
    'visibility' => 'public',
    'created_by' => 1,
]);

echo 'OK:' . $att->id;
