<?php

namespace Uspdev\Workflow;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Uspdev\Workflow\Models\WorkflowObject;
use Uspdev\Workflow\Exceptions\WorkflowDefinitionNotFoundException;

class WorkflowService
{
    /**
     * Localiza ou cria a instância de fluxo do modelo informado.
     * Um objeto de domínio conserva a definição e a versão com as quais foi iniciado.
     */
    public function start(string $workflowName, Model $model): WorkflowObject
    {
        $definition = $this->loadDefinition($workflowName);
        $definitionData = $definition->getDefinitionData();
        $objectId = $model->getKey();

        if ($objectId === null) {
            throw new InvalidArgumentException('O modelo deve estar persistido antes de iniciar um workflow.');
        }

        return WorkflowObject::firstOrCreate(
            [
                'object_type' => $model->getMorphClass(),
                'object_id' => (string) $objectId,
            ],
            [
                'workflow_definition_id' => $definition->getKey(),
                'current_places' => $definitionData->initial_places,
                'variables' => [],
            ],
        );
    }

    /**
     * Retorna a instância de workflow associada ao modelo informado.
     */
    public function find(Model $model): ?WorkflowObject
    {
        if ($model->getKey() === null) {
            return null;
        }

        return WorkflowObject::where('object_type', $model->getMorphClass())
            ->where('object_id', (string) $model->getKey())
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
            $query->where('status', 'published')
                ->orderByDesc('version');
        }

        $definition = $query->first();

        if (!$definition) {
            throw new WorkflowDefinitionNotFoundException("Definição de workflow '{$name}' não foi encontrada.");
        }

        return $definition;
    }
}
