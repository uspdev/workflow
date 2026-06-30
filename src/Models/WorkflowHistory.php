<?php

namespace Uspdev\Workflow\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Uspdev\Forms\Models\FormSubmission;

class WorkflowHistory extends Model
{
    protected $table = 'workflow_history';

    protected $casts = [
        'metadata' => 'array',
    ];

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
