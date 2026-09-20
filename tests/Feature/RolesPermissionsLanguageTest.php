<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesPermissionsLanguageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->owner = User::factory()->create([
            'preferred_locale' => 'en',
        ]);
        $this->owner->roles()->attach(
            Role::query()->where('name', 'owner')->firstOrFail()
        );
    }

    public function test_owner_can_create_role_assign_real_permissions_and_permission_checks_follow_assignment(): void
    {
        $reportsView = Permission::query()->where('name', 'reports.view')->firstOrFail();
        $reportsProfit = Permission::query()->where('name', 'reports.profit')->firstOrFail();

        $this->actingAs($this->owner)
            ->post(route('admin.roles.store'), [
                'name' => 'sales_supervisor',
                'label' => 'Sales Supervisor',
                'permission_ids' => [$reportsView->id, $reportsProfit->id],
            ])
            ->assertRedirect();

        $role = Role::query()
            ->with('permissions')
            ->where('name', 'sales_supervisor')
            ->firstOrFail();

        $this->assertSame(
            ['reports.profit', 'reports.view'],
            $role->permissions->pluck('name')->sort()->values()->all(),
        );

        $staff = User::factory()->create();
        $staff->roles()->attach($role);

        $this->assertTrue($staff->hasPermission('reports.view'));
        $this->assertTrue($staff->hasPermission('reports.profit'));
        $this->assertFalse($staff->hasPermission('sales.void'));

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $this->owner->id,
            'event' => 'admin.role.created',
            'auditable_id' => $role->id,
        ]);
    }

    public function test_owner_can_update_role_permissions_but_owner_role_is_immutable(): void
    {
        $role = Role::create([
            'name' => 'floor_lead',
            'label' => 'Floor Lead',
        ]);

        $salesView = Permission::query()->where('name', 'sales.view')->firstOrFail();
        $inventoryView = Permission::query()->where('name', 'inventory.view')->firstOrFail();

        $this->actingAs($this->owner)
            ->put(route('admin.roles.update', $role), [
                'label' => 'Floor Supervisor',
                'permission_ids' => [$salesView->id, $inventoryView->id],
            ])
            ->assertRedirect();

        $role->refresh()->load('permissions');

        $this->assertSame('Floor Supervisor', $role->label);
        $this->assertEqualsCanonicalizing(
            ['sales.view', 'inventory.view'],
            $role->permissions->pluck('name')->all(),
        );

        $ownerRole = Role::query()->where('name', 'owner')->firstOrFail();

        $this->actingAs($this->owner)
            ->from(route('admin.roles.index'))
            ->put(route('admin.roles.update', $ownerRole), [
                'label' => 'Changed Owner',
                'permission_ids' => [],
            ])
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHasErrors('role');

        $this->assertSame('Owner', $ownerRole->fresh()->label);
    }

    public function test_non_owner_cannot_manage_role_permission_matrix(): void
    {
        $administrator = User::factory()->create();
        $administrator->roles()->attach(
            Role::query()->where('name', 'administrator')->firstOrFail()
        );

        $this->assertTrue($administrator->hasPermission('users.manage'));

        $this->actingAs($administrator)
            ->get(route('admin.roles.index'))
            ->assertForbidden();

        $this->actingAs($administrator)
            ->post(route('admin.roles.store'), [
                'name' => 'forbidden_role',
                'label' => 'Forbidden Role',
                'permission_ids' => [],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'forbidden_role']);
    }

    public function test_custom_role_cannot_be_deleted_while_assigned_and_system_roles_are_protected(): void
    {
        $custom = Role::create([
            'name' => 'custom_counter',
            'label' => 'Custom Counter',
        ]);

        $staff = User::factory()->create();
        $staff->roles()->attach($custom);

        $this->actingAs($this->owner)
            ->from(route('admin.roles.index'))
            ->delete(route('admin.roles.destroy', $custom))
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $custom->id]);

        $cashier = Role::query()->where('name', 'cashier')->firstOrFail();

        $this->actingAs($this->owner)
            ->from(route('admin.roles.index'))
            ->delete(route('admin.roles.destroy', $cashier))
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $cashier->id]);
    }

    public function test_language_switcher_is_dropdown_with_all_languages_and_persists_selection(): void
    {
        $this->withoutVite();

        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-haspopup="menu"', false)
            ->assertSee('English')
            ->assertSee('دری')
            ->assertSee('پښتو');

        $this->actingAs($this->owner)
            ->post(route('locale.update', 'fa'))
            ->assertRedirect()
            ->assertSessionHas('locale', 'fa');

        $this->assertSame('fa', $this->owner->fresh()->preferred_locale);

        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lang="fa"', false)
            ->assertSee('dir="rtl"', false);
    }
}
