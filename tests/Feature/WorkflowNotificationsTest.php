<?php

namespace Uspdev\Workflow\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Uspdev\Workflow\Contracts\RoleResolver;
use Uspdev\Workflow\Exceptions\WorkflowSyncValidationException;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Uspdev\Workflow\Services\WorkflowSyncService;
use Uspdev\Workflow\Tests\TestCase;
use Uspdev\Workflow\WorkflowService;

class WorkflowNotificationsTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporaryPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('notification_subjects', function (Blueprint $table): void {
            $table->id();
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_nonexistent_notification_role_prevents_the_sync(): void
    {
        $this->bindRoleResolver(fn (string $role): bool => $role !== 'inexistente');
        $definition = $this->definition();
        $definition['roles'][] = ['name' => 'inexistente'];
        $definition['transitions'][0]['notifications'] = [
            'append_roles' => ['inexistente'],
        ];

        $this->expectException(WorkflowSyncValidationException::class);
        $this->expectExceptionMessage("role inexistente 'inexistente'");

        try {
            $this->sync($definition);
        } finally {
            $this->assertDatabaseCount('workflow_definitions', 0);
        }
    }

    public function test_unsupported_notification_fields_prevent_the_sync(): void
    {
        $this->bindRoleResolver(fn (): bool => true);
        $definition = $this->definition();
        $definition['transitions'][0]['notifications'] = [
            'override_roles' => [],
            'users' => [],
            'emails' => [],
        ];

        try {
            $this->sync($definition);
            $this->fail('Era esperado rejeitar a configuração de notifications.');
        } catch (WorkflowSyncValidationException $exception) {
            $errors = implode("\n", $exception->errors());
            $this->assertStringContainsString("'override_roles' não é suportado", $errors);
            $this->assertStringContainsString("'users' não é suportado", $errors);
            $this->assertStringContainsString("'emails' não é suportado", $errors);
        }

        $this->assertDatabaseCount('workflow_definitions', 0);
    }

    public function test_a_valid_role_without_members_does_not_invalidate_or_roll_back_the_transition(): void
    {
        $validatedRoles = [];
        $this->bindRoleResolver(function (string $role) use (&$validatedRoles): bool {
            $validatedRoles[] = $role;

            return true;
        });

        $definition = $this->definition();
        $definition['transitions'][0]['notifications'] = [
            'append_roles' => ['sem_membros'],
        ];

        $this->assertTrue($this->sync($definition));
        $this->assertContains('sem_membros', $validatedRoles);

        $subject = NotificationSubject::create();
        $object = $this->app->make(WorkflowService::class)
            ->start('notifications', $subject);

        $this->assertTrue($object->apply('avancar'));
        $this->assertSame(['fim'], $object->current_places);
        $this->assertSame('avancar', $object->getHistory()->sole()->transition_name);
    }

    public function test_a_workflow_without_roles_needs_no_resolver_or_notification_work(): void
    {
        $definition = $this->definition();
        $definition['roles'] = [];
        $definition['places'][1]['roles'] = [];

        $this->assertTrue($this->sync($definition));

        $storedDefinition = WorkflowDefinition::sole()->getDefinitionData();
        $this->assertSame(
            ['roles' => []],
            $storedDefinition->resolveNotificationsFor('avancar'),
        );

        $object = $this->app->make(WorkflowService::class)
            ->start('notifications', NotificationSubject::create());

        $this->assertTrue($object->apply('avancar'));
        $this->assertSame(['fim'], $object->current_places);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(): array
    {
        return [
            'name' => 'notifications',
            'initial_places' => ['inicio'],
            'roles' => [
                ['name' => 'destino'],
                ['name' => 'sem_membros'],
            ],
            'places' => [
                ['name' => 'inicio', 'roles' => []],
                ['name' => 'fim', 'roles' => ['destino']],
            ],
            'transitions' => [[
                'name' => 'avancar',
                'from' => 'inicio',
                'tos' => ['fim'],
                'form' => false,
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function sync(array $definition): bool
    {
        $file = tempnam(sys_get_temp_dir(), 'workflow-notifications-');
        $this->temporaryPaths[] = $file;
        file_put_contents($file, json_encode($definition, JSON_THROW_ON_ERROR));

        return $this->app->make(WorkflowSyncService::class)->sync($file);
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
}

class NotificationSubject extends Model
{
    protected $table = 'notification_subjects';

    public $timestamps = false;

    protected $guarded = [];
}
