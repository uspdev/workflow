<?php

namespace Uspdev\Workflow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Uspdev\Forms\Form;
use Uspdev\Workflow\Exceptions\TransitionNotAllowedException;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Uspdev\Workflow\DTO\TransitionDefinition;
use Uspdev\Workflow\Workflow;
use Gate;

class WorkflowObject extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = ['workflow_definition_id', 'object_type', 'object_id', 'current_places', 'variables'];

    protected $casts = [
        'current_places' => 'array',
        'variables' => 'array',
    ];

    private function isTransitionBlocked(TransitionDefinition $transition)
    {
        if(is_array($transition->from))
        {
            foreach($transition->from as $place)   
            {
                if(!isset($this->current_places[$place]) || $this->current_places[$place] != 1)
                {
                    return true;
                }
            }
        }
        else if(!in_array($transition->from, $this->current_places))
        {
            return true;
        }
        return false;
    }

    /**
     *  Retorna dados relevantes referentes um objeto de workflow
     *  Com o id correspondente ao passado de parâmetro na chamada do método
     * 
     *  - Os dados são retornados em um array com as seguintes chaves:
     *  -- 'workflowObject' - Instância de 'WorkflowObject';
     *  -- 'workflowDefinition' - OInstância da 'WorkflowDefinition' atrelada ao WorkflowObject;
     *  -- 'workflowsTransitions' - Array de transições com as chaves 'enabled', 'all' e 'currentState';
     *      --- essas chaves contém, respectivamente, as transições habilitadas para o objeto, todas as          transições e o estado ('place') atual
     * 
     *  -- 'formHtml' - HMTL formatado do formulário relacionado ao estado/place atual do objeto;
     *  -- 'title' - Título da definição;
     *  -- 'activity' - Array de registro de atividades para aquele objeto;
     *  -- 'formSubmissions' - Array de submissões de formulários para aquele objeto;
     * 
     * @return Array $workflowObjectData
     */
    public static function obterDadosDoObjeto(int $object_id)
    {
        $workflowObject = WorkflowObject::find($object_id)->firstOrFail();
        $workflowDefinition = WorkflowDefinition::where('id', $workflowObject->workflow_definition_id)->firstOrFail();
        $workflowInstance = Workflow::criarSymfonyWorkflow($workflowDefinition);

        $workflowsTransitions['enabled'] =  Workflow::obterTransitionsHabilitadas($workflowInstance, $workflowObject);
        $workflowsTransitions['all'] =  Workflow::obterNomeDasTransitions($workflowInstance);
        $workflowsTransitions['currentState'] =  $workflowObject->current_places;
        $workflowsTransitions['allowed'] = [];
        $form = new Form(['key' => $workflowObject->id]);

        $forms = [];
        
        foreach($workflowsTransitions['enabled'] as $enabledTransition){
            if (isset($workflowDefinition->definition['transitions'][$enabledTransition]['forms'])) {
                foreach($workflowDefinition->definition['transitions'][$enabledTransition]['forms'] as $formName){

                    $formHtml = $form->generateHtml($formName);
                    $formHtml = str_replace("workflowDefinitionName", $workflowDefinition->name, $formHtml);
                    $statesString = '';
                    foreach ($workflowObject->current_place as $state => $value) {
                        $statesString .= $state;
                        $statesString .= ', ';
                    }
                    $statesString = \Illuminate\Support\Str::beforeLast($statesString, ', ');
                    $formHtml = str_replace("place_name", $statesString, $formHtml);
                    $formHtml = str_replace("transition_name", $enabledTransition, $formHtml);

                    $formData['transition'] =  $enabledTransition;
                    $formData['html'] =  $formHtml;
                    $forms[] = $formData;
                }
            }
        }

        $title = $workflowDefinition->definition['name'];
        $activities = Workflow::obterAtividades($workflowObject->id);
        $form = new Form(['key' => $workflowObject->id]);
        $formSubmissions = $form->listSubmission();
        if (!Gate::allows('admin')) {
            $formSubmissions = $formSubmissions->filter(function ($submission) use ($workflowObject, $workflowDefinition) {
                $transition = $submission['data']['transition'];
                $to = $workflowDefinition->definition['transitions'][$transition]['tos'];
                $initial = $workflowDefinition->definition['initial_places'];

                $workflowInstance = Workflow::criarSymfonyWorkflow($workflowDefinition);
                $fakeWorkflowObject = new \stdClass();
                
                /**
                 * Caso o destino da transition não esteja no formato [chave => valor],
                 * a formata desta maneira.
                 * 
                 * Ainda, caso o destino da transition seja um vetor mas, da forma [nomePlace => nomePlace],
                 * atribui ao vetor '$toWithWeights' os 'nomePlace' e o valor 1, de tal forma que:
                 * 'toWithWeights' == [nomePlace1 => 1, nomePlace2 => 1, ...].
                 */
                if (!is_array($to)) {
                    $to = [$to => 1];
                } else {
                    if (array_values($to) === $to) {
                        $toWithWeights = [];
                        foreach ($to as $place) {
                            $toWithWeights[$place] = 1;
                        }
                        $to = $toWithWeights;
                    }
                }

                $fakeWorkflowObject->currentState = $to;
                $enabledTransitions =  Workflow::obterTransitionsHabilitadas($workflowInstance,$workflowObject);
                if (empty($enabledTransitions)) {
                    return true;
                }

                // Verifica se a submissão tem um ou mais places a que se refere
                $submission_place_arr = array_map('trim',explode(',',$submission['data']['place']));
                if(count($submission_place_arr) >= 2)
                {
                    // Caso ao menos um dos places seja igual ao atual, permite ao usuário ver a submissão de formulário
                    $curr_places = array_keys($workflowObject->current_places);
                    $intersection = array_intersect($submission_place_arr,$curr_places);
                }

                return $submission['data']['place'] == $workflowObject->current_places || $workflowObject->current_places == $to || $submission['data']['place'] == $initial || !empty($intersection);
            });
        }        

        $workflowObjectData['workflowObject'] = $workflowObject;
        $workflowObjectData['workflowDefinition'] = $workflowDefinition;
        $workflowObjectData['workflowsTransitions'] = $workflowsTransitions;
        $workflowObjectData['forms'] = $forms;
        $workflowObjectData['name'] = $title;
        $workflowObjectData['activities'] = $activities;
        $workflowObjectData['formSubmissions'] = collect($formSubmissions);
        // dd($workflowObjectData);
        return $workflowObjectData;
    }

    /**
     * Aplica uma transição no objeto
     *
     * ### Etapas
     * valida a transição
     * valida permissões
     * 3. valida form e executa bindings: retorna para UI caso validação falhe
     * registra workflow_history
     * executa a transição
     * notifica quem precisar
     */
    public function apply(string $transitionName, array $inputData, ?User $user = null): bool
    {
        /** @var WorkflowDefinition */
        $workflowDefinition = WorkflowDefinition::find($this->workflow_definition_id);
        $transition = $workflowDefinition->transition($transitionName);
        if (!$transition) {
            throw new TransitionNotAllowedException("A transição '{$transitionName}' não existe neste workflow.");
        }

        if (!$this->can($transitionName, $user)) {
            throw new TransitionNotAllowedException("Você não tem permissão para executar a ação '{$transitionName}' no estado atual.");
        }

        DB::transaction(function () use ($transitionName, $transition, $user, $inputData) {
            // 3. valida form
            if ($transition->form) {
                //todo: precisa validar
                // handleSubmission deve lançar exception se validação falhar
                $form = $transition->form()->handleSubmission($inputData);
                if(is_array($form) && $form['status'] === 'error') {
                    throw ValidationException::withMessages(['Submissão de formulário da transition é inválida.']);
                }
            }

            if ($transition->bindings->isNotEmpty()) {
                // todo: precisa validar esta lógica
                foreach ($transition->bindings as $binding) {
                    // 1. Extrai o valor do input (ex: transforma 'form.user_codpes' em $inputData['user_codpes'])
                    $rawKey = str_replace('form.', '', $binding->from);
                    $rawValue = Arr::get($inputData, $rawKey);

                    // 2. Resolve o valor baseado na estratégia do 'resolver'
                    $resolvedValue = $this->resolveBindingValue($binding->resolver, $rawValue);

                    // 3. Alimenta o atributo do Model Local dinâmicamente
                    $this->variables->{$binding->attribute} = $resolvedValue;
                }
            }

            $this->current_place = $transition->tos;

            $this->save();
            $this->history()->create([
                'transition_name' => $transitionName,
                'from_places' => implode(',', $transition->from),
                'to_places' => implode(',', $transition->to),
                'user_id' => $user?->id,
                'form_submission_id' => $form?->id,
                'metadata' => [],
            ]);
        });

        // todo: notifica quem precisar
        // notifications está bugado
        // Passamos o grafo ($definitionData) para que o DTO consiga calcular as roles padrão dos 'tos'
        // $destinatarios = $transition->resolveNotificationDestinations($definitionData);

        // Agora que temos o array $destinatarios calculado, disparamos a ação de envio.
        // A melhor prática no Laravel é disparar um Evento para que o envio do e-mail
        // aconteça em background (fila/Queue), sem travar a tela do usuário.
        // event(new WorkflowTransitionExecuted($this, $transition, $destinatarios));
        return true;
    }

    /**
     * Retorna a lista de transições associadas ao place atual.
     *
     * @return array<TransitionDefinition> Lista de DTOs das transições disponíveis.
     */
    public function transitions(): ?array
    {   
        /** @var WorkflowDefinition */
        $workflowDefinition = WorkflowDefinition::find($this->workflow_definition_id);
        if (isset($workflowDefinition)) 
        {
            $curr_place_trans = [];
            foreach($this->current_places as $place) 
            {
                $curr_place_trans[$place] = $workflowDefinition->transitionsFromPlace($place);
            }
            return $curr_place_trans;
        }
        return null;
    }

    /**
     * Retorna o estado completo do workflow formatado para o consumo da UI.
     *
     * Este método centraliza todas as informações necessárias para renderizar a interface,
     * incluindo o estado atual (place), permissões (actors), transições permitidas (actions),
     * além de dados para construção dinâmica de formulários e descrições complementares.
     *
     * @return array{
     *     actors: array<int, int|string>, xxxxxxxx
     *     transitions: array<string>,
     * } Dados estruturados para o frontend.
     */
    public function workflowState(): array
    {
        $data = [
            'current_places' => $this->current_places,
            'actors' => [],
            'transitions' => [],
        ];

        // TODO - Recuperar Actors corretamente
        $actors_arr = [];
        $workflow_def = WorkflowDefinition::find($this->workflow_definition_id);
        foreach($this->current_places as $place) 
        {
            $place_def = $workflow_def->place($place);
            $actors_arr[] = $place_def->roles;
        }

        $transition_arr = [];
        foreach($this->transitions() as $transition)
        {
            $transition_arr[] = $transition->toArray();
        }

        $data['actors'] = $actors_arr;
        $data['transitions'] = $transition_arr;
        return $data;
    }

    /**
     * Verifica se uma transição específica pode ser executada.
     *
     * @param  string  $transition  O nome da transição a ser verificada.
     * @param  \App\Models\User|null  $user  O usuário executando a ação (opcional).
     * @return bool  True se a transição for permitida, false caso contrário.
     */
    public function can(string $transition, ?User $user = null): bool
    {
        $can = true;
        /** @var WorkflowDefinition */
        $workflowDefinition = WorkflowDefinition::find($this->workflow_definition_id);
        $places = $workflowDefinition->definition['places'] ?? [];
        $transitionData = $workflowDefinition->transition($transition);
        if(isset($user))
        {
            foreach($transitionData->from as $fromPlace) 
            {
                if(!empty($places[$fromPlace]['roles']))
                {
                    if(!$user->hasRole($places[$fromPlace]['roles']))
                    {
                        $can = false; break;
                    }
                }
            }
        }
        else
        {
            foreach($transitionData->from as $fromPlace) 
            {
                if(!empty($places[$fromPlace]['roles']))
                {$can = false; break;}
            }
        }

        return $can;
    }

    /**
     * Retorna a instância do Model vinculada a este objeto de workflow.
     *
     * @return Model  A instância do modelo do Laravel.
     */
    public function model(): ?Model
    {
        return $this->object_type::find($this->object_id);
    }


    /**
     * Sistema de mapeamento de Resolvers (Pode ser expandido com Services do Laravel)
     */
    protected function resolveBindingValue(string $resolver, mixed $value): mixed
    {
        return match ($resolver) {
            // Exemplo de resolver do ecossistema USP: Busca o ID do usuário local pelo Número USP
            'user_by_codpes' => \App\Models\User::where('codpes', $value)->first()?->id,
            // Se não exigir nenhum resolver complexo, apenas retorna o dado puro
            'direct', 'raw' => $value,

            default => $value,
        };
    }

    /**
     * Relacionamento Polimórfico.
     * * Permite obter o objeto real do sistema da USP que está acoplado a este workflow.
     * Ex: $workflowObject->object -> Retorna a instância de Chamado ou Pedido.
     */
    public function object(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relacionamento com a Definição.
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    // ******************************

    /**
     *  Relaciona o objeto de workflow à uma definição de workflow
     *
     *  @return BelongsTo <WorkflowDefinition, WorkflowObject>
     */
    public function workflowDefinition(): belongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class);
    }

    /**
     * Relacionamento com histórico
     */
    public function history(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class);
    }

    /**
     *  Obtém o 'state' atual do workflow
     *
     *  - Caso 'state' seja nulo, retorna um array vazio;
     *  @return array
     */
    public function getCurrentState()
    {
        return $this->state ?? [];
    }

    /**
     *  Atualiza o campo 'state' do workflow
     *
     *  @param array $state
     *  @return void
     */
    public function setCurrentState($state)
    {
        $this->state = $state;
    }

    /**
     *  Relaciona o objeto de workflow à um usuário
     *
     * @return
     */
    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

    /**
     *  Passa para o próximo state do workflow
     *
     *  - Atualiza o 'state' do workflow;
     *  - Registra o usuário, o state e gera um log simples ao atualizar.
     *
     * @param string $newState
     * @return void
     */
    // public function updateState(string $newState)
    // {
    //     $this->state = $newState;
    //     $this->save();

    //     activity()
    //         ->performedOn($this)
    //         ->causedBy(auth()->user())
    //         ->withProperties(['state' => $newState])
    //         ->log("Updated to {$newState}");
    // }

    public static function createObject(WorkflowDefinition $workflowDefinition, Model $model): WorkflowObject
    {
        $workflowObject = new WorkflowObject();
        $workflowObject->workflow_definition_id = $workflowDefinition->id;
        $workflowObject->object_type = get_class($model);
        $workflowObject->object_id = $model->id;
        $workflowObject->current_places = $workflowDefinition->definition['initial_places'] ?? [];

        $variables_arr = [];

        foreach($workflowDefinition->definition['roles'] as $role)
        {
            if(str_starts_with($role['name'],'@'))
            {
                $role_name = str_replace('@','',$role['name']);
                $variables_arr[$role_name] = '';
            }
        }

        $workflowObject->variables = $variables_arr;
        $workflowObject->save();

        return $workflowObject;
    }
}
