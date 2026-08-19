<?php

namespace Uspdev\Workflow\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uspdev\Workflow\DTO\WorkflowDefinitionData;
use Uspdev\Workflow\Exceptions\InvalidWorkflowDefinitionException;

class NotificationDefinitionTest extends TestCase
{
    public function test_destination_roles_are_the_defaults_and_append_roles_only_adds_roles(): void
    {
        $definition = WorkflowDefinitionData::fromArray([
            'name' => 'notifications',
            'initial_places' => ['inicio'],
            'roles' => [
                ['name' => 'destino'],
                ['name' => 'repetida'],
                ['name' => 'adicional'],
            ],
            'places' => [
                ['name' => 'inicio', 'roles' => []],
                ['name' => 'destino_a', 'roles' => ['destino', 'repetida']],
                ['name' => 'destino_b', 'roles' => ['repetida']],
            ],
            'transitions' => [[
                'name' => 'avancar',
                'from' => 'inicio',
                'tos' => ['destino_a', 'destino_b'],
                'notifications' => [
                    'append_roles' => ['adicional', 'repetida'],
                ],
            ]],
        ]);

        $this->assertSame([
            'roles' => ['destino', 'repetida', 'repetida', 'adicional', 'repetida'],
        ], $definition->resolveNotificationsFor('avancar'));
        $this->assertSame([
            'append_roles' => ['adicional', 'repetida'],
        ], $definition->transition('avancar')->notifications->toArray());
    }

    public function test_absence_of_destination_and_appended_roles_resolves_to_no_notification_work(): void
    {
        $definition = WorkflowDefinitionData::fromArray([
            'name' => 'sem_destinatarios',
            'initial_places' => ['inicio'],
            'roles' => [],
            'places' => [
                ['name' => 'inicio', 'roles' => []],
                ['name' => 'fim', 'roles' => []],
            ],
            'transitions' => [[
                'name' => 'avancar',
                'from' => 'inicio',
                'tos' => ['fim'],
            ]],
        ]);

        $this->assertSame(['roles' => []], $definition->resolveNotificationsFor('avancar'));
    }

    public function test_notifications_rejects_a_list_instead_of_a_single_object(): void
    {
        $definition = $this->minimalDefinition();
        $definition['transitions'][0]['notifications'] = [
            ['append_roles' => ['adicional']],
        ];

        $this->expectException(InvalidWorkflowDefinitionException::class);
        $this->expectExceptionMessage("'notifications' deve ser um único objeto");

        WorkflowDefinitionData::fromArray($definition);
    }

    public function test_unsupported_notification_destination_types_are_rejected_explicitly(): void
    {
        $definition = $this->minimalDefinition();
        $definition['transitions'][0]['notifications'] = [
            'override_roles' => [],
            'users' => [],
            'emails' => [],
        ];

        try {
            WorkflowDefinitionData::fromArray($definition);
            $this->fail('Era esperado rejeitar destinatários fora do contrato V2.');
        } catch (InvalidWorkflowDefinitionException $exception) {
            $errors = implode("\n", $exception->errors());
            $this->assertStringContainsString("'override_roles' não é suportado", $errors);
            $this->assertStringContainsString("'users' não é suportado", $errors);
            $this->assertStringContainsString("'emails' não é suportado", $errors);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalDefinition(): array
    {
        return [
            'name' => 'notifications',
            'initial_places' => ['inicio'],
            'roles' => [['name' => 'adicional']],
            'places' => [
                ['name' => 'inicio', 'roles' => []],
                ['name' => 'fim', 'roles' => []],
            ],
            'transitions' => [[
                'name' => 'avancar',
                'from' => 'inicio',
                'tos' => ['fim'],
            ]],
        ];
    }
}
