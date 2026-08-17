<?php

namespace Uspdev\Workflow\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Uspdev\Workflow\Models\WorkflowHistory;
use Uspdev\Workflow\Models\WorkflowObject;
use Uspdev\Workflow\Tests\TestCase;

class WorkflowV2SchemaTest extends TestCase
{
    public function test_provider_loads_only_the_workflow_v2_schema(): void
    {
        $this->assertTrue(Schema::hasColumns('workflow_definitions', [
            'id', 'name', 'description', 'definition', 'version', 'status', 'published_at',
        ]));
        $this->assertTrue(Schema::hasColumns('workflow_objects', [
            'workflow_definition_id', 'object_type', 'object_id', 'current_places', 'variables',
        ]));
        $this->assertTrue(Schema::hasColumns('workflow_history', [
            'workflow_object_id', 'transition_name', 'from_places', 'to_places',
            'user_id', 'form_submission_id', 'metadata',
        ]));

        $this->assertFalse(Schema::hasTable('workflow_role_users'));
        $this->assertFalse(Schema::hasTable('workflow_role_emails'));
        $this->assertFalse(Schema::hasTable('user_workflow_definition'));
        $this->assertFalse(Schema::hasColumn('workflow_objects', 'model_type'));
        $this->assertFalse(Schema::hasColumn('workflow_objects', 'current_place'));
        $this->assertFalse(Schema::hasColumn('workflow_history', 'transition'));
    }

    public function test_models_persist_definition_object_and_history_using_the_v2_contract(): void
    {
        $definition = WorkflowDefinition::create([
            'name' => 'equivalencia',
            'definition' => ['initial_places' => ['aluno_inicio']],
            'version' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $object = WorkflowObject::create([
            'workflow_definition_id' => $definition->id,
            'object_type' => 'App\\Models\\Aproveitamento',
            'object_id' => 'aproveitamento-42',
            'current_places' => ['aluno_inicio'],
            'variables' => ['parecerista' => 123456],
        ]);

        $history = $object->history()->create([
            'transition_name' => 'tr_inicio_conferencia',
            'from_places' => ['aluno_inicio'],
            'to_places' => ['svgrad_conferencia'],
            'user_id' => 999999,
            'form_submission_id' => 888888,
            'metadata' => ['source' => 'test'],
        ]);

        $this->assertNotNull($definition->id);
        $this->assertSame($definition->id, $object->definition->id);
        $this->assertSame(['aluno_inicio'], $object->current_places);
        $this->assertSame(['parecerista' => 123456], $object->variables);
        $this->assertSame(['aluno_inicio'], $history->from_places);
        $this->assertSame(['svgrad_conferencia'], $history->to_places);
        $this->assertSame(['source' => 'test'], $history->metadata);
        $this->assertSame($history->id, $object->history->first()->id);
    }

    public function test_internal_foreign_keys_are_enforced_without_external_foreign_keys(): void
    {
        $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('workflow_history')"));

        $this->assertCount(1, $foreignKeys);
        $this->assertSame('workflow_objects', $foreignKeys->first()->table);

        $this->expectException(QueryException::class);
        WorkflowHistory::create([
            'workflow_object_id' => 999999,
            'transition_name' => 'inexistente',
            'from_places' => [],
            'to_places' => [],
            'user_id' => 999999,
            'form_submission_id' => 999999,
        ]);
    }

    public function test_history_is_append_only(): void
    {
        [, , $history] = $this->persistWorkflow();

        try {
            $history->update(['metadata' => ['changed' => true]]);
            $this->fail('Era esperado impedir a alteracao do historico.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $history->refresh();

        $this->expectException(LogicException::class);
        $history->delete();
    }

    public function test_object_with_history_cannot_be_deleted_by_cascade(): void
    {
        [, $object] = $this->persistWorkflow();

        $this->expectException(QueryException::class);
        $object->delete();
    }

    private function persistWorkflow(): array
    {
        $definition = WorkflowDefinition::create([
            'name' => 'equivalencia',
            'definition' => ['initial_places' => ['aluno_inicio']],
            'version' => 1,
            'status' => 'published',
        ]);
        $object = WorkflowObject::create([
            'workflow_definition_id' => $definition->id,
            'object_type' => 'App\\Models\\Aproveitamento',
            'object_id' => '42',
            'current_places' => ['aluno_inicio'],
        ]);
        $history = $object->history()->create([
            'transition_name' => 'tr_inicio_conferencia',
            'from_places' => ['aluno_inicio'],
            'to_places' => ['svgrad_conferencia'],
        ]);

        return [$definition, $object, $history];
    }
}
