<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    private const PROTECTED_ROLE_NAMES = [
        'owner',
        'administrator',
        'manager',
        'cashier',
        'stock_keeper',
        'accountant',
    ];

    public function index(Request $request): View
    {
        $this->assertOwner($request);

        $permissions = Permission::query()
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', [
            'roles' => Role::query()
                ->with('permissions:id,name,label')
                ->withCount('users')
                ->orderByRaw("CASE WHEN name = 'owner' THEN 0 ELSE 1 END")
                ->orderBy('label')
                ->get(),
            'permissions' => $permissions,
            'permissionGroups' => $permissions->groupBy(
                fn (Permission $permission): string => str($permission->name)->before('.')->toString()
            ),
            'protectedRoleNames' => self::PROTECTED_ROLE_NAMES,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->assertOwner($request);

        $request->merge([
            'name' => strtolower(trim((string) $request->input('name'))),
        ]);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name'),
            ],
            'label' => ['required', 'string', 'max:120'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);

        $permissionIds = collect($data['permission_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        DB::transaction(function () use ($data, $permissionIds, $request, $audit): void {
            $role = Role::create([
                'name' => $data['name'],
                'label' => trim($data['label']),
            ]);

            $role->permissions()->sync($permissionIds->all());

            $audit->record(
                'admin.role.created',
                model: $role,
                newValues: [
                    'name' => $role->name,
                    'label' => $role->label,
                    'permissions' => Permission::query()
                        ->whereIn('id', $permissionIds)
                        ->orderBy('name')
                        ->pluck('name')
                        ->values()
                        ->all(),
                ],
                actor: $request->user(),
            );
        });

        return back()->with('status', __('ui.role_created'));
    }

    public function update(
        Request $request,
        Role $role,
        AuditLogger $audit,
    ): RedirectResponse {
        $this->assertOwner($request);
        $this->assertMutableRole($role);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);

        $role->loadMissing('permissions');

        $old = [
            'label' => $role->label,
            'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
        ];

        $permissionIds = collect($data['permission_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        DB::transaction(function () use ($role, $data, $permissionIds, $old, $request, $audit): void {
            $role->update(['label' => trim($data['label'])]);
            $role->permissions()->sync($permissionIds->all());

            $audit->record(
                'admin.role.updated',
                model: $role,
                oldValues: $old,
                newValues: [
                    'label' => $role->label,
                    'permissions' => Permission::query()
                        ->whereIn('id', $permissionIds)
                        ->orderBy('name')
                        ->pluck('name')
                        ->values()
                        ->all(),
                ],
                actor: $request->user(),
            );
        });

        return back()->with('status', __('ui.role_updated'));
    }

    public function destroy(
        Request $request,
        Role $role,
        AuditLogger $audit,
    ): RedirectResponse {
        $this->assertOwner($request);

        if (in_array($role->name, self::PROTECTED_ROLE_NAMES, true)) {
            throw ValidationException::withMessages([
                'role' => __('ui.protected_role_delete'),
            ]);
        }

        $role->loadMissing(['users:id', 'permissions:id,name']);

        if ($role->users->isNotEmpty()) {
            throw ValidationException::withMessages([
                'role' => __('ui.role_in_use'),
            ]);
        }

        DB::transaction(function () use ($role, $request, $audit): void {
            $audit->record(
                'admin.role.deleted',
                model: $role,
                oldValues: [
                    'name' => $role->name,
                    'label' => $role->label,
                    'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
                ],
                actor: $request->user(),
            );

            $role->delete();
        });

        return back()->with('status', __('ui.role_deleted'));
    }

    private function assertOwner(Request $request): void
    {
        abort_unless($request->user()?->hasRole('owner'), 403);
    }

    private function assertMutableRole(Role $role): void
    {
        if ($role->name === 'owner') {
            throw ValidationException::withMessages([
                'role' => __('ui.owner_role_immutable'),
            ]);
        }
    }
}
