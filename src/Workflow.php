<?php
namespace Uspdev\Workflow;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Workflow as SymfonyWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Uspdev\Workflow\Models\WorkflowObject;
use Uspdev\Forms\Form;
use Uspdev\Forms\Models\FormSubmission;
use Spatie\Activitylog\Models\Activity;


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

    public static function obterNomeDasTransitionsHabilitadas(SymfonyWorkflow $workflowInstance, WorkflowObject $workflowObject)
    {
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
        $path = "storage/app/public/".$definitionName.".png";
        $formattedJson = json_encode($definitionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); 

        $workflowData['definitionName'] = $definitionName;
        $workflowData['path'] = $path;
        $workflowData['formattedJson'] = $formattedJson;

        return $workflowData;
    }

    public static function obterDadosDoObjeto($workflowObjectId)
    {
        $workflowObject = Workflow::obterWorkflowObject($workflowObjectId);
        $currentState = $workflowObject->state;

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

    public static function criarWorkflow($definitionName, $state, $userId = null)
    {
        $userId = $userId ?: auth()->user()->id;
        return WorkflowObject::create(array_merge($state, $definitionName, ['user_id' => $userId]));
    }

    public static function atualizarWorkflow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'nullable|string',
            'definition' => 'required|json',
        ]);

        if ($validator->fails()) {
            return redirect()->route('adminworkflows.edit', ['adminworkflow' => $request->name])
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
            return redirect()->route('adminworkflows.create')
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

        $workflow_definition_name = ['workflow_definition_name' => $workflowDefinitionName];

        $workflowObject = Workflow::criarWorkflow($workflow_definition_name, $state);

        return $workflowObject;
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

    public static function listarWorkflowsdoUser($userId = null)
    {
        $userId = $userId ?: auth()->user()->id;

        $workflowsDisplay = [];

        $workflows = WorkflowObject::where('user_id', $userId)->get();
        $workflowData = [];
        foreach ($workflows as $workflowObject) {
            $workflowDefinition = Workflow::obterWorkflowDefinition($workflowObject->workflow_definition_name);
            $workflowInstance = Workflow::criarSymfonyWorkflow($workflowDefinition);
            $enabledTransitions = $workflowInstance->getEnabledTransitions($workflowObject);

            $workflowData[$workflowObject->id]['workflowDefinition'] = $workflowDefinition;

            if($workflowObject->state == $workflowDefinition->definition['initial_places']){
                $workflowData[$workflowObject->id]['state'] = 'start';
            } else if(count($enabledTransitions) > 0){
                $workflowData[$workflowObject->id]['state'] = 'progress';
            } else {
                $workflowData[$workflowObject->id]['state'] = 'end';
            }
        }
        $workflowsDisplay['workflows'] = $workflows;
        $workflowsDisplay['workflowData'] = $workflowData;

        return $workflowsDisplay;
    }

    public static function aplicarTransition($id, $transition)
    {
        $workflowObject = WorkflowObject::findOrFail($id);
        $workflowDefinition = WorkflowDefinition::where('name', $workflowObject->workflow_definition_name)->firstOrFail();

        $workflow = SELF::criarSymfonyWorkflow($workflowDefinition);

        if ($workflow->can($workflowObject, $transition)) {

            if (isset($workflowDefinition->definition['places'][$workflowObject->state]['forms'])) {
                $form = new Form();

                if ($form->getDefinition($workflowDefinition->definition['places'][$workflowObject->state]['forms']) != null) {
                    $cond['form_definition_id'] = $form->getDefinition($workflowDefinition->definition['places'][$workflowObject->state]['forms'])->id;

                    $submissions = FormSubmission::where($cond)->get();

                    $hasSubmission = $submissions->where('key', $workflowObject->id)->isNotEmpty();
                    if (!$hasSubmission) {
                        return redirect()->route('workflows.show', ['id' => $workflowObject->id])
                            ->with('error', 'Você deve enviar o formulário necessário antes de aplicar essa transição!');
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
    }
}
