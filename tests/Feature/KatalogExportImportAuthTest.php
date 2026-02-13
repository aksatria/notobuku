<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KatalogExportImportAuthTest extends TestCase
{
    use RefreshDatabase;

    private function createInstitutionId(): int
    {
        return DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-04',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUser(string $role, int $institutionId): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => "{$role}@test.local",
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'institution_id' => $institutionId,
        ]);
    }

    public function test_export_forbidden_for_member(): void
    {
        $institutionId = $this->createInstitutionId();
        $member = $this->makeUser('member', $institutionId);

        $response = $this->actingAs($member)->get('/katalog/export?format=csv');
        $response->assertStatus(302);
        $response->assertRedirect('/app');
    }

    public function test_import_forbidden_for_member(): void
    {
        $institutionId = $this->createInstitutionId();
        $member = $this->makeUser('member', $institutionId);

        $file = UploadedFile::fake()->createWithContent('import.csv', "title\nTest");

        $response = $this->actingAs($member)->post('/katalog/import', [
            'format' => 'csv',
            'file' => $file,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/app');
    }

    public function test_export_ok_for_admin(): void
    {
        $institutionId = $this->createInstitutionId();
        $admin = $this->makeUser('admin', $institutionId);

        $response = $this->actingAs($admin)->get('/katalog/export?format=csv');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
