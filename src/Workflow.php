<?php

namespace Uspdev\Workflow;

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
use Uspdev\Workflow\Models\WorkflowObject;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Symfony\Component\Workflow\Workflow as SymfonyWorkflow;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;

class Workflow
{

    /**
     * Retorna todas as definições de workflow persistidas no banco de dados
     * 
     * @return Array
     */
    public static function obterTodosWorkflowDefinitions()
    {
        return WorkflowDefinition::all();
    }

    /**
     * Retorna uma definição de workflow
     * Com o nome passado de parâmetro na chamada do método
     * 
     * @param String $definitionName
     * @return WorkflowDefinition
     */
    public static function obterWorkflowDefinition($definitionName)
    {
        return WorkflowDefinition::where('name', $definitionName)->firstOrFail();
    }

    /**
     * Retorna todas os objetos de workflow persistidos no banco de dados
     * 
     * @return Array
     */
    public static function obterTodosWorkflowObjects()
    {
        return WorkflowObject::all();
    }

    /**
     * Retorna um objeto de workflow
     * Com o id correspondente ao passado de parâmetro na chamada do método
     * 
     * @param Integer $id
     * @return WorkflowObject
     */
    public static function obterWorkflowObject($id)
    {
        return WorkflowObject::findOrFail($id);
    }

    /**
     * Retorna os registros de atividade para um objeto
     * Com o id correspondente ao passado de parâmetro na chamada do método
     * 
     * @param Integer $id
     * @return Array
     */
    public static function obterAtividades($id)
    {
        
        $atividades = Activity::where('subject_type', WorkflowObject::class)
            ->where('subject_id', $id)
            ->get();

        $resultadoFormatado = $atividades->map(function ($atividade) {
            $workflowObject = Workflow::obterWorkflowObject($atividade->subject_id);
            $workflowDefinition = SELF::obterWorkflowDefinition($workflowObject->workflow_definition_name);  
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
     * @param WorkflowObject $workflowObject = null
     * @param stdClass $fakeWorkflowObject = null
     * @return Array
     */
    public static function obterNomeDasTransitionsHabilitadas(SymfonyWorkflow $workflowInstance, WorkflowObject $workflowObject = null, stdClass $fakeWorkflowObject = null)
    {
        $workflowObject = $workflowObject ?? $fakeWorkflowObject;

        $enabledTransitions = $workflowInstance->getEnabledTransitions($workflowObject);
        $transitionNames = array_map(function ($transition) {
            return $transition->getName();
        }, $enabledTransitions);

        $workflowDefinition = null;
        if ($workflowObject instanceof WorkflowObject) {
            $workflowDefinition = self::obterWorkflowDefinition($workflowObject->workflow_definition_name);
        } elseif (isset($workflowObject->workflowDefinitionName)) {
            $workflowDefinition = self::obterWorkflowDefinition($workflowObject->workflowDefinitionName);
        }

        if (!$workflowDefinition) {
            return $transitionNames ?: [];
        }

        $currentPlaces = $workflowObject->state ?? $workflowObject->currentState ?? [];

        $allowedTransitions = array_filter($transitionNames, function ($transitionName) use ($workflowDefinition, $currentPlaces) {
            return !self::estaTransicaoBloqueada($workflowDefinition->definition, $transitionName, $currentPlaces);
        });

        return array_values($allowedTransitions) ?: [];
    }


    /**
     * Retorna o html de um formulário referente ao estado do objeto naquela definição
     * 
     * @param WorkflowObject $workflowObject
     * @param WorkflowDefinition $workflowDefinition
     * @return String HTML formatado
     */
    public static function obterHtml(WorkflowObject $workflowObject, WorkflowDefinition $workflowDefinition)
    {
        $definitionData = $workflowDefinition->definition;

        if (isset($definitionData['places'][$workflowObject->state]['forms'])) {
            $formName = $definitionData['places'][$workflowObject->state]['forms'];
            $form = new Form(['key' => $workflowObject->id]);
            $formHtml = $form->generateHtml($formName);
        } else {
            $formHtml = '';
        }

        return $formHtml;
    }

    /**
     * Retorna dados relevantes referentes uma definição de workflow
     * Com o nome passado de parâmetro na chamada do método
     * 
     * Os dados são retornados em um array com as seguintes chaves:
     * 'workflowDefinition' - o campo 'definition' da própria definição de workflow
     * 'definitionName' - o nome da definição
     * 'path' - o caminho para onde a imagem da definição foi gerada
     * 'places' - array com os 'places' da definição
     * 
     * @param String $definitionName
     * @return Array $workflowData
     */
    public static function obterDadosDaDefinicao($definitionName)
    {
        $workflowDefinition = Workflow::obterWorkflowDefinition($definitionName);

        $definitionData = $workflowDefinition->definition;
        $workflowDefinition->generatePng();
        $path = "storage/app/public/" . $definitionName . ".png";
        $formattedJson = json_encode($definitionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $roles = [];
        foreach($workflowDefinition->definition['places'] as $place){
            $keyRole = key($place['role']);
            $role = $place['role'][$keyRole];
            $roles[$role] = $keyRole;
        }

        $workflowData['workflowDefinition'] = $workflowDefinition;
        $workflowData['definitionName'] = $definitionName;
        $workflowData['path'] = $path;
        $workflowData['formattedJson'] = $formattedJson;
        $workflowData['roles'] = array_unique($roles);

        return $workflowData;
    }

    /**
     * Retorna dados relevantes referentes um objeto de workflow
     * Com o id correspondente ao passado de parâmetro na chamada do método
     * 
     * Os dados são retornados em um array com as seguintes chaves:
     * 'workflowObject' - O próprio objeto de workflow
     * 'workflowDefinition' - O objeto da definição referente ao workflowObject
     * 'workflowsTransitions' - Array de transições com as chaves 'enabled', 'all' e 'currentState'
     *  essas chaves contém, respectivamente, as transições habilitadas para o objeto, todas as transições
     *  da definição e o estado atual do objeto
     * 'formHtml' - HMTL formatado do formulário relacionado ao estado/place atual do objeto
     * 'title' - Título da definição
     * 'activity' - Array de registro de atividades para aquele objeto
     * 'formSubmissions' - Array de submissões de formulários para aquele objeto
     * 
     * @param Integer $workflowObjectId
     * @return Array $workflowObjectData
     */
    public static function obterDadosDoObjeto($workflowObjectId)
    {
        $workflowObject = Workflow::obterWorkflowObject($workflowObjectId);

        $workflowDefinition = Workflow::obterWorkflowDefinition($workflowObject->workflow_definition_name);
        $workflowInstance = Workflow::criarSymfonyWorkflow($workflowDefinition);

        $workflowsTransitions['enabled'] =  Workflow::obterNomeDasTransitionsHabilitadas($workflowInstance, $workflowObject);
        $workflowsTransitions['all'] =  Workflow::obterNomeDasTransitions($workflowInstance);
        $workflowsTransitions['currentState'] =  $workflowObject->state;
        $workflowsTransitions['allowed'] = [];

        $form = new Form(['key' =>$workflowObjectId]);

        $forms = [];
        
        foreach($workflowsTransitions['enabled'] as $enabledTransition){
            if (isset($workflowDefinition->definition['transitions'][$enabledTransition]['forms'])) {
                foreach($workflowDefinition->definition['transitions'][$enabledTransition]['forms'] as $formName){

                    $formHtml = $form->generateHtml($formName);
                    $formHtml = str_replace("workflowDefinitionName", $workflowDefinition->name, $formHtml);
                    $statesString = '';
                    foreach ($workflowObject->state as $state => $value) {
                        $statesString .= $state;
                        $statesString .= ', ';
                    }
                    $statesString = \Illuminate\Support\Str::beforeLast($statesString, ', ');
                    $formHtml = str_replace("place_name", $statesString, $formHtml);
                    $formHtml = str_replace("transition_name", $enabledTransition, $formHtml);

                    $formData['transition'] =  $enabledTransition;
                    $formData['html'] =  $formHtml;
                    $forms[] = $formData;
                }
            }
        }

        $title = $workflowDefinition->definition['title'];
        $activities = Workflow::obterAtividades($workflowObject->id);
        $form = new Form(['key' => $workflowObject->id]);
        $formSubmissions = $form->listSubmission();
        if (!Gate::allows('admin')) {
            $formSubmissions = $formSubmissions->filter(function ($submission) use ($workflowObject, $workflowDefinition) {
                $transition = $submission['data']['transition'];
                $to = $workflowDefinition->definition['transitions'][$transition]['tos'];
                $initial = $workflowDefinition->definition['initial_places'];

                $workflowInstance = Workflow::criarSymfonyWorkflow($workflowDefinition);
                $fakeWorkflowObject = new \stdClass();
                
                if (!is_array($to)) {
                    $to = [$to => 1];
                } else {
                    if (array_values($to) === $to) {
                        $toWithWeights = [];
                        foreach ($to as $place) {
                            $toWithWeights[$place] = 1;
                        }
                        $to = $toWithWeights;
                    }
                }

                $fakeWorkflowObject->currentState = $to;
                $enabledTransitions =  Workflow::obterNomeDasTransitionsHabilitadas($workflowInstance, null, $fakeWorkflowObject);
                if (empty($enabledTransitions)) {
                    return true;
                }

                return $submission['data']['place'] == $workflowObject->state || $workflowObject->state == $to || $submission['data']['place'] == $initial;
            });
        }        

        $workflowObjectData['workflowObject'] = $workflowObject;
        $workflowObjectData['workflowDefinition'] = $workflowDefinition;
        $workflowObjectData['workflowsTransitions'] = $workflowsTransitions;
        $workflowObjectData['forms'] = $forms;
        $workflowObjectData['title'] = $title;
        $workflowObjectData['activities'] = $activities;
        $workflowObjectData['formSubmissions'] = collect($formSubmissions);

        return $workflowObjectData;
    }

    /**
     * Retorna um WorkflowObject referente à definição de workflow
     * passada como parâmetro na chamada do método, no estado em que também foi passado
     * como parâmetro. Pode receber um codpes para ser referenciado na criação do objeto.
     * Se esse codpes não for passado como parâmetro, a criação do objeto utiliza o codpes
     * do usuário autenticado no sistema
     * 
     * @param String $definitionName
     * @param String $state
     * @param Integer $userCodpes
     * @return WorkflowObject
     */
    public static function criarWorkflow($definitionName, $state, $userCodpes = null)
    {
        $userCodpes = $userCodpes ?: auth()->user()->codpes;
        if (!is_array($state)) {
            $state = ['state' => $state];
        }
        return WorkflowObject::create(array_merge($state, $definitionName, ['user_codpes' => $userCodpes]));
    }

    /**
     * Atualiza uma WorkflowDefinition com os dados passados como parâmetros pelo $request
     * Valida todos os dados antes de fazer a atualização da definição
     * 
     * @param String $request->name
     * @param String $request->description
     * @param Json $request->defintion
     */
    public static function atualizarWorkflow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'nullable|string',
            'definition' => 'required|json',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        $workflow = WorkflowDefinition::where('name', $request->name)->firstOrFail();
        $workflow->description = $request->description;
        $workflow->definition = json_decode($request->definition);
        
        $def = json_decode($request->definition, true);
        if (isset($def['places'])) { // Verificar necessidade de excluir roles e permissions antigas
            foreach ($def['places'] as $key => $value) {
                $keyRole = key($value['role']);
                $roleName = $value['role'][$keyRole] ?? $key;
        
                $role = Role::firstOrCreate(['name' => $roleName]);
        
                $permission = Permission::firstOrCreate(['name' => $key]);
        
                if (!$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        $workflow->save();
    }

    /**
     * Cria uma WorkflowDefinition com os dados passados como parâmetros pelo $request
     * Valida todos os dados antes de fazer a criação da definição
     * 
     * @param String $request->name
     * @param String $request->description
     * @param Json $request->defintion
     */
    public static function criarWorkflowDefinition(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:workflow_definitions,name',
            'description' => 'nullable|string',
            'definition' => 'required|json',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        $def = json_decode($request->definition, true);
        if (isset($def['places'])) {
            foreach ($def['places'] as $key => $value) {
                $keyRole = key($value['role']);
                $roleName = $value['role'][$keyRole] ?? $key;
        
                $role = Role::firstOrCreate(['name' => $roleName]);
        
                $permission = Permission::firstOrCreate(['name' => $key]);
        
                if (!$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
        

        WorkflowDefinition::create([
            'name' => $request->name,
            'description' => $request->description,
            'definition' => json_decode($request->definition),
        ]);
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
    public static function criarWorkflowObject($workflowDefinitionName)
    {
        $workflowDefinition = Workflow::obterWorkflowDefinition($workflowDefinitionName);
        $workflow = Workflow::criarSymfonyWorkflow($workflowDefinition);

        $initialState = $workflow->getDefinition()->getInitialPlaces();
        foreach($initialState as $state) {
            $states = [$state => 1];

        }

        $workflowInstance = Workflow::criarSymfonyWorkflow($workflowDefinition);

        $fakeWorkflowObject = new \stdClass();
        $fakeWorkflowObject->state = $states;
        $fakeWorkflowObject->id = '0';
        $fakeWorkflowObject->currentState = $states;
        $fakeWorkflowObject->workflowDefinitionName = $workflowDefinitionName;

        $workflowsTransitions['enabled'] =  Workflow::obterNomeDasTransitionsHabilitadas($workflowInstance, null, $fakeWorkflowObject);
        $workflowsTransitions['all'] =  Workflow::obterNomeDasTransitions($workflowInstance);
        $workflowsTransitions['currentState'] =  $state;

        $forms = [];
        foreach($workflowsTransitions['enabled'] as $enabledTransition){
            if (isset($workflowDefinition->definition['transitions'][$enabledTransition]['forms'])) {
                foreach($workflowDefinition->definition['transitions'][$enabledTransition]['forms'] as $formName){
                    $form = new Form();
                    $formHtml = $form->generateHtml($formName);
                    $formHtml = str_replace("workflowDefinitionName", $workflowDefinition->name, $formHtml);
                    $formHtml = str_replace("workflowDefinitionName", $workflowDefinition->name, $formHtml);
                    $statesString = '';
                    foreach ($states as $state => $value) {
                        $statesString .= $state;
                        $statesString .= ', ';
                    }
                    $statesString = \Illuminate\Support\Str::beforeLast($statesString, ', ');
                    $formHtml = str_replace("place_name", $statesString, $formHtml);            $formHtml = str_replace("transition_name", $enabledTransition, $formHtml);


                    $formData['transition'] =  $enabledTransition;
                    $formData['html'] =  $formHtml;
                    $forms[] = $formData;
                }
            }
        }

        $workflowObjectData = [
        'workflowObject' => $fakeWorkflowObject,
        'workflowDefinition' => $workflowDefinition,
        'workflowsTransitions' => $workflowsTransitions,
        'forms' => $forms,
        'title' => $workflowDefinition->definition['title'],
        'activities' => [],
        'formSubmissions' => [],
        'formRequired' => !empty($formName)
    ];

        return $workflowObjectData;
    }

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
                array_map(function ($name, $transition) {
                    $tos = is_array($transition['tos']) ? $transition['tos'] : [$transition['tos']];
                    return new Transition($name, $transition['from'], $tos);
                }, array_keys($transitions), $transitions)
            );

            return new SymfonyWorkflow($workflowDefinition, new MethodMarkingStore(false, 'currentState'));
        }
    }

    /**
     * Exclui um WorkflowObject com o Id passado
     * 
     * @param Integer $workflowId
     */
    public static function deletarWorkflow($workflowId)
    {
        $workflowObject = WorkflowObject::findOrFail($workflowId);
        $workflowObject->delete();
    }

    /**
     * Exclui uma WorkflowDefinition com o nome passado
     * 
     * @param String $definitionName
     */
    public static function deletarDefinicaodeWorkflow($definitionName)
    {
        $workflowDefinition = WorkflowDefinition::where('name', $definitionName)->firstOrFail();
        $workflowDefinition->delete();
    }

    /**
     * Lista todos os WorkflowObjects relacionados à WorkflowDefinition com o nome passado
     * 
     * @param String $definitionName
     * @return Array $workflowsDisplay
     */
    public static function listarWorkflowsdaDefinition($definitionName)
    {
        $workflowsDisplay = [];

        $workflowDefinition = WorkflowDefinition::where('name', $definitionName)->firstOrFail();
        $definitionData = $workflowDefinition->definition;

        $transitionsData = $definitionData['transitions'];

        $workflows = WorkflowObject::where('workflow_definition_name', $definitionName)->get();
        $workflowsTransitions = [];

        foreach ($workflows as $workflowObject) {
            $enabledTransitions = [];
            $currentStates = $workflowObject->state;
            foreach ($transitionsData as $transitionName => $transition) {
                if (in_array($transition['from'], $currentStates)) {
                    $enabledTransitions[] = $transitionName;
                }
            }
            

            $allTransitions = array_keys($transitionsData);

            $workflowsTransitions[$workflowObject->id] = [
                'enabled' => $enabledTransitions,
                'all' => $allTransitions,
                'currentState' => $workflowObject->currentState,
            ];
        }

        $workflowsDisplay['workflows'] = $workflows;
        $workflowsDisplay['workflowsTransitions'] = $workflowsTransitions;
        $workflowsDisplay['workflowDefinition'] = $workflowDefinition;

        return $workflowsDisplay;
    }

    /**
     * Lista todos os WorkflowObjects criados pelo usuário passado pelo codpes
     * na chamada do método. Se não for passado parâmetro, será utilizado o codpes
     * do usuário autenticado no sistema
     * 
     * @param Integer $userCodpes
     * @return Array $workflowsDisplay
     */
    public static function listarWorkflowsdoUser($userCodpes = null)
    {
        $userCodpes = $userCodpes ?: auth()->user()->codpes;

        $workflowsDisplay = [];

        $workflows = WorkflowObject::where('user_codpes', $userCodpes)->get();
        $workflowData = [];
        foreach ($workflows as $workflowObject) {
            $workflowDefinition = Workflow::obterWorkflowDefinition($workflowObject->workflow_definition_name);
            $workflowInstance = Workflow::criarSymfonyWorkflow($workflowDefinition);
            $enabledTransitions = $workflowInstance->getEnabledTransitions($workflowObject);

            $workflowData[$workflowObject->id]['workflowDefinition'] = $workflowDefinition;

            if ($workflowObject->state == $workflowDefinition->definition['initial_places']) {
                $workflowData[$workflowObject->id]['state'] = 'start';
            } else if (count($enabledTransitions) > 0) {
                $workflowData[$workflowObject->id]['state'] = 'progress';
            } else {
                $workflowData[$workflowObject->id]['state'] = 'end';
            }
        }
        $workflowsDisplay['workflows'] = $workflows;
        $workflowsDisplay['workflowData'] = $workflowData;

        return $workflowsDisplay;
    }

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
     * Verifica se um WorkflowObject passado por seu id pode realizar certa
     * transição também passada por parâmetro. Caso possa, o método aplica essa
     * tal transição e salva o objeto com seu novo estado.
     * 
     * @param Integer $id
     * @param String $transition
     * @param String $workflowDefinitionName
     */
    public static function aplicarTransition($id, $transition, $workflowDefinitionName) 
    {
        $workflowDefinition = WorkflowDefinition::where('name', $workflowDefinitionName)->firstOrFail();

        if ($id == 0) {
            DB::beginTransaction();
            $workflow_definition_name = ['workflow_definition_name' => $workflowDefinitionName];
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

            $workflow = SELF::criarSymfonyWorkflow($workflowDefinition);
            if ($workflow->can($workflowObject, $transition)) {
                $success = self::verificarFormParaTransition($transition, $workflowObject, $workflowDefinition);

                if (!$success) {
                    DB::rollback();
                    return 0;
                } 
                DB::commit();

                $state = $workflow->apply($workflowObject, $transition);
                $currentState = $workflow->getMarking($workflowObject)->getPlaces();
                $formattedState = implode(', ', array_keys($currentState));
                $workflowObject->updateState($formattedState);
                $workflowObject->state = $state;
                $workflowObject->save();
            } else {
                DB::rollback();
                return 0;
            }
        } else {
            $workflowObject = WorkflowObject::findOrFail($id);
        }

        $workflow = SELF::criarSymfonyWorkflow($workflowDefinition);
        if ($workflow->can($workflowObject, $transition)) {

            $success = self::verificarFormParaTransition($transition, $workflowObject, $workflowDefinition);
            if (!$success) {
                return $workflowObject->id;
            } 

            $currentPlaces = $workflowObject->state;
            if (self::estaTransicaoBloqueada($workflowDefinition->definition, $transition, $currentPlaces)) {
                return 0; // add mensagem de erro
            }
            $state = $workflow->apply($workflowObject, $transition);
            
            $places = $state->getPlaces();

            foreach ($places as $place => $number) {
                if (isset($workflowDefinition->definition['places'][$place]['max'])) {
                    $max = $workflowDefinition->definition['places'][$place]['max'];
            
                    if ($number > $max) {
                        $places[$place] = $max;  
                    }
                }
            }
            
            $workflowObject->state = $places;
            

            $workflowObject->save();

            
            $workflowObject->save();
        }
        return $workflowObject->id;
    }

    /**
     * Verifica se uma transição está bloqueada porque existem múltiplas transições
     * de estados diferentes que levam ao estado de origem da transição atual,
     * e nem todas foram completadas ainda.
     *
     * @param array $workflowDefinition
     * @param WorkflowObject $workflowObject
     * @param string $transition
     * @param array $currentPlaces
     * @return bool TRUE se a transição está bloqueada, FALSE caso contrário
     */
    public static function estaTransicaoBloqueada($workflowDefinition, $transition, $currentPlaces)
    {
        if(count($currentPlaces) == 1){
            return false;
        }

        $transitions = $workflowDefinition['transitions'];
        
        if (!isset($transitions[$transition])) {
            return false;
        }

        $transitionData = $transitions[$transition];
        $from = $transitionData['from'];
        
        $transitionsToFrom = [];
        foreach ($transitions as $name => $trans) {
            $transTos = is_array($trans['tos']) ? $trans['tos'] : [$trans['tos']];
            
            if (in_array($from, $transTos)) {
                $transitionsToFrom[] = $trans['from'];
            }
        }
        
        if (count($transitionsToFrom) > 1) {
            foreach ($transitionsToFrom as $requiredPlace) {
                if (!isset($currentPlaces[$requiredPlace])) {
                    return true;
                }
            }
        }
        return false;
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

}
