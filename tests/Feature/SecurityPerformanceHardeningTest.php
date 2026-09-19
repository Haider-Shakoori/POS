<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityPerformanceHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login|cashier|127.0.0.1');
    }

    public function test_web_responses_include_security_headers(): void
    {
        $this->withoutVite();

        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_login_is_throttled_after_repeated_failed_attempts_and_success_clears_counter(): void
    {
        $this->seed(DatabaseSeeder::class);

        $cashier = User::factory()->create([
            'username' => 'cashier',
            'password' => 'secret-password',
        ]);
        $cashier->roles()->attach(Role::query()->where('name', 'cashier')->firstOrFail());

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')
                ->post('/login', [
                    'username' => 'cashier',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect('/login');
        }

        $this->from('/login')
            ->post('/login', [
                'username' => 'cashier',
                'password' => 'secret-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username']);

        RateLimiter::clear('login|cashier|127.0.0.1');

        $this->post('/login', [
            'username' => 'cashier',
            'password' => 'secret-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($cashier);
        $this->assertSame(0, RateLimiter::attempts('login|cashier|127.0.0.1'));
    }
}
