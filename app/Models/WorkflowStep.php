<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    use HasFactory;
    protected $fillable = [
        'process_flow_id',
        'name',
        'step_order',
        'step_type',
        'assigned_role',
        'auto_execute',
        'conditions',
        'configuration'
    ];

    protected $casts = [
        'conditions' => 'json',
        'configuration' => 'json',
        'auto_execute' => 'boolean'
    ];

    /**
     * Get the process flow that owns this step.
     */
    public function processFlow(): BelongsTo
    {
        return $this->belongsTo(ProcessFlow::class);
    }

    /**
     * Get the task assignments for this workflow step.
     */
    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Scope to get auto-executable steps.
     */
    public function scopeAutoExecutable($query)
    {
        return $query->where('auto_execute', true);
    }

    /**
     * Scope to filter by step type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('step_type', $type);
    }

    /**
     * Get the validation rules for the model.
     */
    public static function validationRules(): array
    {
        return [
            'process_flow_id' => 'required|exists:process_flows,id',
            'name' => 'required|string|max:255',
            'step_order' => 'required|integer|min:1',
            'step_type' => 'required|in:manual,automatic,approval,notification,billing,packing,return',
            'assigned_role' => 'nullable|string|max:100',
            'auto_execute' => 'required|boolean',
            'conditions' => 'nullable|array',
            'configuration' => 'nullable|array',
        ];
    }
}
