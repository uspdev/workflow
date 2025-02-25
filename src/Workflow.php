<?php

namespace Uspdev\Workflow;

use stdClass;
use App\Models\User;
use Uspdev\Forms\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Uspdev\Forms\Models\FormSubmission;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Validator;
use Uspdev\Workflow\Models\WorkflowObject;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Transition;
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


        return array_map(function ($transition) {
            return $transition->getName();
        }, $enabledTransitions) ?: [];
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
            $key = $workflowObject->id;
            $form = new Form($key);
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

        $workflowData['workflowDefinition'] = $workflowDefinition;
        $workflowData['definitionName'] = $definitionName;
        $workflowData['path'] = $path;
        $workflowData['formattedJson'] = $formattedJson;
        $workflowData['places'] = $workflowDefinition->definition['places'];

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

        $formHtml = Workflow::obterHtml($workflowObject, $workflowDefinition);
        $formHtml = str_replace("workflowDefinitionName", $workflowDefinition->name, $formHtml);
        $formHtml = str_replace("place_name", $workflowDefinition->state, $formHtml);

        $title = $workflowDefinition->definition['title'];
        $activities = Workflow::obterAtividades($workflowObject->id);
        $form = new Form($workflowObject->id);
        $formSubmissions = $form->listSubmission();

        $workflowObjectData['workflowObject'] = $workflowObject;
        $workflowObjectData['workflowDefinition'] = $workflowDefinition;
        $workflowObjectData['workflowsTransitions'] = $workflowsTransitions;
        $workflowObjectData['formHtml'] = $formHtml;
        $workflowObjectData['title'] = $title;
        $workflowObjectData['activities'] = $activities;
        $workflowObjectData['formSubmissions'] = $formSubmissions;

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

        if (isset(json_decode($request->definition)->places)) {
            foreach (json_decode($request->definition)->places as $key => $value) {
                if (is_array($value)) {
                    Role::firstOrCreate(['name' => $key]);
                } else {
                    Role::firstOrCreate(['name' => $key]);

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
        $formattedState = implode(', ', $initialState);
        $state = ['state' => $formattedState];

        // $workflow_definition_name = ['workflow_definition_name' => $workflowDefinitionName];

        // $workflowObject = Workflow::criarWorkflow($workflow_definition_name, $state);

        $workflowInstance = Workflow::criarSymfonyWorkflow($workflowDefinition);

        $fakeWorkflowObject = new \stdClass();
        $fakeWorkflowObject->state = $formattedState;
        $fakeWorkflowObject->id = '0';
        $fakeWorkflowObject->currentState = $formattedState;
        $fakeWorkflowObject->workflowDefinitionName = $workflowDefinitionName;

        $workflowsTransitions['enabled'] =  Workflow::obterNomeDasTransitionsHabilitadas($workflowInstance, null, $fakeWorkflowObject);
        $workflowsTransitions['all'] =  Workflow::obterNomeDasTransitions($workflowInstance);
        $workflowsTransitions['currentState'] =  $state;

        $formName = $workflowDefinition->definition['places'][$formattedState]['forms'];

        $form = new Form();
        $formHtml = $form->generateHtml($formName);
        $formHtml = str_replace("workflowDefinitionName", $workflowDefinition->name, $formHtml);
        $formHtml = str_replace("place_name", $formattedState, $formHtml);

        $workflowObjectData = [
        'workflowObject' => $fakeWorkflowObject,
        'workflowDefinition' => $workflowDefinition,
        'workflowsTransitions' => $workflowsTransitions,
        'formHtml' => $formHtml,
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
                    return new Transition($name, $transition['from'], $transition['to']);
                }, array_keys($transitions), $transitions)
            );

            return new SymfonyWorkflow($workflowDefinition, new MethodMarkingStore(true, 'currentState'));
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
            $currentState = $workflowObject->state;

            foreach ($transitionsData as $transitionName => $transition) {
                if ($transition['from'] === $currentState) {
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

            $state = $workflowDefinition['definition']['initial_places'];
            $workflowObject = Workflow::criarWorkflow($workflow_definition_name, $state);

            $workflowObject->save();

            $workflow = SELF::criarSymfonyWorkflow($workflowDefinition);
            if ($workflow->can($workflowObject, $transition)) {

                if (isset($workflowDefinition->definition['places'][$workflowObject->state]['forms'])) { 
                    $form = new Form();
                    if ($form->getDefinition($workflowDefinition->definition['places'][$workflowObject->state]['forms']) != null) {
                        $cond['form_definition_id'] = $form->getDefinition($workflowDefinition->definition['places'][$workflowObject->state]['forms'])->id;
    
                        $submissions = FormSubmission::where($cond)->get();
                        $hasSubmission = $submissions->where('key', $workflowObject->id)->isNotEmpty();
                        if (!$hasSubmission) {
                            DB::rollback();
                            return 0;
                        }
                    }
                }
                DB::commit();

                $workflow->apply($workflowObject, $transition);
    
                $currentState = $workflow->getMarking($workflowObject)->getPlaces();
                $formattedState = implode(', ', array_keys($currentState));
                $workflowObject->updateState($formattedState);
                $workflowObject->state = $formattedState;
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

            if (isset($workflowDefinition->definition['places'][$workflowObject->state]['forms'])) {
                $form = new Form();
                if ($form->getDefinition($workflowDefinition->definition['places'][$workflowObject->state]['forms']) != null) {
                    $cond['form_definition_id'] = $form->getDefinition($workflowDefinition->definition['places'][$workflowObject->state]['forms'])->id;

                    $submissions = FormSubmission::where($cond)->get();
                    $hasSubmission = $submissions->where('key', $workflowObject->id)->isNotEmpty();
                    if (!$hasSubmission) {
                        return $workflowObject->id;
                    }
                     
                }
            }

            $workflow->apply($workflowObject, $transition);

            $currentState = $workflow->getMarking($workflowObject)->getPlaces();
            $formattedState = implode(', ', array_keys($currentState));
            $workflowObject->updateState($formattedState);
            $workflowObject->state = $formattedState;
            $workflowObject->save();
        }
        return $workflowObject->id;
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

            $state = $workflowDefinition['definition']['initial_places'];

            $workflowObject = Workflow::criarWorkflow($workflow_definition_name, $state);

            $workflowObject->save();
            $request->merge(['form_key' => $workflowObject->id]);
        }

        $form = new Form();
        $form->handleSubmission($request);
        return $workflowObject->id ?? $request->input('form_key');
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
      
        $places = $user->getRoleNames();
        $workflowObjects = collect();

        foreach ($places as $place) {
            $objects = WorkflowObject::where('state', $place)->get();
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
        $place = $request->input('place');

        $user      = Auth::user();
        if (! $user->hasRole($place) && ! Gate::allows('admin')) {
            return response()->json(['alert-danger' => 'Você não tem permissão para gerenciar este departamento.'], 403);
        }

        if ($codpes_rem) {
            $userToRemove = User::where('codpes', $codpes_rem)->first();
            if ($userToRemove) {
                $userToRemove->removeRole($place);
                $request->session()->flash('alert-warning', 'Usuário removido com sucesso!');
            }
        }

        if ($codpes_add) {
            $userToAdd = User::findOrCreateFromReplicado($codpes_add);
            if ($userToAdd) {
                $userToAdd->assignRole($place);
                $request->session()->flash('alert-success', 'Usuário adicionado com sucesso!');
            }
        }
    }

}
