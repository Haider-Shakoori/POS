<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_cashier_can_login_and_access_pos(): void
    {
        $this->seed(DatabaseSeeder::class);

        $cashier = User::factory()->create([
            'username' => 'cashier',
            'password' => 'secret-password',
        ]);

        $cashier->roles()->attach(Role::query()->where('name', 'cashier')->firstOrFail());

        $response = $this->post('/login', [
            'username' => 'cashier',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($cashier);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $cashier->id,
            'event' => 'auth.login',
        ]);

        $this->withoutVite();
        $this->get('/pos')->assertOk();
    }

    public function test_user_without_pos_permission_is_forbidden(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'accountant')->firstOrFail());

        $this->actingAs($user)
            ->get('/pos')
            ->assertForbidden();

        $this->assertSame(0, AuditLog::query()->count());
    }
}
