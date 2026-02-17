<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatalogModularControllersTest extends TestCase
{
    use RefreshDatabase;

    private function createInstitutionId(): int
    {
        return (int) DB::table('institutions')->insertGetId([
            'name' => 'Catalog Modular Test Institution',
            'code' => 'CAT-MOD-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUser(string $role, int $institutionId, string $email): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'institution_id' => $institutionId,
        ]);
    }

    private function seedBiblio(int $institutionId, string $title = 'Catalog Modular Title'): int
    {
        return (int) DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => $title,
            'material_type' => 'buku',
            'media_type' => 'teks',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_member_forbidden_for_create_edit_audit_and_maintenance_routes(): void
    {
        $institutionId = $this->createInstitutionId();
        $biblioId = $this->seedBiblio($institutionId);
        $member = $this->makeUser('member', $institutionId, 'member-catalog-mod@test.local');

        $this->actingAs($member)->get(route('katalog.create'))
            ->assertStatus(302)
            ->assertRedirect('/app');

        $this->actingAs($member)->get(route('katalog.edit', $biblioId))
            ->assertStatus(302)
            ->assertRedirect('/app');

        $this->actingAs($member)->get(route('katalog.audit', $biblioId))
            ->assertStatus(302)
            ->assertRedirect('/app');

        $this->actingAs($member)->get(route('katalog.audit.csv', $biblioId))
            ->assertStatus(302)
            ->assertRedirect('/app');

        $this->actingAs($member)->post(route('katalog.autofix', $biblioId))
            ->assertStatus(302)
            ->assertRedirect('/app');

        $this->actingAs($member)->delete(route('katalog.destroy', $biblioId))
            ->assertStatus(302)
            ->assertRedirect('/app');
    }

    public function test_admin_can_access_create_edit_and_audit_csv_routes(): void
    {
        $institutionId = $this->createInstitutionId();
        $biblioId = $this->seedBiblio($institutionId, 'Judul Modular Akses');
        $admin = $this->makeUser('admin', $institutionId, 'admin-catalog-mod@test.local');

        $this->actingAs($admin)->get(route('katalog.create'))
            ->assertOk();

        $this->actingAs($admin)->get(route('katalog.edit', $biblioId))
            ->assertOk()
            ->assertSee('Judul Modular Akses');

        $this->actingAs($admin)->get(route('katalog.audit.csv', $biblioId))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_can_destroy_biblio_without_items(): void
    {
        $institutionId = $this->createInstitutionId();
        $biblioId = $this->seedBiblio($institutionId, 'Judul Akan Dihapus');
        $admin = $this->makeUser('admin', $institutionId, 'admin-catalog-delete@test.local');

        $this->actingAs($admin)->delete(route('katalog.destroy', $biblioId))
            ->assertRedirect(route('katalog.index'));

        $this->assertDatabaseMissing('biblio', [
            'id' => $biblioId,
            'institution_id' => $institutionId,
        ]);
    }

    public function test_opac_shelves_preference_endpoint_updates_session(): void
    {
        $response = $this->post(route('opac.preferences.shelves'), [
            'enabled' => '1',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'enabled' => true,
            ]);
    }
}

