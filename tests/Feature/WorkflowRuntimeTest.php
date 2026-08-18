<?php

namespace Uspdev\Workflow\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use Uspdev\Forms\FormsManager;
use Uspdev\Workflow\Exceptions\TransitionNotAllowedException;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Uspdev\Workflow\Tests\TestCase;
use Uspdev\Workflow\WorkflowService;

class WorkflowRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('aproveitamentos', function (Blueprint $table): void {
            $table->id();
        });
    }

    public function test_start_is_idempotent_for_the_same_domain_object(): void
    {
        $this->definition();
        $model = Aproveitamento::create();
        $service = $this->app->make(WorkflowService::class);

        $first = $service->start('equivalencia', $model);
        $second = $service->start('equivalencia', $model->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(['aluno_inicio'], $first->current_places);
        $this->assertSame($first->id, $service->find($model)?->id);
        $this->assertDatabaseCount('workflow_objects', 1);
    }

    public function test_it_exposes_state_transitions_and_history_for_a_formless_transition(): void
    {
        $this->definition();
        $object = $this->app->make(WorkflowService::class)
            ->start('equivalencia', Aproveitamento::create());
        $user = new WorkflowUser();
        $user->id = 123456;

        $this->assertSame([], $object->getHistory()->all());
        $this->assertSame(['aluno_inicio'], $object->currentPlaces()->pluck('name')->all());
        $this->assertSame(['tr_inicio_conferencia'], $object->enabledTransitions()->pluck('name')->all());
        $this->assertSame(['tr_inicio_conferencia'], collect($object->transitions())->pluck('name')->all());
        $this->assertSame([
            'current_places' => ['aluno_inicio'],
            'actors' => ['aluno'],
            'transitions' => [[
                'name' => 'tr_inicio_conferencia',
                'label' => 'Enviar para conferência',
                'from' => 'aluno_inicio',
                'tos' => ['svgrad_conferencia'],
                'form' => false,
            ]],
        ], $object->workflowState());

        $this->assertTrue($object->apply('tr_inicio_conferencia', [], $user));
        $this->assertSame(['svgrad_conferencia'], $object->current_places);
        $this->assertSame(['tr_retorno'], $object->enabledTransitions()->pluck('name')->all());

        $history = $object->getHistory();
        $this->assertCount(1, $history);
        $this->assertSame('tr_inicio_conferencia', $history->first()->transition_name);
        $this->assertSame(['aluno_inicio'], $history->first()->from_places);
        $this->assertSame(['svgrad_conferencia'], $history->first()->to_places);
        $this->assertSame(123456, $history->first()->user_id);
        $this->assertNull($history->first()->form_submission_id);
        $this->assertSame([], $history->first()->metadata);
    }

    public function test_omitted_null_and_false_forms_do_not_use_forms(): void
    {
        $forms = $this->createMock(FormsManager::class);
        $forms->expects($this->never())->method('definition');
        $forms->expects($this->never())->method('render');
        $forms->expects($this->never())->method('validate');
        $forms->expects($this->never())->method('submit');
        $this->app->instance(FormsManager::class, $forms);

        foreach (['omitted', 'null', 'false'] as $formVariant) {
            $name = "workflow_{$formVariant}";
            $transition = [
                'name' => 'avancar',
                'from' => 'inicio',
                'tos' => ['fim'],
            ];
            if ($formVariant !== 'omitted') {
                $transition['form'] = $formVariant === 'false' ? false : null;
            }

            $this->definition($name, ['inicio'], [
                ['name' => 'inicio', 'roles' => []],
                ['name' => 'fim', 'roles' => []],
            ], [$transition]);

            $object = $this->app->make(WorkflowService::class)
                ->start($name, Aproveitamento::create());

            $this->assertTrue($object->apply('avancar'));
            $this->assertSame(['fim'], $object->current_places);
            $this->assertNull($object->getHistory()->sole()->form_submission_id);
        }
    }

    public function test_a_transition_outside_the_current_state_is_rejected_without_history(): void
    {
        $this->definition();
        $object = $this->app->make(WorkflowService::class)
            ->start('equivalencia', Aproveitamento::create());

        $this->expectException(TransitionNotAllowedException::class);

        try {
            $object->apply('tr_retorno');
        } finally {
            $this->assertSame(['aluno_inicio'], $object->fresh()->current_places);
            $this->assertDatabaseCount('workflow_history', 0);
        }
    }

    /**
     * @param array<int, string> $initialPlaces
     * @param array<int, array<string, mixed>>|null $places
     * @param array<int, array<string, mixed>>|null $transitions
     */
    private function definition(
        string $name = 'equivalencia',
        array $initialPlaces = ['aluno_inicio'],
        ?array $places = null,
        ?array $transitions = null,
    ): WorkflowDefinition {
        return WorkflowDefinition::create([
            'name' => $name,
            'version' => 1,
            'status' => 'published',
            'published_at' => now(),
            'definition' => [
                'name' => $name,
                'initial_places' => $initialPlaces,
                'roles' => [
                    ['name' => 'aluno'],
                    ['name' => 'svgrad'],
                ],
                'places' => $places ?? [
                    ['name' => 'aluno_inicio', 'roles' => ['aluno']],
                    ['name' => 'svgrad_conferencia', 'roles' => ['svgrad']],
                ],
                'transitions' => $transitions ?? [
                    [
                        'name' => 'tr_inicio_conferencia',
                        'label' => 'Enviar para conferência',
                        'from' => 'aluno_inicio',
                        'tos' => ['svgrad_conferencia'],
                        'form' => false,
                    ],
                    [
                        'name' => 'tr_retorno',
                        'from' => 'svgrad_conferencia',
                        'tos' => ['aluno_inicio'],
                        'form' => false,
                    ],
                ],
            ],
        ]);
    }
}

class Aproveitamento extends Model
{
    protected $table = 'aproveitamentos';

    public $timestamps = false;

    protected $guarded = [];
}

class WorkflowUser extends User
{
    public function hasRole(string|array $roles): bool
    {
        return in_array('aluno', (array) $roles, true);
    }
}
