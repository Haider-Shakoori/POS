<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'pos.access' => 'Access POS',
            'sales.view' => 'View sales',
            'sales.create' => 'Create sales',
            'sales.return' => 'Return sales',
            'sales.void' => 'Void sales',
            'sales.discount' => 'Apply sales discounts',
            'sales.override_min_price' => 'Sell below configured minimum price',
            'sales.credit' => 'Create customer credit sales',
            'sales.override_credit_limit' => 'Override customer credit limit',
            'purchases.view' => 'View purchases',
            'purchases.create' => 'Create purchases',
            'purchases.approve' => 'Approve and cancel purchase orders',
            'purchases.receive' => 'Receive approved purchase orders',
            'purchases.direct_receive' => 'Receive stock without a purchase order',
            'purchases.record_payment' => 'Record initial purchase payments',
            'inventory.view' => 'View inventory',
            'inventory.products.manage' => 'Create and manage products',
            'inventory.catalog.manage' => 'Manage categories, brands and units',
            'inventory.opening_stock' => 'Record opening stock',
            'inventory.adjust' => 'Adjust inventory',
            'inventory.count' => 'Perform stock counts',
            'inventory.count.approve' => 'Approve stock counts',
            'customers.view' => 'View customers',
            'customers.manage' => 'Manage customers',
            'customers.collect' => 'Record customer collections',
            'suppliers.view' => 'View suppliers',
            'suppliers.manage' => 'Manage suppliers',
            'suppliers.pay' => 'Record supplier payments',
            'expenses.view' => 'View expenses',
            'expenses.create' => 'Create expenses',
            'reports.view' => 'View reports',
            'reports.profit' => 'View profit reports',
            'shifts.open' => 'Open cashier shifts',
            'shifts.close' => 'Close cashier shifts',
            'shifts.reopen' => 'Reopen closed shifts',
            'settings.manage' => 'Manage shop settings',
            'users.manage' => 'Manage users and access',
            'audit.view' => 'View audit logs',
        ];

        foreach ($permissions as $name => $label) {
            Permission::query()->updateOrCreate(['name' => $name], ['label' => $label]);
        }

        $roles = [
            'owner' => 'Owner',
            'administrator' => 'Administrator',
            'manager' => 'Manager',
            'cashier' => 'Cashier',
            'stock_keeper' => 'Stock Keeper',
            'accountant' => 'Accountant',
        ];

        foreach ($roles as $name => $label) {
            Role::query()->updateOrCreate(['name' => $name], ['label' => $label]);
        }

        $all = Permission::query()->pluck('id');
        Role::query()->whereIn('name', ['owner', 'administrator'])->get()
            ->each(fn (Role $role) => $role->permissions()->sync($all));

        $assignments = [
            'manager' => [
                'pos.access', 'sales.view', 'sales.create', 'sales.return', 'sales.credit', 'sales.void',
                'sales.discount', 'sales.override_min_price', 'sales.credit', 'sales.override_credit_limit',
                'purchases.view', 'purchases.create', 'purchases.approve', 'purchases.receive',
                'purchases.direct_receive', 'purchases.record_payment',
                'inventory.view', 'inventory.products.manage', 'inventory.catalog.manage', 'inventory.opening_stock',
                'inventory.adjust', 'inventory.count', 'inventory.count.approve',
                'customers.view', 'customers.manage', 'customers.collect',
                'suppliers.view', 'suppliers.manage', 'suppliers.pay',
                'expenses.view', 'expenses.create', 'reports.view', 'reports.profit',
                'shifts.open', 'shifts.close',
            ],
            'cashier' => [
                'pos.access', 'sales.view', 'sales.create', 'sales.return',
                'customers.view', 'customers.collect', 'shifts.open', 'shifts.close',
            ],
            'stock_keeper' => [
                'purchases.view', 'purchases.create', 'purchases.receive',
                'inventory.view', 'inventory.products.manage', 'inventory.catalog.manage', 'inventory.opening_stock',
                'inventory.adjust', 'inventory.count', 'suppliers.view',
            ],
            'accountant' => [
                'sales.view', 'purchases.view', 'purchases.record_payment',
                'customers.view', 'customers.collect',
                'suppliers.view', 'suppliers.pay', 'expenses.view', 'expenses.create',
                'reports.view', 'reports.profit',
            ],
        ];

        foreach ($assignments as $roleName => $permissionNames) {
            $ids = Permission::query()->whereIn('name', $permissionNames)->pluck('id');
            Role::query()->where('name', $roleName)->firstOrFail()->permissions()->sync($ids);
        }
    }
}
