<?php
namespace Uspdev\Workflow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Uspdev\Workflow\Models\WorkflowDefinition;

class WorkflowObject extends Model 
{
    use HasFactory;
    
    protected $fillable = [
        'state',
        'workflow_definition_name',
        'user_codpes',
    ];

    protected $casts = [
        'state' => 'array',
    ];
    

    public function workflowDefinition()
    {
        return $this->belongsTo(WorkflowDefinition::class);
    }

    public function getCurrentState()
    {
        return $this->state ?? [];
    }

    public function setCurrentState($state)
    {
        $this->state = $state;
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function updateState(string $newState)
    {
        $this->state = $newState;
        $this->save();

        activity() 
            ->performedOn($this) 
            ->causedBy(auth()->user()) 
            ->withProperties(['state' => $newState]) 
            ->log("Updated to {$newState}");
    }
}
