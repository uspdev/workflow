<?php

namespace Uspdev\Workflow\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Uspdev\Workflow\Models\WorkflowObject start(string $workflowName, \Illuminate\Database\Eloquent\Model $model)
 * @method static \Uspdev\Workflow\Models\WorkflowObject|null find(\Illuminate\Database\Eloquent\Model $model)
 * @method static \Uspdev\Workflow\Models\WorkflowDefinition loadDefinition(string $name, ?int $version = null)
 *
 * @see \Uspdev\Workflow\WorkflowService
 */
class Workflow extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'workflow';
    }
}
