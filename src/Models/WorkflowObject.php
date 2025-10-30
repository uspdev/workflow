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
    
    /**
     *  Relaciona o objeto de workflow à uma definição de workflow
     * 
     *  @return BelongsTo<WorkflowDefinition, WorkflowObject>
     */
    public function workflowDefinition()
    {
        return $this->belongsTo(WorkflowDefinition::class);
    }

    /**
     *  Obtém o 'state' atual do workflow
     *  
     *  - Caso 'state' seja nulo, retorna um array vazio;
     *  @return array
     */
    public function getCurrentState()
    {
        return $this->state ?? [];
    }

    /**
     *  Atualiza o campo 'state' do workflow
     * 
     *  @param array $state
     *  @return void
     */
    public function setCurrentState($state)
    {
        $this->state = $state;
    }

    /**
     *  Relaciona o objeto de workflow à um usuário
     * 
     * @return 
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     *  Passa para o próximo state do workflow
     *  
     *  - Atualiza o 'state' do workflow;
     *  - Registra o usuário, o state e gera um log simples ao atualizar.
     *  
     * @param string $newState
     * @return void
     */
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
