<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->can('orders.view');
    }

    /**
     * Determine whether the user can create orders.
     */
    public function create(User $user): bool
    {
        return $user->can('orders.create');
    }

    /**
     * Determine whether the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->can('orders.update');
    }

    /**
     * Determine whether the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->can('orders.delete');
    }

    /**
     * Determine whether the user can accept the order.
     */
    public function accept(User $user, Order $order): bool
    {
        return $user->can('orders.accept') && 
               in_array($order->status, ['pending', 'processing']);
    }

    /**
     * Determine whether the user can reject the order.
     */
    public function reject(User $user, Order $order): bool
    {
        return $user->can('orders.reject') && 
               in_array($order->status, ['pending', 'processing']);
    }

    /**
     * Determine whether the user can sync orders.
     */
    public function sync(User $user): bool
    {
        return $user->can('orders.sync');
    }

    /**
     * Determine whether the user can export orders.
     */
    public function export(User $user): bool
    {
        return $user->can('orders.export');
    }

    /**
     * Determine whether the user can restore the order.
     */
    public function restore(User $user, Order $order): bool
    {
        return $user->can('orders.create');
    }

    /**
     * Determine whether the user can permanently delete the order.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return $user->can('orders.delete') && $user->hasRole('super-admin');
    }
}
