<?php

namespace App\Services;

use App\Models\User;
use App\Models\AuditLog;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Collection;

class AuthorizationService
{
    /**
     * Assign a role to a user with audit logging.
     */
    public function assignRole(User $user, string $roleName, ?User $assignedBy = null): bool
    {
        try {
            $role = Role::findByName($roleName);
            
            if ($user->hasRole($roleName)) {
                return true; // Already has the role
            }
            
            $user->assignRole($role);
            
            // Log the role assignment
            AuditLog::log(
                'role_assigned',
                'authorization',
                "Role '{$roleName}' assigned to user: {$user->email}",
                $assignedBy,
                [
                    'target_user_id' => $user->id,
                    'target_user_email' => $user->email,
                    'role_name' => $roleName,
                    'role_id' => $role->id,
                ],
                'medium'
            );
            
            return true;
        } catch (\Exception $e) {
            // Log the failed assignment
            AuditLog::log(
                'role_assignment_failed',
                'authorization',
                "Failed to assign role '{$roleName}' to user: {$user->email}",
                $assignedBy,
                [
                    'target_user_id' => $user->id,
                    'target_user_email' => $user->email,
                    'role_name' => $roleName,
                    'error' => $e->getMessage(),
                ],
                'high'
            );
            
            return false;
        }
    }
    
    /**
     * Remove a role from a user with audit logging.
     */
    public function removeRole(User $user, string $roleName, ?User $removedBy = null): bool
    {
        try {
            if (!$user->hasRole($roleName)) {
                return true; // User doesn't have the role
            }
            
            $role = Role::findByName($roleName);
            $user->removeRole($role);
            
            // Log the role removal
            AuditLog::log(
                'role_removed',
                'authorization',
                "Role '{$roleName}' removed from user: {$user->email}",
                $removedBy,
                [
                    'target_user_id' => $user->id,
                    'target_user_email' => $user->email,
                    'role_name' => $roleName,
                    'role_id' => $role->id,
                ],
                'medium'
            );
            
            return true;
        } catch (\Exception $e) {
            // Log the failed removal
            AuditLog::log(
                'role_removal_failed',
                'authorization',
                "Failed to remove role '{$roleName}' from user: {$user->email}",
                $removedBy,
                [
                    'target_user_id' => $user->id,
                    'target_user_email' => $user->email,
                    'role_name' => $roleName,
                    'error' => $e->getMessage(),
                ],
                'high'
            );
            
            return false;
        }
    }
    
    /**
     * Grant a permission to a user with audit logging.
     */
    public function grantPermission(User $user, string $permissionName, ?User $grantedBy = null): bool
    {
        try {
            $permission = Permission::findByName($permissionName);
            
            if ($user->hasPermissionTo($permissionName)) {
                return true; // Already has the permission
            }
            
            $user->givePermissionTo($permission);
            
            // Log the permission grant
            AuditLog::log(
                'permission_granted',
                'authorization',
                "Permission '{$permissionName}' granted to user: {$user->email}",
                $grantedBy,
                [
                    'target_user_id' => $user->id,
                    'target_user_email' => $user->email,
                    'permission_name' => $permissionName,
                    'permission_id' => $permission->id,
                ],
                'medium'
            );
            
            return true;
        } catch (\Exception $e) {
            // Log the failed grant
            AuditLog::log(
                'permission_grant_failed',
                'authorization',
                "Failed to grant permission '{$permissionName}' to user: {$user->email}",
                $grantedBy,
                [
                    'target_user_id' => $user->id,
                    'target_user_email' => $user->email,
                    'permission_name' => $permissionName,
                    'error' => $e->getMessage(),
                ],
                'high'
            );
            
            return false;
        }
    }
    
    /**
     * Revoke a permission from a user with audit logging.
     */
    public function revokePermission(User $user, string $permissionName, ?User $revokedBy = null): bool
    {
        try {
            if (!$user->hasDirectPermission($permissionName)) {
                return true; // User doesn't have the direct permission
            }
            
            $permission = Permission::findByName($permissionName);
            $user->revokePermissionTo($permission);
            
            // Log the permission revocation
            AuditLog::log(
                'permission_revoked',
                'authorization',
                "Permission '{$permissionName}' revoked from user: {$user->email}",
                $revokedBy,
                [
                    'target_user_id' => $user->id,
                    'target_user_email' => $user->email,
                    'permission_name' => $permissionName,
                    'permission_id' => $permission->id,
                ],
                'medium'
            );
            
            return true;
        } catch (\Exception $e) {
            // Log the failed revocation
            AuditLog::log(
                'permission_revocation_failed',
                'authorization',
                "Failed to revoke permission '{$permissionName}' from user: {$user->email}",
                $revokedBy,
                [
                    'target_user_id' => $user->id,
                    'target_user_email' => $user->email,
                    'permission_name' => $permissionName,
                    'error' => $e->getMessage(),
                ],
                'high'
            );
            
            return false;
        }
    }
    
    /**
     * Get all available roles.
     */
    public function getAllRoles(): Collection
    {
        return Role::all();
    }
    
    /**
     * Get all available permissions.
     */
    public function getAllPermissions(): Collection
    {
        return Permission::all();
    }
    
    /**
     * Get permissions grouped by category.
     */
    public function getPermissionsByCategory(): array
    {
        $permissions = Permission::all();
        $grouped = [];
        
        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->name);
            $category = $parts[0] ?? 'other';
            
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            
            $grouped[$category][] = $permission;
        }
        
        return $grouped;
    }
    
    /**
     * Check if a user can perform an action on a resource.
     */
    public function canPerformAction(User $user, string $action, $resource = null): bool
    {
        // Check direct permission
        if ($user->can($action, $resource)) {
            return true;
        }
        
        // Log the authorization check for audit purposes
        AuditLog::log(
            'authorization_check',
            'authorization',
            "Authorization check for action '{$action}' - " . ($user->can($action, $resource) ? 'ALLOWED' : 'DENIED'),
            $user,
            [
                'action' => $action,
                'resource_type' => $resource ? get_class($resource) : null,
                'resource_id' => $resource && method_exists($resource, 'getKey') ? $resource->getKey() : null,
                'result' => $user->can($action, $resource) ? 'allowed' : 'denied',
            ],
            'low'
        );
        
        return false;
    }
    
    /**
     * Get user's effective permissions (including role-based permissions).
     */
    public function getUserEffectivePermissions(User $user): Collection
    {
        return $user->getAllPermissions();
    }
    
    /**
     * Sync user roles (remove all existing and assign new ones).
     */
    public function syncUserRoles(User $user, array $roleNames, ?User $syncedBy = null): bool
    {
        try {
            $oldRoles = $user->getRoleNames()->toArray();
            
            // Sync roles
            $user->syncRoles($roleNames);
            
            // Log the role sync
            AuditLog::log(
                'roles_synced',
                'authorization',
                "Roles synced for user: {$user->email}",
                $syncedBy,
                [
                    'target_user_id' => $user->id,
                    'target_user_email' => $user->email,
                    'old_roles' => $oldRoles,
                    'new_roles' => $roleNames,
                ],
                'medium'
            );
            
            return true;
        } catch (\Exception $e) {
            // Log the failed sync
            AuditLog::log(
                'role_sync_failed',
                'authorization',
                "Failed to sync roles for user: {$user->email}",
                $syncedBy,
                [
                    'target_user_id' => $user->id,
                    'target_user_email' => $user->email,
                    'attempted_roles' => $roleNames,
                    'error' => $e->getMessage(),
                ],
                'high'
            );
            
            return false;
        }
    }
    
    /**
     * Check if a role hierarchy is valid (prevent privilege escalation).
     */
    public function isValidRoleAssignment(User $assigningUser, string $roleName): bool
    {
        // Super admins can assign any role
        if ($assigningUser->hasRole('super-admin')) {
            return true;
        }
        
        // Define role hierarchy (higher number = more privileges)
        $roleHierarchy = [
            'viewer' => 1,
            'warehouse-staff' => 2,
            'customer-service' => 3,
            'billing-clerk' => 4,
            'platform-manager' => 5,
            'auditor' => 6,
            'order-manager' => 7,
            'admin' => 8,
            'super-admin' => 9,
        ];
        
        $assigningUserMaxLevel = $assigningUser->getRoleNames()
            ->map(fn($role) => $roleHierarchy[$role] ?? 0)
            ->max();
            
        $targetRoleLevel = $roleHierarchy[$roleName] ?? 0;
        
        // Users cannot assign roles with equal or higher privileges than their own
        return $targetRoleLevel < $assigningUserMaxLevel;
    }
}