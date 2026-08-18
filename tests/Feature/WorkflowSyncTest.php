<?php

namespace Uspdev\Workflow\Tests\Feature;

use RuntimeException;
use Uspdev\Forms\FormsManager;
use Uspdev\Forms\Models\FormDefinition;
use Uspdev\Workflow\Contracts\RoleResolver;
use Uspdev\Workflow\Exceptions\WorkflowSyncValidationException;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Uspdev\Workflow\Services\WorkflowSyncService;
use Uspdev\Workflow\Tests\TestCase;

class WorkflowSyncTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            is_dir($path) ? @rmdir($path) : @unlink($path);
        }

        parent::tearDown();
    }

    public function test_sync_is_idempotent_and_publishes_a_valid_v2_definition(): void
    {
        $this->bindRoleResolver(fn (string $role): bool => $role === 'operador');
        $this->bindForms(['obs']);
        $file = $this->jsonFile($this->validDefinition());

        $service = $this->app->make(WorkflowSyncService::class);

        $this->assertTrue($service->sync($file));
        $first = WorkflowDefinition::sole();
        $this->assertSame('published', $first->status->value);
        $this->assertSame(1, $first->version);
        $this->assertNotNull($first->published_at);
        $this->assertSame(['inicio'], $first->definition['initial_places']);
        $this->assertSame('inicio', $first->definition['transitions'][0]['from']);
        $this->assertSame(['fim'], $first->definition['transitions'][0]['tos']);
        $this->assertSame('inicio', $first->place('inicio')->name);
        $this->assertSame('concluir', $first->transition('concluir')->name);
        $this->assertSame(['concluir'], $first->transitionsFromPlace('inicio')->pluck('name')->all());

        $publishedAt = $first->published_at->toISOString();
        $updatedAt = $first->updated_at->toISOString();
        $this->travel(1)->second();

        $this->assertTrue($service->workflow_sync($file));

        $this->assertSame(1, WorkflowDefinition::count());
        $syncedAgain = WorkflowDefinition::sole();
        $this->assertSame($first->id, $syncedAgain->id);
        $this->assertSame($publishedAt, $syncedAgain->published_at->toISOString());
        $this->assertSame($updatedAt, $syncedAgain->updated_at->toISOString());
    }

    public function test_sync_aggregates_errors_and_does_not_persist_any_definition_from_the_execution(): void
    {
        WorkflowDefinition::create([
            'name' => 'anterior',
            'definition' => ['preserved' => true],
            'version' => 1,
            'status' => 'published',
        ]);

        $this->bindRoleResolver(function (string $role): bool {
            if ($role === 'resolver_quebrado') {
                throw new RuntimeException('serviço indisponível');
            }

            return $role === 'operador';
        });
        $this->bindForms(['obs']);

        $directory = $this->temporaryDirectory();
        $this->writeJson($directory . '/01-valida.json', $this->validDefinition(name: 'valida'));
        $this->writeJson($directory . '/02-invalida.json', [
            'name' => 'invalida',
            'version' => 2,
            'status' => 'draft',
            'initial_places' => ['place_ausente'],
            'roles' => [
                ['name' => 'role_inexistente'],
                ['name' => 'resolver_quebrado'],
            ],
            'places' => [
                ['name' => 'inicio', 'roles' => ['role_inexistente']],
            ],
            'transitions' => [
                [
                    'name' => 'transicao_invalida',
                    'from' => 'origem_ausente',
                    'tos' => ['destino_ausente'],
                    'form' => 'form_ausente',
                ],
            ],
        ]);

        try {
            $this->app->make(WorkflowSyncService::class)->sync($directory);
            $this->fail('Era esperada uma falha de validação agregada.');
        } catch (WorkflowSyncValidationException $exception) {
            $message = implode("\n", $exception->errors());
            $this->assertStringContainsString("status 'draft'", $message);
            $this->assertStringContainsString("initial_places referencia o place inexistente 'place_ausente'", $message);
            $this->assertStringContainsString("place de origem inexistente 'origem_ausente'", $message);
            $this->assertStringContainsString("place de destino inexistente 'destino_ausente'", $message);
            $this->assertStringContainsString("formulário inexistente 'form_ausente'", $message);
            $this->assertStringContainsString("role inexistente 'role_inexistente'", $message);
            $this->assertStringContainsString("falha do resolver ao validar a role 'resolver_quebrado'", $message);
        }

        $this->assertSame(['anterior'], WorkflowDefinition::pluck('name')->all());
        $this->assertTrue(WorkflowDefinition::sole()->definition['preserved']);
    }

    public function test_sync_fails_closed_when_the_consumer_does_not_provide_a_role_resolver(): void
    {
        $this->bindForms(['obs']);
        $file = $this->jsonFile($this->validDefinition());

        $this->expectException(WorkflowSyncValidationException::class);
        $this->expectExceptionMessage('resolver de roles não foi fornecido');

        try {
            $this->app->make(WorkflowSyncService::class)->sync($file);
        } finally {
            $this->assertDatabaseCount('workflow_definitions', 0);
        }
    }

    public function test_sync_archives_the_previous_published_version(): void
    {
        $this->bindRoleResolver(fn (): bool => true);
        $this->bindForms(['obs']);

        $versionOne = $this->validDefinition();
        $versionOne['version'] = 1;
        $this->app->make(WorkflowSyncService::class)->sync($this->jsonFile($versionOne));

        $versionTwo = $this->validDefinition();
        $versionTwo['version'] = 2;
        $this->app->make(WorkflowSyncService::class)->sync($this->jsonFile($versionTwo));

        $this->assertDatabaseHas('workflow_definitions', [
            'name' => 'equivalencia',
            'version' => 1,
            'status' => 'archived',
        ]);
        $this->assertDatabaseHas('workflow_definitions', [
            'name' => 'equivalencia',
            'version' => 2,
            'status' => 'published',
        ]);
    }

    public function test_command_reports_all_errors_and_returns_failure(): void
    {
        $file = $this->jsonFile([
            'name' => 'invalida',
            'status' => 'draft',
        ]);

        $this->artisan('workflow:sync', ['--path' => $file])
            ->expectsOutputToContain("status 'draft'")
            ->expectsOutputToContain("'initial_places' deve ser uma lista")
            ->assertFailed();

        $this->assertDatabaseCount('workflow_definitions', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function validDefinition(string $name = 'equivalencia'): array
    {
        return [
            'name' => $name,
            'description' => 'Fluxo de teste',
            'initial_places' => ['inicio'],
            'roles' => [
                ['name' => 'operador', 'label' => 'Operador'],
            ],
            'places' => [
                ['name' => 'inicio', 'roles' => ['operador']],
                ['name' => 'fim', 'roles' => []],
            ],
            'transitions' => [
                [
                    'name' => 'concluir',
                    'from' => 'inicio',
                    'tos' => ['fim'],
                    'form' => 'obs',
                ],
            ],
        ];
    }

    /**
     * @param array<int, string> $existingForms
     */
    private function bindForms(array $existingForms): void
    {
        $forms = $this->createMock(FormsManager::class);
        $forms->method('definition')->willReturnCallback(
            fn (string $name): ?FormDefinition => in_array($name, $existingForms, true)
                ? new FormDefinition(['name' => $name])
                : null,
        );
        $this->app->instance(FormsManager::class, $forms);
    }

    private function bindRoleResolver(callable $callback): void
    {
        $this->app->instance(RoleResolver::class, new class($callback) implements RoleResolver
        {
            public function __construct(private readonly mixed $callback) {}

            public function exists(string $role): bool
            {
                return ($this->callback)($role);
            }
        });
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function jsonFile(array $definition): string
    {
        $file = tempnam(sys_get_temp_dir(), 'workflow-sync-');
        $this->temporaryPaths[] = $file;
        $this->writeJson($file, $definition);

        return $file;
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/workflow-sync-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $this->temporaryPaths[] = $directory;

        return $directory;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function writeJson(string $file, array $definition): void
    {
        file_put_contents($file, json_encode($definition, JSON_THROW_ON_ERROR));
        if (!in_array($file, $this->temporaryPaths, true)) {
            $this->temporaryPaths[] = $file;
        }
    }
}
