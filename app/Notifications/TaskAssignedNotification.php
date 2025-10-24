<?php

namespace App\Notifications;

use App\Models\TaskAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected TaskAssignment $taskAssignment;

    public function __construct(TaskAssignment $taskAssignment)
    {
        $this->taskAssignment = $taskAssignment;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Task Assigned: ' . $this->taskAssignment->workflowStep->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You have been assigned a new task in the order management system.')
            ->line('**Task:** ' . $this->taskAssignment->workflowStep->name)
            ->line('**Order:** ' . $this->taskAssignment->order->platform_order_id)
            ->line('**Customer:** ' . $this->taskAssignment->order->customer_name)
            ->line('**Workflow:** ' . $this->taskAssignment->workflowStep->processFlow->name)
            ->action('View Task', url('/admin/task-assignments/' . $this->taskAssignment->id))
            ->line('Please complete this task as soon as possible.');
    }

    public function toArray($notifiable): array
    {
        return [
            'task_id' => $this->taskAssignment->id,
            'task_name' => $this->taskAssignment->workflowStep->name,
            'order_id' => $this->taskAssignment->order->platform_order_id,
            'customer_name' => $this->taskAssignment->order->customer_name,
            'workflow_name' => $this->taskAssignment->workflowStep->processFlow->name,
            'assigned_at' => $this->taskAssignment->assigned_at,
        ];
    }
}