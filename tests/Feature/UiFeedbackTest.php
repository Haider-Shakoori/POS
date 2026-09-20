<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Terminal;
use App\Models\User;
use App\Services\Cash\ShiftOpeningService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class UiFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Terminal $occupiedTerminal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());

        $this->occupiedTerminal = Terminal::query()->where('code', 'COUNTER-1')->firstOrFail();
    }

    public function test_cash_page_only_lists_terminals_without_open_shifts(): void
    {
        $this->withoutVite();

        Terminal::create([
            'code' => 'COUNTER-2',
            'name' => 'Counter 2',
            'is_active' => true,
        ]);

        app(ShiftOpeningService::class)->open([
            'idempotency_key' => (string) Str::uuid(),
            'terminal_id' => $this->occupiedTerminal->id,
            'opening_cash' => '0.00',
        ], $this->owner);

        $cashier = User::factory()->create(['preferred_locale' => 'en']);
        $cashier->roles()->attach(Role::query()->where('name', 'cashier')->firstOrFail());

        $this->actingAs($cashier)
            ->get(route('cash.index'))
            ->assertOk()
            ->assertSee('COUNTER-2')
            ->assertDontSee('COUNTER-1');
    }

    public function test_shift_open_domain_error_is_flashed_for_the_cash_page(): void
    {
        $this->withoutVite();

        app(ShiftOpeningService::class)->open([
            'idempotency_key' => (string) Str::uuid(),
            'terminal_id' => $this->occupiedTerminal->id,
            'opening_cash' => '0.00',
        ], $this->owner);

        $response = $this->actingAs($this->owner)
            ->from(route('cash.index'))
            ->post(route('cash.shifts.store'), [
                'idempotency_key' => (string) Str::uuid(),
                'terminal_id' => $this->occupiedTerminal->id,
                'opening_cash' => '0.00',
            ]);

        $response->assertRedirect(route('cash.index'));
        $response->assertSessionHasErrors('shift');

        $this->assertStringContainsString(
            "@error('shift')",
            file_get_contents(resource_path('views/cash/index.blade.php')),
        );
    }

    public function test_global_error_banner_renders_shared_errors_once(): void
    {
        $this->actingAs($this->owner);

        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'name' => 'The name field is required.',
        ])));

        $html = view('layouts.app')->render();

        $this->assertSame(1, substr_count($html, 'The name field is required.'));

        view()->share('errors', new ViewErrorBag);

        $this->assertSame(0, substr_count(view('layouts.app')->render(), 'The name field is required.'));
    }

    public function test_pages_declaring_page_errors_section_suppress_the_global_banner(): void
    {
        $this->actingAs($this->owner);

        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'name' => 'The name field is required.',
        ])));

        view()->startSection('page-errors', '1');

        $html = view('layouts.app')->render();

        $this->assertSame(0, substr_count($html, 'The name field is required.'));

        view()->flushSections();
    }
}
