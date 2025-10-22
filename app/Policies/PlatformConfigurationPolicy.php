<?php

namespace App\Policies;

use App\Models\PlatformConfiguration;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlatformConfigurationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any platform configurations.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('platforms.view');
    }

    /**
     * Determine whether the user can view the platform configuration.
     */
    public function view(User $user, PlatformConfiguration $platformConfiguration): bool
    {
        return $user->can('platforms.view');
    }

    /**
     * Determine whether the user can create platform configurations.
     */
    public function create(User $user): bool
    {
        return $user->can('platforms.create');
    }

    /**
     * Determine whether the user can update the platform configuration.
     */
    public function update(User $user, PlatformConfiguration $platformConfiguration): bool
    {
        return $user->can('platforms.update');
    }

    /**
     * Determine whether the user can delete the platform configuration.
     */
    public function delete(User $user, PlatformConfiguration $platformConfiguration): bool
    {
        return $user->can('platforms.delete');
    }

    /**
     * Determine whether the user can configure platform settings.
     */
    public function configure(User $user, PlatformConfiguration $platformConfiguration): bool
    {
        return $user->can('platforms.configure');
    }

    /**
     * Determine whether the user can sync platform data.
     */
    public function sync(User $user, PlatformConfiguration $platformConfiguration): bool
    {
        return $user->can('platforms.sync') && $platformConfiguration->is_active;
    }

    /**
     * Determine whether the user can restore the platform configuration.
     */
    public function restore(User $user, PlatformConfiguration $platformConfiguration): bool
    {
        return $user->can('platforms.create');
    }

    /**
     * Determine whether the user can permanently delete the platform configuration.
     */
    public function forceDelete(User $user, PlatformConfiguration $platformConfiguration): bool
    {
        return $user->can('platforms.delete') && $user->hasRole('super-admin');
    }
}
