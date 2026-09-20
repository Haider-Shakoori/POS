<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\FirstRunSetup;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function create(FirstRunSetup $setup): View|RedirectResponse
    {
        if (! $setup->required()) {
            return redirect()->route('login');
        }

        return view('setup.owner');
    }

    public function store(
        Request $request,
        FirstRunSetup $setup,
        AuditLogger $audit,
    ): RedirectResponse {
        if (! $setup->required()) {
            return redirect()->route('login');
        }

        $supportedLocales = array_keys(config('pos.locales', ['en' => []]));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('users', 'username'),
            ],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
            'preferred_locale' => ['required', Rule::in($supportedLocales)],
        ]);

        // Fresh installations may have been migrated without --seed.
        // All application seeders are idempotent reference-data seeders.
        app(DatabaseSeeder::class)->run();

        $owner = DB::transaction(function () use ($data, $audit): User {
            // Locking the owner role row serializes competing first-run
            // submissions so a second, unauthenticated setup cannot create
            // another owner before the first request commits.
            $ownerRole = Role::query()
                ->where('name', 'owner')
                ->lockForUpdate()
                ->first();

            if (! $ownerRole) {
                throw ValidationException::withMessages([
                    'setup' => __('ui.setup_owner_role_missing'),
                ]);
            }

            if (User::query()->exists()) {
                throw ValidationException::withMessages([
                    'setup' => __('ui.setup_already_completed'),
                ]);
            }

            $owner = User::create([
                'name' => trim($data['name']),
                'username' => trim($data['username']),
                'email' => filled($data['email'] ?? null) ? trim($data['email']) : null,
                'password' => $data['password'],
                'preferred_locale' => $data['preferred_locale'],
                'is_active' => true,
            ]);

            $owner->roles()->attach($ownerRole);

            $audit->record(
                'setup.owner.created',
                model: $owner,
                newValues: [
                    'name' => $owner->name,
                    'username' => $owner->username,
                    'email' => $owner->email,
                    'preferred_locale' => $owner->preferred_locale,
                    'role' => 'owner',
                ],
                actor: $owner,
            );

            return $owner;
        }, 3);

        $request->session()->put('locale', $owner->preferred_locale);

        return redirect()
            ->route('login')
            ->with('status', __('ui.setup_complete'));
    }
}