<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions for order management
        $orderPermissions = [
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.delete',
            'orders.accept',
            'orders.reject',
            'orders.sync',
            'orders.export',
        ];

        // Create permissions for platform management
        $platformPermissions = [
            'platforms.view',
            'platforms.create',
            'platforms.update',
            'platforms.delete',
            'platforms.configure',
            'platforms.sync',
        ];

        // Create permissions for workflow management
        $workflowPermissions = [
            'workflows.view',
            'workflows.create',
            'workflows.update',
            'workflows.delete',
            'workflows.execute',
            'workflows.assign',
        ];

        // Create permissions for task management
        $taskPermissions = [
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.assign',
            'tasks.complete',
        ];

        // Create permissions for billing management
        $billingPermissions = [
            'billing.view',
            'billing.create',
            'billing.update',
            'billing.delete',
            'billing.process',
            'billing.refund',
        ];

        // Create permissions for return management
        $returnPermissions = [
            'returns.view',
            'returns.create',
            'returns.update',
            'returns.delete',
            'returns.approve',
            'returns.process',
        ];

        // Create permissions for user management
        $userPermissions = [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.impersonate',
        ];

        // Create permissions for role management
        $rolePermissions = [
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'roles.assign',
        ];

        // Create permissions for audit and security
        $auditPermissions = [
            'audit.view',
            'audit.export',
            'security.view',
            'security.configure',
        ];

        // Create permissions for system administration
        $systemPermissions = [
            'system.configure',
            'system.backup',
            'system.restore',
            'system.maintenance',
        ];

        // Combine all permissions
        $allPermissions = array_merge(
            $orderPermissions,
            $platformPermissions,
            $workflowPermissions,
            $taskPermissions,
            $billingPermissions,
            $returnPermissions,
            $userPermissions,
            $rolePermissions,
            $auditPermissions,
            $systemPermissions
        );

        // Create permissions
        foreach ($allPermissions as $permission) {
            \Spatie\Permission\Models\Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Super Admin - Full access to everything
        $superAdmin = \Spatie\Permission\Models\Role::create(['name' => 'super-admin']);
        $superAdmin->givePermissionTo($allPermissions);

        // Admin - Full access except system administration
        $admin = \Spatie\Permission\Models\Role::create(['name' => 'admin']);
        $adminPermissions = array_merge(
            $orderPermissions,
            $platformPermissions,
            $workflowPermissions,
            $taskPermissions,
            $billingPermissions,
            $returnPermissions,
            $userPermissions,
            $rolePermissions,
            $auditPermissions
        );
        $admin->givePermissionTo($adminPermissions);

        // Order Manager - Order and workflow management
        $orderManager = \Spatie\Permission\Models\Role::create(['name' => 'order-manager']);
        $orderManagerPermissions = array_merge(
            $orderPermissions,
            $workflowPermissions,
            $taskPermissions,
            ['platforms.view', 'platforms.sync'],
            ['audit.view']
        );
        $orderManager->givePermissionTo($orderManagerPermissions);

        // Warehouse Staff - Task execution and order processing
        $warehouseStaff = \Spatie\Permission\Models\Role::create(['name' => 'warehouse-staff']);
        $warehouseStaffPermissions = [
            'orders.view',
            'orders.update',
            'tasks.view',
            'tasks.complete',
            'workflows.execute',
            'billing.view',
            'billing.process',
            'returns.view',
            'returns.process',
        ];
        $warehouseStaff->givePermissionTo($warehouseStaffPermissions);

        // Customer Service - Order and return management
        $customerService = \Spatie\Permission\Models\Role::create(['name' => 'customer-service']);
        $customerServicePermissions = [
            'orders.view',
            'orders.update',
            'orders.accept',
            'orders.reject',
            'returns.view',
            'returns.create',
            'returns.update',
            'returns.approve',
            'tasks.view',
            'workflows.view',
        ];
        $customerService->givePermissionTo($customerServicePermissions);

        // Billing Clerk - Billing and financial operations
        $billingClerk = \Spatie\Permission\Models\Role::create(['name' => 'billing-clerk']);
        $billingClerkPermissions = array_merge(
            $billingPermissions,
            [
                'orders.view',
                'orders.update',
                'returns.view',
                'tasks.view',
                'tasks.complete',
            ]
        );
        $billingClerk->givePermissionTo($billingClerkPermissions);

        // Platform Manager - Platform integration management
        $platformManager = \Spatie\Permission\Models\Role::create(['name' => 'platform-manager']);
        $platformManagerPermissions = array_merge(
            $platformPermissions,
            [
                'orders.view',
                'orders.sync',
                'workflows.view',
                'audit.view',
            ]
        );
        $platformManager->givePermissionTo($platformManagerPermissions);

        // Auditor - Read-only access for audit and compliance
        $auditor = \Spatie\Permission\Models\Role::create(['name' => 'auditor']);
        $auditorPermissions = [
            'orders.view',
            'orders.export',
            'platforms.view',
            'workflows.view',
            'tasks.view',
            'billing.view',
            'returns.view',
            'users.view',
            'roles.view',
            'audit.view',
            'audit.export',
            'security.view',
        ];
        $auditor->givePermissionTo($auditorPermissions);

        // Viewer - Basic read-only access
        $viewer = \Spatie\Permission\Models\Role::create(['name' => 'viewer']);
        $viewerPermissions = [
            'orders.view',
            'platforms.view',
            'workflows.view',
            'tasks.view',
            'billing.view',
            'returns.view',
        ];
        $viewer->givePermissionTo($viewerPermissions);

        $this->command->info('Roles and permissions created successfully!');
    }
}
