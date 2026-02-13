<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Biblio;
use App\Models\AuthorityAuthor;
use App\Models\AuthoritySubject;
use App\Models\AuthorityPublisher;
use Illuminate\Support\Str;

function normalize_name(string $value): string {
    $v = strtolower(trim($value));
    $v = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $v);
    $v = preg_replace('/\s+/', ' ', $v);
    return trim($v);
}

$b = Biblio::with(['authors','subjects'])->find(562);
if (!$b) { echo "biblio not found\n"; exit; }

// Set relator to aut for all authors on this biblio
foreach ($b->authors as $author) {
    $b->authors()->updateExistingPivot($author->id, ['role' => 'aut']);
}

// Authority Author
$authorName = 'Marco Polo';
$authorNorm = normalize_name($authorName);
$authorUri = 'local://authority/author/marco-polo';
AuthorityAuthor::updateOrCreate(
    ['normalized_name' => $authorNorm],
    ['preferred_name' => $authorName, 'aliases' => [], 'external_ids' => ['uri' => $authorUri]]
);

// Authority Subjects
$subjects = [
    'Description and travel',
    'Voyages and travels',
    'History',
    'Mongols',
    'Early works to 1800',
    'Chinese Inscriptions',
];
foreach ($subjects as $term) {
    $norm = normalize_name($term);
    $uri = 'local://authority/subject/' . Str::slug($term);
    AuthoritySubject::updateOrCreate(
        ['scheme' => 'local', 'normalized_term' => $norm],
        ['preferred_term' => $term, 'aliases' => [], 'external_ids' => ['uri' => $uri]]
    );
}

// Authority Publisher
$publisher = 'Gramedia';
$pubNorm = normalize_name($publisher);
$pubUri = 'local://authority/publisher/gramedia';
AuthorityPublisher::updateOrCreate(
    ['normalized_name' => $pubNorm],
    ['preferred_name' => $publisher, 'aliases' => [], 'external_ids' => ['uri' => $pubUri]]
);

echo "done\n";
