<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookApiEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionWithBranch(string $code, ?int $id = null): array
    {
        $payload = [
            'name' => 'Institution ' . $code,
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($id !== null) {
            $payload['id'] = $id;
            DB::table('institutions')->insert($payload);
            $institutionId = $id;
        } else {
            $institutionId = DB::table('institutions')->insertGetId($payload);
        }

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-' . $code,
            'name' => 'Branch ' . $code,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeUser(int $institutionId, int $branchId): User
    {
        return User::create([
            'name' => 'API User',
            'email' => 'api-user@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    public function test_books_search_endpoint_returns_paginated_json_and_institution_scope(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionWithBranch('API-A');
        [$otherInstitutionId] = $this->seedInstitutionWithBranch('API-B');
        $user = $this->makeUser($institutionId, $branchId);
        $this->actingAs($user, 'sanctum');

        $hitId = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Pemrograman Laravel Modern',
            'publisher' => 'Notobuku Press',
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);
        DB::table('biblio')->insert([
            'institution_id' => $otherInstitutionId,
            'title' => 'Pemrograman Laravel Modern',
            'publisher' => 'Outside',
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        $resp = $this->getJson(route('api.v1.books.search', ['q' => 'Laravel', 'per_page' => 10]));
        $resp->assertOk();
        $resp->assertJsonPath('ok', true);
        $resp->assertJsonPath('meta.total', 1);
        $resp->assertJsonPath('data.0.id', $hitId);
        $resp->assertJsonPath('data.0.title', 'Pemrograman Laravel Modern');
    }

    public function test_books_show_endpoint_returns_detail_and_404_for_other_institution(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionWithBranch('API-C');
        [$otherInstitutionId] = $this->seedInstitutionWithBranch('API-D');
        $user = $this->makeUser($institutionId, $branchId);
        $this->actingAs($user, 'sanctum');

        $ownId = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Katalog API Detail',
            'isbn' => '9786020000001',
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);
        $otherId = DB::table('biblio')->insertGetId([
            'institution_id' => $otherInstitutionId,
            'title' => 'Katalog Luar',
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        $ok = $this->getJson(route('api.v1.books.show', ['id' => $ownId]));
        $ok->assertOk();
        $ok->assertJsonPath('ok', true);
        $ok->assertJsonPath('data.id', $ownId);
        $ok->assertJsonPath('data.isbn', '9786020000001');

        $forbiddenByScope = $this->getJson(route('api.v1.books.show', ['id' => $otherId]));
        $forbiddenByScope->assertNotFound();
    }
}
