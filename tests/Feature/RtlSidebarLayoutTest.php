<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RtlSidebarLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_shell_uses_direction_safe_sidebar_classes_in_ltr_and_rtl_locales(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::factory()->create([
            'preferred_locale' => 'en',
        ]);
        $owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());

        $this->withoutVite();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('app-sidebar', false)
            ->assertSee('app-sidebar-closed', false)
            ->assertDontSee('rtl:translate-x-full', false);

        $owner->update(['preferred_locale' => 'fa']);

        $this->actingAs($owner->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('app-sidebar', false)
            ->assertSee('app-sidebar-closed', false)
            ->assertDontSee('rtl:translate-x-full', false);
    }

    public function test_sidebar_css_forces_desktop_visibility_for_both_directions(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString(".app-sidebar-closed", $css);
        $this->assertStringContainsString("html[dir='rtl'] .app-sidebar-closed", $css);
        $this->assertStringContainsString('@media (min-width: 64rem)', $css);
        $this->assertStringContainsString('transform: translateX(0) !important;', $css);
    }
}
