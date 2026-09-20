<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstRunSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_to_setup_when_no_users_exist(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get(route('login'))
            ->assertRedirect(route('setup'));

        $this->get(route('home'))
            ->assertRedirect(route('setup'));
    }

    public function test_setup_shows_only_when_no_users_exist(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->withoutVite()
            ->get(route('setup'))
            ->assertOk()
            ->assertSee(__('ui.create_initial_owner'));
    }

    public function test_setup_redirects_to_login_after_owner_exists(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::factory()->create(['preferred_locale' => 'en']);
        $owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());

        $this->get(route('setup'))->assertRedirect(route('login'));

        $this->post(route('setup.store'), $this->validPayload())
            ->assertRedirect(route('login'));

        $this->assertSame(1, User::query()->count());
    }

    public function test_owner_can_be_created_and_setup_closes_afterwards(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->withoutVite()
            ->post(route('setup.store'), $this->validPayload())
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $owner = User::query()->with('roles')->firstOrFail();

        $this->assertSame('Afghan Shop', $owner->name);
        $this->assertSame('firstowner', $owner->username);
        $this->assertSame('fa', $owner->preferred_locale);
        $this->assertTrue($owner->is_active);
        $this->assertTrue($owner->roles->contains('name', 'owner'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'setup.owner.created',
            'auditable_type' => User::class,
            'auditable_id' => $owner->id,
        ]);

        $this->get(route('setup'))->assertRedirect(route('login'));
    }

    public function test_second_unauthenticated_owner_cannot_be_created(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('setup.store'), $this->validPayload());
        $this->assertSame(1, User::query()->count());

        $this->post(route('setup.store'), $this->validPayload([
            'name' => 'Second Owner',
            'username' => 'secondowner',
        ]))->assertRedirect(route('login'));

        $this->assertSame(1, User::query()->count());
        $this->assertNull(User::query()->where('username', 'secondowner')->first());
    }

    public function test_setup_validates_inputs_before_creating_owner(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('setup.store'), $this->validPayload([
            'username' => 'not allowed!',
        ]))->assertSessionHasErrors('username');

        $this->post(route('setup.store'), $this->validPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertSessionHasErrors('password');

        $this->post(route('setup.store'), $this->validPayload([
            'password' => 'Different123!',
            'password_confirmation' => 'Mismatch123!',
        ]))->assertSessionHasErrors('password');

        $this->post(route('setup.store'), $this->validPayload([
            'preferred_locale' => 'de',
        ]))->assertSessionHasErrors('preferred_locale');

        $this->assertSame(0, User::query()->count());
    }

    public function test_duplicate_submission_after_completion_is_closed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('setup.store'), $this->validPayload());
        $this->assertSame(1, User::query()->count());

        $this->post(route('setup.store'), $this->validPayload())
            ->assertRedirect(route('login'));

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'setup.owner.created')->count());
    }

    public function test_setup_restores_missing_reference_data_before_creating_owner(): void
    {
        $this->seed(DatabaseSeeder::class);

        User::query()->delete();
        Role::query()->delete();

        $this->withoutVite()
            ->post(route('setup.store'), $this->validPayload())
            ->assertRedirect(route('login'));

        $owner = User::query()->with('roles')->firstOrFail();
        $this->assertTrue($owner->roles->contains('name', 'owner'));

        $this->assertGreaterThanOrEqual(
            6,
            Role::query()->count(),
            'Reference roles must be restored by the setup seeder.',
        );
    }

    public function test_cli_owner_command_remains_as_fallback(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('pos:create-owner', [
            '--name' => 'CLI Owner',
            '--username' => 'cliowner',
            '--password' => 'CliPass123!',
            '--locale' => 'ps',
        ])->assertSuccessful();

        $owner = User::query()->with('roles')->where('username', 'cliowner')->firstOrFail();
        $this->assertTrue($owner->roles->contains('name', 'owner'));
        $this->assertSame('ps', $owner->preferred_locale);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Afghan Shop',
            'username' => 'firstowner',
            'email' => 'owner@example.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'preferred_locale' => 'fa',
        ], $overrides);
    }
}