<?php
$title = "Engineering Mechanics, Statics & Dynamics";
$lines = [
    "Ringkasan Contoh",
    "",
    "Dokumen ini berisi ringkasan singkat dan daftar isi contoh untuk kebutuhan demo katalog.",
    "",
    "Judul: $title",
    "Lampiran: Ringkasan + TOC",
    "Catatan: Isi ini hanya contoh, bukan konten asli buku.",
];
$text = '';
$y = 770;
foreach ($lines as $line) {
    $safe = str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $line);
    $text .= "72 $y Td ($safe) Tj\n0 -20 Td\n";
    $y -= 20;
}
$content = "BT\n/F1 14 Tf\n" . $text . "\nET";
$objects = [];
$objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
$objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj";
$objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj";
$objects[] = "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj";
$objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj";
$pdf = "%PDF-1.4\n";
$offsets = [];
foreach ($objects as $obj) {
    $offsets[] = strlen($pdf);
    $pdf .= $obj . "\n";
}
$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
$pdf .= "0000000000 65535 f \n";
foreach ($offsets as $off) {
    $pdf .= sprintf("%010d 00000 n \n", $off);
}
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";
file_put_contents("storage/app/public/attachments/engineering-mechanics-ringkasan.pdf", $pdf);
