<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Support\FirstRunSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(FirstRunSetup $setup): View|RedirectResponse
    {
        if ($setup->required()) {
            return redirect()->route('setup');
        }

        return view('auth.login');
    }

    public function store(
        Request $request,
        AuditLogger $audit,
        FirstRunSetup $setup,
    ): RedirectResponse {
        if ($setup->required()) {
            return redirect()->route('setup');
        }

        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->throttleKey($request, $credentials['username']);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()
                ->withErrors(['username' => __('ui.login_throttled', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ])])
                ->onlyInput('username');
        }

        if (! Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withErrors(['username' => __('ui.invalid_credentials')])
                ->onlyInput('username');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $audit->record('auth.login', actor: $request->user());

        return redirect()->intended(route('dashboard'));
    }

    private function throttleKey(Request $request, string $username): string
    {
        return 'login|'.mb_strtolower(trim($username)).'|'.$request->ip();
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        if ($request->user()) {
            $audit->record('auth.logout', actor: $request->user());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
