<?php

namespace Uspdev\Workflow;

use Illuminate\Database\Eloquent\Collection;
use stdClass;
use App\Models\User;
use Uspdev\Forms\Facades\Forms;
use Uspdev\Forms\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Uspdev\Forms\Models\FormDefinition;
use Uspdev\Forms\Models\FormSubmission;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Transition;
use Uspdev\Workflow\Models\WorkflowObject;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Symfony\Component\Workflow\Workflow as SymfonyWorkflow;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Illuminate\Database\Eloquent\Model;

class Workflow
{

    /**
     *  Retorna todas as definições de workflow persistidas no banco de dados
     * 
     *  @return Collection<int, WorkflowDefinition>
     */
    public static function obterTodosWorkflowDefinitions(): Collection
    {
        return WorkflowDefinition::all();
    }

    /**
     *  Retorna todas os objetos de workflow persistidos no banco de dados
     * 
     *  @return Collection<int, WorkflowObject>
     */
    public static function obterTodosWorkflowObjects(): Collection
    {
        return WorkflowObject::all();
    }

    /**
     * Verifica se o formulário requer que algum campo
     * seja obrigatoriamente preenchido
     * 
     * @param string $formName
     * @return bool
     */
    public static function verificarFormRequired(string $formName): bool
    {
        $formDefinition = FormDefinition::where('name', $formName)->firstOrFail();
        $formFields = $formDefinition->fields;

        foreach($formFields as $field){
            if(isset($field["required"])){
                if ($field["required"]==true) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Verifica se a transition requer o preechimento
     * de algum formulário e se esse formulário já foi
     * submetido
     * 
     * @param String $transition
     * @param WorkflowObject $workflowObject
     * @param WorkflowDefinition $workflowDefinition
     * @return bool
     */
    public static function verificarFormParaTransition(string $transition, WorkflowObject $workflowObject, WorkflowDefinition $workflowDefinition): bool
    {
        if (isset($workflowDefinition->definition['transitions'][$transition]['forms'])) {
            foreach($workflowDefinition->definition['transitions'][$transition]['forms'] as $formName){

                $form = new Form();
                if ($form->getDefinition($formName) != null) {
                    
                    $formRequired = self::verificarFormRequired($formName);
                    if ($formRequired) {
                        $cond['form_definition_id'] = $form->getDefinition($formName)->id;

                        $submissions = FormSubmission::where($cond)->get();
                        $hasSubmission = $submissions->where('key', $workflowObject->id)->isNotEmpty();
                        if (!$hasSubmission) {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * Lista todos os WorkflowObjects relacionados a um usuário através de seu estado.
     * Se um usuário estiver relacionado a um place de uma WorkflowDefinition e o WorkflowObject
     * estiver nesse place, o WorkflowObject será listado. O usuário pode ser passado pelo codpes
     * na chamada do método, caso contrário, será utilizado o codpes do usuário autenticado no sistema.
     * 
     * @param int $userCodpes
     * @return Array $workflowsDisplay
     */
    public static function listarWorkflowsObjectsRelacionados(?int $userCodpes = null): array
    {
        $userCodpes = $userCodpes ?: auth()->user()->codpes;

        $user = User::where('codpes', $userCodpes)->first();
        $places = $user->getAllPermissions();
        $workflowObjects = collect();

        foreach ($places as $place) {
            $objects = WorkflowObject::whereJsonContains('state', [$place->name => 1])->get();
            $workflowObjects = $workflowObjects->merge($objects);
        }

        $workflowData = [];

        foreach ($workflowObjects as $workflowObject) {
            $workflowDefinition = WorkflowDefinition::loadDefinition($workflowObject->workflow_definition_name);
            $workflowInstance = Workflow::criarSymfonyWorkflow($workflowDefinition);
            $enabledTransitions = $workflowInstance->getEnabledTransitions($workflowObject);

            $workflowData[$workflowObject->id]['workflowDefinition'] = $workflowDefinition;
            $workflowData[$workflowObject->id]['user'] = User::where('codpes', $workflowObject->user_codpes)->first();

            if ($workflowObject->state == $workflowDefinition->definition['initial_places']) {
                $workflowData[$workflowObject->id]['state'] = 'start';
            } else if (count($enabledTransitions) > 0) {
                $workflowData[$workflowObject->id]['state'] = 'progress';
            } else {
                $workflowData[$workflowObject->id]['state'] = 'end';
            }
        }

        $workflowsDisplay['workflows'] = $workflowObjects;
        $workflowsDisplay['workflowData'] = $workflowData;
        
        return $workflowsDisplay;
    }

     /**
     * Cria ou remove o relacionamento de um usuário com um place de uma WorkflowDefinition
     * 
     * @param Integer $request->codpes_rem
     * @param Integer $request->codpes_add
     * @param String $request->place
     * @param String $request->workflowDefinitionName
     */
    public static function definirUsuarios(Request $request)
    {
        $codpes_rem = $request->input('codpes_rem');
        $codpes_add = $request->input('codpes_add');
        $role = $request->input('role');

        $user      = Auth::user();
        if (! $user->hasRole($role) && ! Gate::allows('admin')) {
            return response()->json(['alert-danger' => 'Você não tem permissão para gerenciar este departamento.'], 403);
        }

        if ($codpes_rem) {
            $userToRemove = User::where('codpes', $codpes_rem)->first();
            if ($userToRemove) {
                $userToRemove->removeRole($role);
                $request->session()->flash('alert-warning', 'Usuário removido com sucesso!');
            }
        }

        if ($codpes_add) {
            $userToAdd = User::findOrCreateFromReplicado($codpes_add);
            if ($userToAdd) {
                $userToAdd->assignRole($role);
                $request->session()->flash('alert-success', 'Usuário adicionado com sucesso!');
            }
        }
    }

    /**
     * Recupera o workflow atrelado àquele objeto.
     * Busca pelo tipo do objeto e pelo id do mesmo, retornando null caso não encontre.
     * @param Model $model
     * @return ?WorkflowObject
     */
    public static function find(Model $model): ?WorkflowObject
    {
        return WorkflowObject::from($model);
    }

    /**
     * Carrega uma definição de workflow com nome e versão especificados
     * @param string $definitionName
     * @param mixed $version
     * @return ?WorkflowDefinition
     */
    public static function loadDefinition(string $definitionName, ?int $version = null): ?WorkflowDefinition
    {
        return WorkflowDefinition::_load($definitionName, $version);
    }

    /**
     * Inicia e retorna a instância de um workflow, a partir de sua definição (que deve estar 
     * publicada).
     * Atrela o objeto à um Model, que servirá para identificação do objeto.
     * Caso não encontre a definição ou o Model seja null, retorna null.
     * @param string $definitionName
     * @param Model $model
     * @return ?WorkflowObject
     */
    public static function start(string $definitionName, Model $model): ?WorkflowObject
    {
        $workflow_def = SELF::loadDefinition($definitionName);
        if(!isset($workflow_def,$model)) 
        {
            throw new \Exception("Workflow definition not found: $definitionName");
        }
        
        return WorkflowDefinition::createObject($definitionName, $model);
    }

}
