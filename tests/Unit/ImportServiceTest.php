<?php

namespace Tests\Unit;

use App\Models\Biblio;
use App\Models\User;
use App\Services\ImportService;
use App\Services\MetadataMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_importCsv_create_and_update(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'institution_id' => $institutionId,
        ]);

        $csv = implode("\n", [
            'title,isbn,publisher,publish_year,authors,subjects,language',
            'Buku A,9786020000003,Penerbit 1,2020,Penulis A,Sains,id',
            'Buku A,9786020000003,Penerbit 2,2021,Penulis A,Sains,id',
        ]);

        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

        $service = new ImportService(new MetadataMappingService());
        $report = $service->importCsv($file, $institutionId, $user->id);

        $this->assertSame(1, $report['created']);
        $this->assertSame(1, $report['updated']);
        $this->assertSame(0, $report['skipped']);
        $this->assertEmpty($report['errors']);

        $biblio = Biblio::query()->where('institution_id', $institutionId)->where('isbn', '9786020000003')->first();
        $this->assertNotNull($biblio);
        $this->assertSame('Penerbit 2', $biblio->publisher);
    }
}
