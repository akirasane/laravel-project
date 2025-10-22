<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can view the user.
     */
    public function view(User $user, User $model): bool
    {
        // Users can always view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        return $user->can('users.view');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Determine whether the user can update the user.
     */
    public function update(User $user, User $model): bool
    {
        // Users can always update their own profile (with restrictions)
        if ($user->id === $model->id) {
            return true;
        }

        // Prevent non-super-admins from updating super-admins
        if ($model->hasRole('super-admin') && !$user->hasRole('super-admin')) {
            return false;
        }

        return $user->can('users.update');
    }

    /**
     * Determine whether the user can delete the user.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Prevent non-super-admins from deleting super-admins
        if ($model->hasRole('super-admin') && !$user->hasRole('super-admin')) {
            return false;
        }

        return $user->can('users.delete');
    }

    /**
     * Determine whether the user can impersonate the user.
     */
    public function impersonate(User $user, User $model): bool
    {
        // Users cannot impersonate themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Prevent non-super-admins from impersonating super-admins
        if ($model->hasRole('super-admin') && !$user->hasRole('super-admin')) {
            return false;
        }

        // Prevent impersonating users with higher or equal privileges
        if (!$user->hasRole('super-admin')) {
            $userRoles = $user->getRoleNames();
            $modelRoles = $model->getRoleNames();
            
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

            $userMaxLevel = $userRoles->map(fn($role) => $roleHierarchy[$role] ?? 0)->max();
            $modelMaxLevel = $modelRoles->map(fn($role) => $roleHierarchy[$role] ?? 0)->max();

            if ($modelMaxLevel >= $userMaxLevel) {
                return false;
            }
        }

        return $user->can('users.impersonate');
    }

    /**
     * Determine whether the user can assign roles to the user.
     */
    public function assignRoles(User $user, User $model): bool
    {
        // Users cannot assign roles to themselves
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('roles.assign');
    }

    /**
     * Determine whether the user can restore the user.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->can('users.create');
    }

    /**
     * Determine whether the user can permanently delete the user.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->can('users.delete') && $user->hasRole('super-admin');
    }
}
