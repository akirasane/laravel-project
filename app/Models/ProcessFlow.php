<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessFlow extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'conditions',
        'created_by'
    ];

    protected $casts = [
        'conditions' => 'json',
        'is_active' => 'boolean'
    ];

    /**
     * Get the workflow steps for this process flow.
     */
    public function workflowSteps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order');
    }

    /**
     * Get the user who created this process flow.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get active process flows.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the validation rules for the model.
     */
    public static function validationRules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:process_flows,name',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'required|boolean',
            'conditions' => 'nullable|array',
            'created_by' => 'nullable|exists:users,id',
        ];
    }
}
