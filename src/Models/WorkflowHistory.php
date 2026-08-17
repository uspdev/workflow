<?php

namespace Uspdev\Workflow\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Uspdev\Forms\Models\FormSubmission;

class WorkflowHistory extends Model
{
    protected $table = 'workflow_history';

    protected $fillable = [
        'workflow_object_id',
        'transition_name',
        'user_id',
        'metadata',
        'form_submission_id',
        'from_places',
        'to_places',
    ];
    protected $casts = [
        'metadata' => 'array',
        'from_places' => 'array',
        'to_places' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('O histórico do workflow é append-only e nao pode ser alterado.');
        });

        static::deleting(function (): never {
            throw new LogicException('O histórico do workflow é append-only e nao pode ser removido.');
        });
    }

    public function workflowObject(): BelongsTo
    {
        return $this->belongsTo(WorkflowObject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }
}
