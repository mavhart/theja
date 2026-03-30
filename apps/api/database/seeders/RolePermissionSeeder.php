<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Crea i ruoli e i permessi di sistema di Theja.
 * Deve essere eseguito DOPO le migration Spatie Permission.
 */
class RolePermissionSeeder extends Seeder
{
    /** Tutti i permessi di sistema. */
    private const PERMISSIONS = [
        'inventory.view_purchase_price',
        'inventory.view_other_pos_stock',
        'inventory.transfer_request',
        'patients.view_all_org',
        'patients.edit',
        'prescriptions.edit',
        'sales.create',
        'sales.apply_discount',
        'sales.view_payment_details',
        'cash_register.access',
        'orders.manage',
        'reports.view_pos',
        'reports.view_org_aggregate',
        'agenda.manage',
        'users.manage_pos',
    ];

    /** Permessi per ruolo. */
    private const ROLE_PERMISSIONS = [
        'super_admin' => self::PERMISSIONS,
        'org_owner' => [
            'inventory.view_purchase_price',
            'inventory.view_other_pos_stock',
            'inventory.transfer_request',
            'patients.view_all_org',
            'patients.edit',
            'prescriptions.edit',
            'sales.create',
            'sales.apply_discount',
            'sales.view_payment_details',
            'cash_register.access',
            'orders.manage',
            'reports.view_pos',
            'reports.view_org_aggregate',
            'agenda.manage',
            'users.manage_pos',
        ],
        'pos_manager' => [
            'inventory.view_purchase_price',
            'inventory.view_other_pos_stock',
            'inventory.transfer_request',
            'patients.view_all_org',
            'patients.edit',
            'prescriptions.edit',
            'sales.create',
            'sales.apply_discount',
            'sales.view_payment_details',
            'cash_register.access',
            'orders.manage',
            'reports.view_pos',
            'reports.view_org_aggregate',
            'agenda.manage',
            'users.manage_pos',
        ],
        'optician' => [
            'patients.view_all_org',
            'patients.edit',
            'prescriptions.edit',
            'reports.view_pos',
            'agenda.manage',
        ],
        'sales' => [
            'inventory.view_other_pos_stock',
            'inventory.transfer_request',
            'patients.view_all_org',
            'patients.edit',
            'sales.create',
            'sales.apply_discount',
            'sales.view_payment_details',
            'orders.manage',
            'reports.view_pos',
            'agenda.manage',
        ],
        'cashier' => [
            'cash_register.access',
            'sales.create',
            'sales.view_payment_details',
            'reports.view_pos',
        ],
    ];

    public function run(): void
    {
        // Crea tutti i permessi (guard: web)
        foreach (self::PERMISSIONS as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // Crea i ruoli e assegna i permessi
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }

        $this->command->info('Ruoli e permessi di sistema creati.');
    }
}
