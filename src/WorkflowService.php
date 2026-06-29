<?php

namespace Uspdev\Workflow;

use Illuminate\Database\Eloquent\Model;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Uspdev\Workflow\Models\WorkflowObject;
use Uspdev\Workflow\Exceptions\WorkflowDefinitionNotFoundException;

class WorkflowService
{
    /**
     * Cria uma nova instância de fluxo (WorkflowObject) para o modelo informado.
     * Posiciona o objeto nos estados definidos no primeiro 'place' ou marcação inicial.
     */
    public function start(string $workflowName, Model $model): WorkflowObject
    {
        // 1. Carrega a definição para garantir que ela existe e está ativa
        $definition = $this->loadDefinition($workflowName);
        $definitionData = $definition->getDefinitionData();

        // 3. Cria a instância viva do processo (WorkflowObject)
        return WorkflowObject::create([
            'workflow_definition_id' => $definition->id,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'current_place' => $definitionData->initial_marking,
        ]);
    }

    /**
     * Retorna a instância de workflow associada ao modelo informado.
     */
    public function find(Model $model): ?WorkflowObject
    {
        return WorkflowObject::where('model_type', get_class($model))
            ->where('model_id', $model->getKey())
            ->first();
    }

    /**
     * Retorna a definição de um workflow. Lança exceção se não encontrar.
     * * @throws WorkflowDefinitionNotFoundException
     */
    public function loadDefinition(string $name, ?int $version = null): WorkflowDefinition
    {
        $query = WorkflowDefinition::where('name', $name);

        if ($version !== null) {
            $query->where('version', $version);
        } else {
            $query->where('is_published', true);
        }

        $definition = $query->first();

        if (!$definition) {
            throw new WorkflowDefinitionNotFoundException("Definição de workflow '{$name}' não foi encontrada.");
        }

        return $definition;
    }
}
