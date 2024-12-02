<?php

namespace Uspdev\Workflow;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Transition;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Workflow as SymfonyWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Uspdev\Workflow\Models\WorkflowObject;
use Uspdev\Workflow\Models\User;

use Uspdev\Forms\Form;
use Spatie\Activitylog\Models\Activity;
use stdClass;

class Workflow
{
    public static function obterTodosWorkflowDefinitions()
    {
        return WorkflowDefinition::all();
    }

    public static function obterWorkflowDefinition($definitionName)
    {
        return WorkflowDefinition::where('name', $definitionName)->firstOrFail();
    }

    public static function obterTodosWorkflowObjects()
    {
        return WorkflowObject::all();
    }

    public static function obterWorkflowObject($id)
    {
        return WorkflowObject::findOrFail($id);
    }

    public static function obterAtividades($id)
    {
        return Activity::where('subject_type', WorkflowObject::class)
            ->where('subject_id', $id)
            ->get();
    }

    public static function obterNomeDasTransitions(SymfonyWorkflow $workflowInstance)
    {
        $allTransitions =  $workflowInstance->getDefinition()->getTransitions();

        return array_map(function ($transition) {
            return $transition->getName();
        }, $allTransitions) ?: [];
    }

    public static function obterNomeDasTransitionsHabilitadas(SymfonyWorkflow $workflowInstance, WorkflowObject $workflowObject = null, stdClass $fakeWorkflowObject = null)
    {
        $workflowObject = $workflowObject ?? $fakeWorkflowObject;
        $enabledTransitions = $workflowInstance->getEnabledTransitions($workflowObject);


        return array_map(function ($transition) {
            return $transition->getName();
        }, $enabledTransitions) ?: [];
    }

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

    public static function obterDadosDoObjeto($workflowObjectId)
    {
        $workflowObject = Workflow::obterWorkflowObject($workflowObjectId);

        $workflowDefinition = Workflow::obterWorkflowDefinition($workflowObject->workflow_definition_name);
        $workflowInstance = Workflow::criarSymfonyWorkflow($workflowDefinition);

        $workflowsTransitions[$workflowObject->id] = [
            'enabled' => Workflow::obterNomeDasTransitionsHabilitadas($workflowInstance, $workflowObject),
            'all' => Workflow::obterNomeDasTransitions($workflowInstance),
            'currentState' => $workflowObject->state,
        ];

        $formHtml = Workflow::obterHtml($workflowObject, $workflowDefinition);
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

    public static function criarWorkflow($definitionName, $state, $userCodpes = null)
    {
        $userCodpes = $userCodpes ?: auth()->user()->codpes;
        if (!is_array($state)) {
            $state = ['state' => $state];
        }
        return WorkflowObject::create(array_merge($state, $definitionName, ['user_codpes' => $userCodpes]));
    }

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

        WorkflowDefinition::create([
            'name' => $request->name,
            'description' => $request->description,
            'definition' => json_decode($request->definition),
        ]);
    }

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

        $workflowsTransitions[0] = [
            'enabled' => Workflow::obterNomeDasTransitionsHabilitadas($workflowInstance, null, $fakeWorkflowObject),
            'all' => Workflow::obterNomeDasTransitions($workflowInstance),
            'currentState' => $state,
        ];


        $formName = $workflowDefinition->definition['places'][$formattedState]['forms'];

        $form = new Form();
        $formHtml = $form->generateHtml($formName);

        $title = $workflowDefinition->definition['title'];
        $workflowObjectData['workflowObject'] = $fakeWorkflowObject;
        $workflowObjectData['workflowDefinition'] = $workflowDefinition;
        $workflowObjectData['workflowsTransitions'] = $workflowsTransitions;
        $workflowObjectData['formHtml'] = $formHtml;;
        $workflowObjectData['title'] = $title;
        $workflowObjectData['activities'] = [];
        $workflowObjectData['formSubmissions'] = [];

        return $workflowObjectData;
    }

    public static function definirUsuarios(Request $request)
    {
        $codpes_rem = $request->input('codpes_rem');
        $codpes_add = $request->input('codpes_add');
        $place = $request->input('place');
        $workflowDefinitionName = $request->input('workflowDefinitionName');
        $workflowDefinition = WorkflowDefinition::where('name',  $workflowDefinitionName)->first();

        if (!$workflowDefinition) {
            return response()->json(['message' => 'Workflow não encontrado'], 404);
        }

        $userToRemove = User::where('codpes', $codpes_rem)->first();
        if ($userToRemove) {
            $workflowDefinition->users()->wherePivot('place', $place)->detach($userToRemove->codpes);
        }

        if (isset($codpes_add)) {
            $userToAdd = User::findOrCreateFromReplicado($codpes_add);
            $workflowDefinition->users()->attach($userToAdd->codpes, ['place' => $place]);
        }
    }

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

    public static function deletarWorkflow($workflowId)
    {
        $workflowObject = WorkflowObject::findOrFail($workflowId);
        $workflowObject->delete();
    }

    public static function deletarDefinicaodeWorkflow($definitionName)
    {
        $workflowDefinition = WorkflowDefinition::where('name', $definitionName)->firstOrFail();
        $workflowDefinition->delete();
    }

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

    public static function aplicarTransition($id, $transition, $workflowDefinitionName) //voltar aqui
    {
        // dd($workflowDefinitionName);

        $workflowDefinition = WorkflowDefinition::where('name', $workflowDefinitionName)->firstOrFail();
        if ($id == 0) {
            $workflow_definition_name = ['workflow_definition_name' => $workflowDefinitionName];

            $state = $workflowDefinition['definition']['initial_places'];
            $workflowObject = Workflow::criarWorkflow($workflow_definition_name, $state);
            // dd($workflowObject);

            $workflowObject->save();
        } else {
            $workflowObject = WorkflowObject::findOrFail($id);
        }

        $workflow = SELF::criarSymfonyWorkflow($workflowDefinition);

        if ($workflow->can($workflowObject, $transition)) {

            // if (isset($workflowDefinition->definition['places'][$workflowObject->state]['forms'])) {
            //     $form = new Form();
            //     if ($form->getDefinition($workflowDefinition->definition['places'][$workflowObject->state]['forms']) != null) {
            //         $cond['form_definition_id'] = $form->getDefinition($workflowDefinition->definition['places'][$workflowObject->state]['forms'])->id;

            //         $submissions = FormSubmission::where($cond)->get();

            //         $hasSubmission = $submissions->where('key', $workflowObject->id)->isNotEmpty();
            //         if (!$hasSubmission) {
            //             return back()
            //                 ->with('error', 'Você deve enviar o formulário necessário antes de aplicar essa transição!');
            //         }
            //     }
            // }

            $workflow->apply($workflowObject, $transition);

            $currentState = $workflow->getMarking($workflowObject)->getPlaces();
            $formattedState = implode(', ', array_keys($currentState));
            $workflowObject->updateState($formattedState);
            $workflowObject->state = $formattedState;

            $workflowObject->save();
        }
        // dd($workflowObject);
        return $workflowObject->id;
    }

    public static function enviarFormulario(Request $request)
    {
        $form = new Form();
        $form->handleSubmission($request);
    }

    public static function listarWorkflowsObjectsRelacionados($userCodpes = null)
    {
        $userCodpes = $userCodpes ?: auth()->user()->codpes;

        $user = User::where('codpes', $userCodpes)->first();
        $workflowDefinitions = $user->workflowDefinitions;

        $workflowObjects = collect();

        foreach ($workflowDefinitions as $workflowDefinition) {
            $place = $workflowDefinition->pivot->place;

            $objects = WorkflowObject::where('workflow_definition_name', $workflowDefinition->name)
                ->where('user_codpes', $user->codpes)
                ->where('state', $place)
                ->get();

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
}
