<?php

namespace Uspdev\Workflow;

use Illuminate\Database\Eloquent\Collection;
use stdClass;
use App\Models\User;
use Uspdev\Forms\Form;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Uspdev\Forms\Models\FormDefinition;
use Uspdev\Forms\Models\FormSubmission;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Transition;
use Uspdev\Workflow\DTO\TransitionDefinition;
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
     *  @return Collection
     */
    public static function obterTodosWorkflowDefinitions()
    {
        return WorkflowDefinition::all();
    }

    /**
     *  Retorna todas os objetos de workflow persistidos no banco de dados
     * 
     *  @return Collection
     */
    public static function obterTodosWorkflowObjects()
    {
        return WorkflowObject::all();
    }

    /**
     *  Retorna os registros de atividade para um objeto
     *  Com o id correspondente ao passado de parâmetro na chamada do método
     * 
     *  - Encontra a atividade relacionada ao workflow object com o id citado acima;
     *  - Encontra a workflow definition relacionada ao objeto;
     *  - Captura as propriedades da atividade;
     *  - Verifica se o 'state' da atividade está ativo no 'place' atual do workflow object
     *  - Encontra (Se possível) o causador da atividade
     * 
     *  - Formata toda a resposta e retona um array com os dados da atividade;
     * 
     *  @param int $id
     *  @return Array
     */
    public static function obterAtividades(int $id)
    {
        
        $atividades = Activity::where('subject_type', WorkflowObject::class)
            ->where('subject_id', $id)
            ->get();

        $resultadoFormatado = $atividades->map(function ($atividade) {
            $workflowObject = WorkflowObject::findOrFail($atividade->subject_id);
            $workflowDefinition = WorkflowDefinition::where('id',$workflowObject->workflow_definition_id)->first();
            $stateData = json_decode($atividade->properties, true);
            $nomeBonito = $workflowDefinition->definition['places'][$stateData['state']] ?? $stateData['state'];
            $user = $atividade->causer_id ? User::find($atividade->causer_id) : null;

            return [
                'id' => $atividade->id,
                'description' => "Alterado para: " . ($nomeBonito['description'] ?? 'Descrição não disponível'),
                'objectId' => $atividade->subject_id,
                'user' => $user ? $user->name : 'Não definido',
                'created_at' => \Carbon\Carbon::parse($atividade->created_at)->format('d/m/Y H:i'),
                'updated_at' => \Carbon\Carbon::parse($atividade->updated_at)->format('d/m/Y H:i'),
            ];
        });

        return $resultadoFormatado;
    }


    /**
     * Retorna as transições de uma instância de SymfonyWorkflow
     * Passada de parâmetro na chamada do método
     * 
     * @param SymfonyWorkflow $workflowInstance
     * @return Array
     */
    public static function obterNomeDasTransitions(SymfonyWorkflow $workflowInstance)
    {
        $allTransitions =  $workflowInstance->getDefinition()->getTransitions();

        return array_map(function ($transition) {
            return $transition->getName();
        }, $allTransitions) ?: [];
    }

    /**
     * Retorna as transições habilitadas para um objeto de workflow
     * baseado uma instância de SymfonyWorkflow passada de parâmetro na chamada do método
     * 
     * Possui duas maneiras de receber o objeto como parâmetro
     * A primeira, o objeto de WorkflowObject persistido no banco
     * E a segunda, utilizada para quando o objeto ainda não foi persistido no banco
     * Nesse caso, é utilizada uma stdClass que possui as mesmas propriedades que o WorkflowObject
     * 
     * @param SymfonyWorkflow $workflowInstance
     * @param WorkflowObject $workflowObject
     * @return Array
     */
    public static function obterTransitionsHabilitadas(SymfonyWorkflow $workflowInstance, WorkflowObject $workflowObject)
    {
        $enabledTransitions = $workflowInstance->getEnabledTransitions($workflowObject);
        $transitionNames = array_map(function ($transition) {
            return $transition->getName();
        }, $enabledTransitions);

        /** @var WorkflowDefinition */
        $workflowDefinition = WorkflowDefinition::where('id',$workflowObject->workflow_definition_id)->first();

        if (!$workflowDefinition) 
        {
            return $transitionNames ?: [];
        }

        $allowedTransitions = array_filter($transitionNames, function ($transitionName) use ($workflowObject,$workflowDefinition) {
            $transition = $workflowDefinition->transition($transitionName);
            return !$workflowObject->isTransitionBlocked($transition);
        });

        return array_values($allowedTransitions) ?: [];
    }


    /**
     * Retorna o html de um formulário referente ao estado atual do objeto, se existir
     * Caso não tenha, retorna vazio ('')
     * 
     * @param WorkflowObject $workflowObject
     * @param WorkflowDefinition $workflowDefinition
     * @return String HTML formatado
     */
    public static function obterHtml(WorkflowObject $workflowObject, WorkflowDefinition $workflowDefinition)
    {
        $definitionData = $workflowDefinition->definition;

        $formHtml = '';

        $places = $workflowObject->current_places;
        foreach($places as $place)
        {
            if(isset($definitionData['places'][$place]['transitions']))
            {
                foreach($definitionData['places'][$place]['transitions'] as $transitionName)
                {
                    $transition = $workflowDefinition->transition($transitionName);
                    if(isset($transition->form))
                    {
                        $form = new Form();
                        $formHtml = $form->generateHtml($transition->form);
                    }
                }
            }
        }

        return $formHtml;
    }

    /**
     *  Retorna dados relevantes referentes uma definição de workflow
     *  Com o nome passado de parâmetro na chamada do método
     * 
     *  - Os dados são retornados em um array com as seguintes chaves:
     *  - 'workflowDefinition' -> Instância de 'WorkflowDefinition', de nome '$definitionName'
     *  - 'definitionName' -> Nome da definição
     *  - 'path' - Caminho para onde o grafo da definição foi salvo
     *  - 'formattedJson' -> Definição formatada em .json
     *  - 'roles' - 'roles' exigidas pela definição
     * 
     * @param String $definitionName
     * @return Array $workflowData
     */
    public static function obterDadosDaDefinicao(string $definitionName, int $version = null)
    {
        $workflowDefinition = SELF::loadDefinition($definitionName, $version);

        $definitionData = $workflowDefinition->definition;
        $workflowDefinition->generatePng();
        $path = "storage/app/public/" . $definitionName . ".png";
        $formattedJson = json_encode($definitionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $roles = [];
        foreach($workflowDefinition->definition['places'] as $place){

            // Inicialmente no formato 'places => [Role_key1 => role1, ...]
            $keyRole = key($place['role']);
            // keyRole == Role_keyN
            $role = $place['role'][$keyRole];
            // role == roleN
            $roles[$role] = $keyRole;
            // Por fim, passa ao formato : $roles[roleN] == Role_keyN
        }

        $workflowData['workflowDefinition'] = $workflowDefinition;
        $workflowData['definitionName'] = $definitionName;
        $workflowData['path'] = $path;
        $workflowData['formattedJson'] = $formattedJson;
        $workflowData['roles'] = array_unique($roles);

        return $workflowData;
    }

    /**
     * Cria um objeto com as mesmas propriedades de um WorkfloWObject 
     * baseado na definição passada como parâmetro por seu nome.
     * Contudo, não cria diretamente um WorkflowObject, pois isso envolveria a
     * persistência indesejada no banco de dados nesse momento, visto que a persistência
     * só deve ocorrer após alguma atualização referente a esse objeto recém criado.
     * 
     * Retorna o mesmo tipo de array que o método obterDadosDoObjeto
     * 
     * @param String $workflowDefinitionName
     * @return Array $workflowObjectData
     */
    // public static function criarWorkflowObject($workflowDefinitionName)
    // {
    //     $workflowDefinition = Workflow::obterWorkflowDefinition($workflowDefinitionName);
    //     $workflow = Workflow::criarSymfonyWorkflow($workflowDefinition);

    //     $initialState = $workflow->getDefinition()->getInitialPlaces();
    //     foreach($initialState as $state) {
    //         $states = [$state => 1];

    //     }

    //     $workflowInstance = Workflow::criarSymfonyWorkflow($workflowDefinition);

    //     $fakeWorkflowObject = new \stdClass();
    //     $fakeWorkflowObject->state = $states;
    //     $fakeWorkflowObject->id = '0';
    //     $fakeWorkflowObject->currentState = $states;
    //     $fakeWorkflowObject->workflowDefinitionName = $workflowDefinitionName;

    //     $workflowsTransitions['enabled'] =  Workflow::obterNomeDasTransitionsHabilitadas($workflowInstance, null, $fakeWorkflowObject);
    //     $workflowsTransitions['all'] =  Workflow::obterNomeDasTransitions($workflowInstance);
    //     $workflowsTransitions['currentState'] =  $state;

    //     $forms = [];
    //     foreach($workflowsTransitions['enabled'] as $enabledTransition){
    //         if (isset($workflowDefinition->definition['transitions'][$enabledTransition]['forms'])) {
    //             foreach($workflowDefinition->definition['transitions'][$enabledTransition]['forms'] as $formName){
    //                 $form = new Form();
    //                 $formHtml = $form->generateHtml($formName);
    //                 $formHtml = str_replace("workflowDefinitionName", $workflowDefinition->name, $formHtml);
    //                 $formHtml = str_replace("workflowDefinitionName", $workflowDefinition->name, $formHtml);
    //                 $statesString = '';
    //                 foreach ($states as $state => $value) {
    //                     $statesString .= $state;
    //                     $statesString .= ', ';
    //                 }
    //                 $statesString = \Illuminate\Support\Str::beforeLast($statesString, ', ');
    //                 $formHtml = str_replace("place_name", $statesString, $formHtml);            $formHtml = str_replace("transition_name", $enabledTransition, $formHtml);


    //                 $formData['transition'] =  $enabledTransition;
    //                 $formData['html'] =  $formHtml;
    //                 $forms[] = $formData;
    //             }
    //         }
    //  // dd(config('uspdev-workflow.currModel'));   }

    //     $workflowObjectData = [
    //     'workflowObject' => $fakeWorkflowObject,
    //     'workflowDefinition' => $workflowDefinition,
    //     'workflowsTransitions' => $workflowsTransitions,
    //     'forms' => $forms,
    //     'title' => $workflowDefinition->definition['title'],
    //     'activities' => [],
    //     'formSubmissions' => [],
    //     'formRequired' => !empty($formName)
    // ];

    //     return $workflowObjectData;
    // }

    /**
     * Cria uma instância de SymfonyWorkflow baseado na WorkflowDefinition
     * passada como parâmetro na chamada do método
     * 
     * @param WorkflowDefinition $workflowDefinition
     * @return SymfonyWorkflow
     */
    public static function criarSymfonyWorkflow(WorkflowDefinition $workflowDefinition)
    {
        $definitionData = $workflowDefinition->definition;
        $places = [];
        if (isset($definitionData['places'])) {
            foreach ($definitionData['places'] as $key => $value) {
                if (is_array($value)) {
                    $places[] = $key;
                } else {
                    $places[] = $value;
                }
            }

            $transitions = $definitionData['transitions'] ?? [];

            $workflowDefinition = new Definition(
                $places,
                // $name = array_key($transitions)
                // $transition = valor refernciado pelas chaves
                /*
                   Logo, se $transition = [
                   'aprovado' => ['from' => 'inicio', 'tos' => '[meio','fim']]
                    ...
                   ]

                   Na primeira iteração - $name == 'aprovador'; $transition == ['from' => 'inicio', 'tos' => '[meio','fim']]

                   Faz isso para todos os elementos do vetor
                */
                array_map(function ($name, $transition) {
                    $tos = is_array($transition['tos']) ? $transition['tos'] : [$transition['tos']];
                    return new Transition($name, $transition['from'], $tos);
                }, array_keys($transitions), $transitions)
            );

            return new SymfonyWorkflow($workflowDefinition, new MethodMarkingStore(false, 'currentState'));
        }
    }

    /**
     * Exclui um WorkflowObject com o Id referenciado
     * 
     * @param Integer $workflowId
     */
    public static function deletarWorkflow($workflowId)
    {
        $workflowObject = WorkflowObject::findOrFail($workflowId);
        $workflowObject->delete();
    }

    /**
     * Lista todos os WorkflowObjects criados pelo usuário passado pelo codpes
     * na chamada do método. Se não for passado parâmetro, será utilizado o codpes
     * do usuário autenticado no sistema
     * 
     * @param Integer $userCodpes
     * @return Array $workflowsDisplay
     */
    // public static function listarWorkflowsdoUser($userCodpes = null)
    // {
    //     $userCodpes = $userCodpes ?: auth()->user()->codpes;

    //     $workflowsDisplay = [];

    //     $workflows = WorkflowObject::where('user_codpes', $userCodpes)->get();
    //     $workflowData = [];
    //     foreach ($workflows as $workflowObject) {
    //         $workflowDefinition = Workflow::obterWorkflowDefinition($workflowObject->workflow_definition_name);
    //         $workflowInstance = Workflow::criarSymfonyWorkflow($workflowDefinition);
    //         $enabledTransitions = $workflowInstance->getEnabledTransitions($workflowObject);

    //         $workflowData[$workflowObject->id]['workflowDefinition'] = $workflowDefinition;

    //         if ($workflowObject->state == $workflowDefinition->definition['initial_places']) {
    //             $workflowData[$workflowObject->id]['state'] = 'start';
    //         } else if (count($enabledTransitions) > 0) {
    //             $workflowData[$workflowObject->id]['state'] = 'progress';
    //         } else {
    //             $workflowData[$workflowObject->id]['state'] = 'end';
    //         }
    //     }
    //     $workflowsDisplay['workflows'] = $workflows;
    //     $workflowsDisplay['workflowData'] = $workflowData;

    //     return $workflowsDisplay;
    // }

    /**
     * Verifica se o formulário requer que algum campo
     * seja obrigatoriamente preenchido
     * 
     * @param String $formName
     * @return boolean
     */
    public static function verificarFormRequired($formName)
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
     * @return boolean
     */
    public static function verificarFormParaTransition($transition, $workflowObject, $workflowDefinition)
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
     * Lida com a submissão de um formulário através da biblioteca Uspdev/Forms
     */
    public static function enviarFormulario(Request $request)
    {

        if ($request->input('form_key') == config('forms.defaultKey')) {

            $workflowDefinitionName = $request->input('definition_name');

            $workflow_definition_name = ['workflow_definition_name' => $workflowDefinitionName];
            $workflowDefinition = WorkflowDefinition::where('name', $workflowDefinitionName)->firstOrFail();

            $states = [];
            if(is_array($workflowDefinition['definition']['initial_places'])){
                foreach($workflowDefinition['definition']['initial_places'] as $state){
                    $states = [$state => 1];
                }
            } else {
                $states = [$workflowDefinition['definition']['initial_places'] => 1];
            }

            $workflowObject = Workflow::criarWorkflow($workflow_definition_name, $states);

            $workflowObject->save();
            $request->merge(['form_key' => $workflowObject->id]);
        }

        $form = new Form(['editable' => true]);
        $form->handleSubmission($request);
        $id = $workflowObject->id ?? $request->input('form_key');
        self::aplicarTransition($id, $request->input('transition'), $request->input('definition_name'));
        return $id;
    }

    /**
     * Lista todos os WorkflowObjects relacionados a um usuário através de seu estado.
     * Se um usuário estiver relacionado a um place de uma WorkflowDefinition e o WorkflowObject
     * estiver nesse place, o WorkflowObject será listado. O usuário pode ser passado pelo codpes
     * na chamada do método, caso contrário, será utilizado o codpes do usuário autenticado no sistema.
     * 
     * @param Integer $userCodpes
     * @return Array $workflowsDisplay
     */
    public static function listarWorkflowsObjectsRelacionados($userCodpes = null)
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
            $workflowDefinition = Workflow::obterWorkflowDefinition($workflowObject->workflow_definition_name);
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
     * Instancia uma definição de workflow, identificada pelo nome e pela versão.
     * Retorna null caso a definição desejada não seja encontrada.
     * Caso a versão não seja especificada, a versão publicada será retornada.
     * @param string $definitionName
     * @param int $version
     * @return WorkflowDefinition|null
     */
    public static function loadDefinition(string $definitionName, int $version = null): ?WorkflowDefinition
    {
        if(isset($version)) 
        {
            $workflowDefinition = WorkflowDefinition::where('name', $definitionName)
                ->where('version', $version)->first();
        } 
        else 
        {
            $workflowDefinition = WorkflowDefinition::where('name', $definitionName)->where('status', 'published')->first();
        }
        return $workflowDefinition;
    }

    /**
     * Recupera o workflow atrelado àquele objeto.
     * Busca pelo tipo do objeto e pelo id do mesmo, retornando null caso não encontre.
     * @param Model $model
     * @return object|Model|null
     */
    public static function find(Model $model): ?WorkflowObject
    {
        $workflowObject = WorkflowObject::where('object_type', get_class($model))
            ->where('object_id', $model->getKey())
            ->first();

        return $workflowObject;
    }

    /**
     * Inicia e retorna a instância de um workflow, a partir de sua definição.
     * Atrela o objeto à um Model, que servirá para identificação do objeto.
     * Caso não encontre a definição ou o Model seja null, retorna null.
     * @param string $definitionName
     * @param Model $model
     * @return WorkflowObject|null
     */
    public static function start(string $definitionName, Model $model): ?WorkflowObject
    {

        
        $workflow_def = SELF::loadDefinition($definitionName);
        if(!isset($workflow_def,$model)) 
        {
            throw new \Exception("Workflow definition not found: $definitionName");
        }
        
        return WorkflowObject::createObject($workflow_def, $model);
    }

}
