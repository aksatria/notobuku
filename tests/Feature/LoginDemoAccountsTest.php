<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginDemoAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function forceLocalEnv(): void
    {
        $this->app['env'] = 'local';
        $this->app->detectEnvironment(fn() => 'local');
        config(['app.env' => 'local']);
        config(['notobuku.auto_seed_users_on_empty' => false]);
    }

    public function test_demo_accounts_visible_when_no_users_in_local(): void
    {
        $this->forceLocalEnv();

        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('Akun Demo');
        $response->assertSee('Isi Form');
    }

    public function test_demo_accounts_hidden_when_users_exist(): void
    {
        $this->forceLocalEnv();

        User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.test',
            'password' => Hash::make('secret'),
            'role' => 'member',
            'status' => 'active',
        ]);

        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertDontSee('Akun Demo');
        $response->assertDontSee('Isi Form');
    }
}
