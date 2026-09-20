<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();

        $roles = Role::query()
            ->withCount('users')
            ->orderBy('label')
            ->get();

        if (! $actor->hasRole('owner')) {
            $roles = $roles->reject(fn (Role $role): bool => $role->name === 'owner')->values();
        }

        return view('admin.users.index', [
            'users' => User::query()
                ->with('roles:id,name,label')
                ->when(
                    $request->filled('q'),
                    fn ($query) => $query->where(function ($builder) use ($request): void {
                        $term = '%'.trim((string) $request->query('q')).'%';
                        $builder->where('name', 'like', $term)
                            ->orWhere('username', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    })
                )
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'roles' => $roles,
            'isOwner' => $actor->hasRole('owner'),
        ]);
    }

    public function store(
        StoreUserRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validated();
        $roles = $this->resolveRoles($data['role_ids']);
        $this->assertOwnerAssignmentAllowed($request->user(), $roles);

        unset($data['role_ids']);

        DB::transaction(function () use ($data, $roles, $request, $audit): void {
            $user = User::create($data);
            $user->roles()->sync($roles->modelKeys());

            $audit->record(
                'admin.user.created',
                model: $user,
                newValues: [
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'preferred_locale' => $user->preferred_locale,
                    'is_active' => $user->is_active,
                    'roles' => $roles->pluck('name')->values()->all(),
                ],
                actor: $request->user(),
            );
        });

        return back()->with('status', __('ui.user_created'));
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        AuditLogger $audit,
    ): RedirectResponse {
        $actor = $request->user();

        if ($user->hasRole('owner') && ! $actor->hasRole('owner')) {
            abort(403);
        }

        $data = $request->validated();
        $roles = $this->resolveRoles($data['role_ids']);
        $this->assertOwnerAssignmentAllowed($actor, $roles);

        if ($actor->is($user) && ! $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => __('ui.cannot_deactivate_self'),
            ]);
        }

        $wasOwner = $user->hasRole('owner');
        $willBeOwner = $roles->contains('name', 'owner');

        if ($wasOwner && (! $willBeOwner || ! $data['is_active'])) {
            $activeOwnerCount = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->where('name', 'owner'))
                ->count();

            if ($activeOwnerCount <= 1) {
                throw ValidationException::withMessages([
                    'role_ids' => __('ui.last_owner_required'),
                ]);
            }
        }

        $user->loadMissing('roles');
        $old = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'preferred_locale' => $user->preferred_locale,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->values()->all(),
        ];

        $roleIds = $roles->modelKeys();
        unset($data['role_ids']);

        $passwordChanged = filled($data['password'] ?? null);
        if (! $passwordChanged) {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data, $roleIds, $roles, $old, $passwordChanged, $actor, $audit): void {
            $user->update($data);
            $user->roles()->sync($roleIds);
            $user->unsetRelation('roles');

            $audit->record(
                'admin.user.updated',
                model: $user,
                oldValues: $old,
                newValues: [
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'preferred_locale' => $user->preferred_locale,
                    'is_active' => $user->is_active,
                    'roles' => $roles->pluck('name')->values()->all(),
                    'password_changed' => $passwordChanged,
                ],
                actor: $actor,
            );
        });

        return back()->with('status', __('ui.user_updated'));
    }

    private function resolveRoles(array $roleIds): Collection
    {
        $ids = collect($roleIds)->map(fn ($id): int => (int) $id)->unique()->values();
        $roles = Role::query()->whereIn('id', $ids)->get();

        if ($roles->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'role_ids' => __('ui.invalid_role_selection'),
            ]);
        }

        return $roles;
    }

    private function assertOwnerAssignmentAllowed(User $actor, Collection $roles): void
    {
        if ($roles->contains('name', 'owner') && ! $actor->hasRole('owner')) {
            throw ValidationException::withMessages([
                'role_ids' => __('ui.owner_role_restricted'),
            ]);
        }
    }
}
