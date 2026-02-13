<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MarcSettingsPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-MARC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::create([
            'institution_id' => $institutionId,
            'name' => 'Admin User',
            'username' => 'adminuser',
            'email' => 'admin@example.test',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public function test_meeting_ind1_requires_meeting_names(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)->postJson(route('admin.marc.settings.preview'), [
            'title' => 'Preview',
            'meeting_ind1' => '2',
        ]);

        $response->assertStatus(422);
    }

    public function test_force_meeting_main_ignored_when_meeting_names_empty(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)->postJson(route('admin.marc.settings.preview'), [
            'title' => 'Meeting Forced Main',
            'author' => 'Jane Doe',
            'author_role' => 'pengarang',
            'force_meeting_main' => true,
        ]);

        $response->assertStatus(200);

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($response->getContent()));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');
        $this->assertSame(0, $xpath->query('//marc:datafield[@tag="111"]')->length);
    }
}
