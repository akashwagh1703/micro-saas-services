<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowExecution extends Model
{
    protected $fillable = [
        'user_id',
        'workflow_id',
        'contact_id',
        'conversation_id',
        'message_id',
        'status',
        'context',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ExecutionLog::class, 'workflow_execution_id');
    }
}
