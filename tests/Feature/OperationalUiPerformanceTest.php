<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalUiPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_access_completed_operational_admin_surfaces_and_dashboard_has_no_stale_batch_copy(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = $this->userWithRole('owner');

        $this->withoutVite();

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Ready for business')
            ->assertDontSee('Batch 8');

        $this->actingAs($owner)->get('/admin/users')->assertOk();
        $this->actingAs($owner)->get('/admin/audit-log')->assertOk();
        $this->actingAs($owner)->get('/settings/terminals')->assertOk();
        $this->actingAs($owner)->get('/expenses')->assertOk();
    }

    public function test_owner_can_create_user_and_action_is_audited(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = $this->userWithRole('owner');
        $cashierRole = Role::query()->where('name', 'cashier')->firstOrFail();

        $this->actingAs($owner)
            ->post('/admin/users', [
                'name' => 'Counter User',
                'username' => 'counter-user',
                'email' => 'counter@example.test',
                'password' => 'secure-password',
                'preferred_locale' => 'fa',
                'is_active' => '1',
                'role_ids' => [$cashierRole->id],
            ])
            ->assertRedirect();

        $created = User::query()->where('username', 'counter-user')->firstOrFail();

        $this->assertTrue($created->roles()->where('name', 'cashier')->exists());
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $owner->id,
            'event' => 'admin.user.created',
            'auditable_id' => $created->id,
        ]);
    }

    public function test_non_owner_administrator_cannot_assign_owner_role(): void
    {
        $this->seed(DatabaseSeeder::class);
        $administrator = $this->userWithRole('administrator');
        $ownerRole = Role::query()->where('name', 'owner')->firstOrFail();

        $this->actingAs($administrator)
            ->from('/admin/users')
            ->post('/admin/users', [
                'name' => 'Escalated User',
                'username' => 'escalated-user',
                'password' => 'secure-password',
                'preferred_locale' => 'en',
                'is_active' => '1',
                'role_ids' => [$ownerRole->id],
            ])
            ->assertRedirect('/admin/users')
            ->assertSessionHasErrors('role_ids');

        $this->assertDatabaseMissing('users', ['username' => 'escalated-user']);
    }

    public function test_terminal_management_is_audited(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)
            ->post('/settings/terminals', [
                'code' => 'counter-2',
                'name' => 'Counter 2',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('terminals', [
            'code' => 'COUNTER-2',
            'name' => 'Counter 2',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $owner->id,
            'event' => 'settings.terminal.created',
        ]);
    }

    public function test_permission_checks_reuse_loaded_authorization_relationships(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = $this->userWithRole('manager');
        $manager->unsetRelation('roles');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertTrue($manager->hasPermission('sales.view'));
        $queriesAfterFirstPermission = count(DB::getQueryLog());

        $this->assertTrue($manager->hasPermission('inventory.view'));
        $this->assertTrue($manager->hasPermission('reports.view'));
        $this->assertFalse($manager->hasPermission('users.manage'));

        $this->assertSame($queriesAfterFirstPermission, count(DB::getQueryLog()));

        DB::disableQueryLog();
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }
}
