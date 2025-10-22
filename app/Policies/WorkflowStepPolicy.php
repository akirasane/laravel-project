<?php

namespace App\Policies;

use App\Models\WorkflowStep;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkflowStepPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any workflow steps.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('workflows.view');
    }

    /**
     * Determine whether the user can view the workflow step.
     */
    public function view(User $user, WorkflowStep $workflowStep): bool
    {
        return $user->can('workflows.view');
    }

    /**
     * Determine whether the user can create workflow steps.
     */
    public function create(User $user): bool
    {
        return $user->can('workflows.create');
    }

    /**
     * Determine whether the user can update the workflow step.
     */
    public function update(User $user, WorkflowStep $workflowStep): bool
    {
        return $user->can('workflows.update');
    }

    /**
     * Determine whether the user can delete the workflow step.
     */
    public function delete(User $user, WorkflowStep $workflowStep): bool
    {
        return $user->can('workflows.delete');
    }

    /**
     * Determine whether the user can execute the workflow step.
     */
    public function execute(User $user, WorkflowStep $workflowStep): bool
    {
        // Check if user has general workflow execution permission
        if (!$user->can('workflows.execute')) {
            return false;
        }

        // Check if the step is assigned to a specific role
        if ($workflowStep->assigned_role) {
            return $user->hasRole($workflowStep->assigned_role);
        }

        return true;
    }

    /**
     * Determine whether the user can assign tasks for the workflow step.
     */
    public function assign(User $user, WorkflowStep $workflowStep): bool
    {
        return $user->can('workflows.assign') || $user->can('tasks.assign');
    }

    /**
     * Determine whether the user can restore the workflow step.
     */
    public function restore(User $user, WorkflowStep $workflowStep): bool
    {
        return $user->can('workflows.create');
    }

    /**
     * Determine whether the user can permanently delete the workflow step.
     */
    public function forceDelete(User $user, WorkflowStep $workflowStep): bool
    {
        return $user->can('workflows.delete') && $user->hasRole('super-admin');
    }
}
