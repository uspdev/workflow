<?php

namespace Uspdev\Workflow\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uspdev\Workflow\DTO\BindingDefinition;
use Uspdev\Workflow\DTO\NotificationDefinition;
use Uspdev\Workflow\DTO\PlaceDefinition;
use Uspdev\Workflow\DTO\RoleDefinition;
use Uspdev\Workflow\DTO\TransitionDefinition;
use Uspdev\Workflow\DTO\WorkflowDefinitionData;
use Uspdev\Workflow\DTO\WorkflowDefinitionImport;
use Uspdev\Workflow\Exceptions\InvalidWorkflowDefinitionException;

class WorkflowDefinitionDataTest extends TestCase
{
    public function test_from_array_is_the_structural_validation_and_parsing_seam(): void
    {
        $definition = WorkflowDefinitionData::fromArray([
            'type' => 'workflow',
            'supports' => ['App\\Models\\Aproveitamento'],
            'name' => 'equivalencia',
            'label' => 'Equivalência',
            'description' => 'Fluxo acadêmico',
            'initial_places' => ['inicio'],
            'roles' => [
                ['name' => 'aluno'],
                ['name' => 'svgrad'],
            ],
            'places' => [
                ['name' => 'inicio', 'roles' => ['aluno']],
                ['name' => 'conferencia', 'roles' => ['svgrad']],
            ],
            'transitions' => [
                [
                    'name' => 'enviar',
                    'from' => 'inicio',
                    'tos' => ['conferencia'],
                    'form' => 'obs',
                    'bindings' => [
                        ['attribute' => 'autor', 'from' => 'form.autor', 'resolver' => 'direct'],
                    ],
                    'notifications' => ['append_roles' => ['aluno']],
                ],
            ],
        ]);

        $this->assertInstanceOf(RoleDefinition::class, $definition->roles->first());
        $this->assertInstanceOf(PlaceDefinition::class, $definition->places->first());
        $this->assertInstanceOf(TransitionDefinition::class, $definition->transitions->first());
        $this->assertInstanceOf(BindingDefinition::class, $definition->transitions->first()->bindings->first());
        $this->assertInstanceOf(NotificationDefinition::class, $definition->transitions->first()->notifications);
        $this->assertSame(['inicio'], $definition->initial_places);
        $this->assertSame('inicio', $definition->transitions->first()->from);
        $this->assertSame(['conferencia'], $definition->transitions->first()->tos);
        $this->assertSame(['obs'], $definition->referencedForms());
        $this->assertSame(['aluno', 'svgrad'], $definition->referencedRoles());
        $this->assertSame(['App\\Models\\Aproveitamento'], $definition->toArray()['supports']);
    }

    public function test_dto_aggregates_nested_and_graph_errors(): void
    {
        try {
            WorkflowDefinitionData::fromArray([
                'name' => 'invalida',
                'initial_places' => ['ausente'],
                'roles' => [['name' => 'aluno']],
                'places' => [
                    ['name' => 'inicio', 'roles' => ['role_ausente']],
                ],
                'transitions' => [
                    [
                        'name' => 'enviar',
                        'from' => 'origem_ausente',
                        'tos' => ['destino_ausente'],
                        'bindings' => [['attribute' => '', 'from' => 123]],
                        'notifications' => [['append_roles' => ['aluno']]],
                    ],
                ],
            ]);
            $this->fail('Era esperada uma definição inválida.');
        } catch (InvalidWorkflowDefinitionException $exception) {
            $errors = implode("\n", $exception->errors());
            $this->assertStringContainsString("bindings.0: 'attribute'", $errors);
            $this->assertStringContainsString("bindings.0: 'from'", $errors);
            $this->assertStringContainsString("bindings.0: 'resolver'", $errors);
            $this->assertStringContainsString("'notifications' deve ser um único objeto", $errors);
            $this->assertStringContainsString("initial_places referencia o place inexistente 'ausente'", $errors);
            $this->assertStringContainsString("role não declarada 'role_ausente'", $errors);
        }
    }

    public function test_import_dto_validates_version_and_status_together(): void
    {
        $payload = $this->minimalDefinition();
        $payload['version'] = 0;
        $payload['status'] = 'draft';

        try {
            WorkflowDefinitionImport::fromArray($payload);
            $this->fail('Era esperado rejeitar os metadados de importação.');
        } catch (InvalidWorkflowDefinitionException $exception) {
            $errors = implode("\n", $exception->errors());
            $this->assertStringContainsString("'version' deve ser um inteiro maior que zero", $errors);
            $this->assertStringContainsString("status 'draft' não é permitido", $errors);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalDefinition(): array
    {
        return [
            'name' => 'equivalencia',
            'initial_places' => ['inicio'],
            'roles' => [],
            'places' => [['name' => 'inicio', 'roles' => []]],
            'transitions' => [[
                'name' => 'permanecer',
                'from' => 'inicio',
                'tos' => ['inicio'],
                'form' => false,
            ]],
        ];
    }
}
