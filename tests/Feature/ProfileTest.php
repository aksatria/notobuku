<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function createMemberUser(): User
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-PROFILE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::create([
            'name' => 'Member User',
            'email' => 'member@test.local',
            'password' => Hash::make('password'),
            'role' => 'member',
            'status' => 'active',
            'institution_id' => $institutionId,
        ]);

        DB::table('members')->insert([
            'institution_id' => $institutionId,
            'user_id' => $user->id,
            'member_code' => 'MBR-TEST-00001',
            'full_name' => $user->name,
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = $this->createMemberUser();

        $response = $this
            ->actingAs($user)
            ->get('/member/profil');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->createMemberUser();

        $response = $this
            ->actingAs($user)
            ->post('/member/profil', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'username' => 'test_user',
                'phone' => '08123456789',
                'address' => 'Alamat Test',
                'bio' => 'Bio singkat',
                'is_public' => 1,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/member/profil');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->createMemberUser();
        $user->email_verified_at = now();
        $user->save();

        $response = $this
            ->actingAs($user)
            ->post('/member/profil', [
                'name' => 'Member User',
                'email' => $user->email,
                'username' => 'member_user',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/member/profil');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_delete_profile_is_not_supported(): void
    {
        $user = $this->createMemberUser();

        $response = $this
            ->actingAs($user)
            ->delete('/member/profil', [
                'password' => 'password',
            ]);

        $response->assertStatus(405);
        $this->assertNotNull($user->fresh());
    }

    public function test_delete_profile_requires_supported_route(): void
    {
        $user = $this->createMemberUser();

        $response = $this
            ->actingAs($user)
            ->delete('/member/profil', [
                'password' => 'wrong-password',
            ]);

        $response->assertStatus(405);
        $this->assertNotNull($user->fresh());
    }
}
