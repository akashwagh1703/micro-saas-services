<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionLog extends Model
{
    protected $fillable = [
        'workflow_execution_id',
        'node_id',
        'node_type',
        'status',
        'input',
        'output',
        'error_message',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'workflow_execution_id');
    }
}
