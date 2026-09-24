<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Terminal;
use App\Models\User;
use App\Services\Cash\ShiftOpeningService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosCheckoutFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_exposes_no_shift_checkout_feedback_before_cash_payment_is_submitted(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::factory()->create();
        $owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());

        $this->withoutVite();

        $this->actingAs($owner)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('hasOpenShift: false', false)
            ->assertSee('checkoutMessage', false)
            ->assertSee('requiresOpenShift()', false)
            ->assertSee(__('ui.no_open_shift_message'))
            ->assertSee(route('cash.index'), false);
    }

    public function test_pos_exposes_open_shift_state_after_cashier_shift_is_opened(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::factory()->create();
        $owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());

        app(ShiftOpeningService::class)->open([
            'idempotency_key' => (string) Str::uuid(),
            'terminal_id' => Terminal::query()->where('code', 'COUNTER-1')->firstOrFail()->id,
            'opening_cash' => '1000.00',
        ], $owner);

        $this->withoutVite();

        $this->actingAs($owner)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('hasOpenShift: true', false);
    }

    public function test_pos_uses_guarded_search_focus_and_cashier_shortcuts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::factory()->create();
        $owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());

        $this->withoutVite();

        $this->actingAs($owner)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('x-init="focusSearch()"', false)
            ->assertSee('@keydown.window="handleShortcut($event)"', false)
            ->assertSee('this.$refs?.search', false)
            ->assertDontSee('$refs.search.focus()', false)
            ->assertSee("event.key === 'F2'", false)
            ->assertSee("event.key === 'F8'", false)
            ->assertSee("event.key === 'F9'", false)
            ->assertSee("event.ctrlKey", false)
            ->assertSee("event.key === 'Enter'", false)
            ->assertSee('Shift+F9', false)
            ->assertSee('Ctrl+Enter', false);
    }

}
