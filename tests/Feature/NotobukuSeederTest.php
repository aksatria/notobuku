<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\NotobukuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotobukuSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_does_not_override_existing_user_passwords(): void
    {
        $customPassword = 'my-secret-pass';
        $customHash = Hash::make($customPassword);

        User::create([
            'name' => 'Custom Super Admin',
            'email' => 'adhe5381@gmail.com',
            'username' => 'custom_admin',
            'password' => $customHash,
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->seed(NotobukuSeeder::class);

        $user = User::where('email', 'adhe5381@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check($customPassword, $user->password));
        $this->assertSame('custom_admin', $user->username);
    }

    public function test_seeder_does_not_override_member_passwords(): void
    {
        $customPassword = 'member-secret';
        $customHash = Hash::make($customPassword);

        User::create([
            'name' => 'Custom Member',
            'email' => 'member@notobuku.test',
            'username' => 'custom_member',
            'password' => $customHash,
            'role' => 'member',
            'status' => 'active',
        ]);

        $this->seed(NotobukuSeeder::class);

        $user = User::where('email', 'member@notobuku.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check($customPassword, $user->password));
        $this->assertSame('custom_member', $user->username);
    }
}
