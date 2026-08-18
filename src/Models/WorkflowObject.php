<?php

namespace Uspdev\Workflow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Uspdev\Forms\Form;
use Uspdev\Forms\Facades\Forms;
use Uspdev\Workflow\DTO\PlaceDefinition;
use Uspdev\Workflow\Exceptions\TransitionNotAllowedException;
use Uspdev\Workflow\Models\WorkflowDefinition;
use Uspdev\Workflow\DTO\TransitionDefinition;
use Uspdev\Workflow\Workflow;
use Gate;
use Spatie\Activitylog\Models\Activity;

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

    /**
     * Verifica se a transition referenciada está bloqueada ou não.
     * 
     * @param TransitionDefinition $transition
     * @return bool
     */
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
     * Constrói os formulários para as transições disponíveis no estado atual do objeto de 
     * workflow, retornando um array contendo o html do formulário e a transição associada.
     * 
     * @return array<string|TransitionDefinition>
     */
    private function buildEnabledForms()
    {
        $enabledForms = [];
        foreach ($this->enabledTransitions() as $transition) 
        {
            $form = $transition->form();
            if($form)
            {
                $form->key = $this->id;
                $formHtml = $form->generateHtml();
                $formHtml = str_replace("workflowDefinitionName", $this->definition->name, $formHtml);
                $statesString = implode(', ', array_keys($this->current_places));
                $formHtml = str_replace("place_name", $statesString, $formHtml);
                $formHtml = str_replace("transition_name", $transition->name, $formHtml);

                $formData['transition'] =  $transition;
                $formData['html'] =  $formHtml;

                $enabledForms[] = $formData;
                
            }
        }

        return $enabledForms;
    }

    /**
     *  Retorna os registros de atividade para um objeto
     *  Com o id correspondente ao passado de parâmetro na chamada do método
     * 
     *  - Encontra a atividade relacionada ao workflow object com o id citado acima;
     *  - Encontra a workflow definition relacionada ao objeto;
     *  - Captura as propriedades da atividade;
     *  - Verifica se o 'state' da atividade está ativo no 'place' atual do workflow object
     *  - Encontra (Se possível) o causador da atividade
     * 
     *  - Formata toda a resposta e retona um array com os dados da atividade;
     * 
     *  @param int $id
     *  @return Array
     */
    private static function obterAtividades(int $id)
    {
        
        $atividades = Activity::where('subject_type', WorkflowObject::class)
            ->where('subject_id', $id)
            ->get();

        $resultadoFormatado = $atividades->map(function ($atividade) {
            $workflowObject = WorkflowObject::findOrFail($atividade->subject_id);
            $workflowDefinition = WorkflowDefinition::where('id',$workflowObject->workflow_definition_id)->first();
            $stateData = json_decode($atividade->properties, true);
            $nomeBonito = $workflowDefinition->definition['places'][$stateData['state']] ?? $stateData['state'];
            $user = $atividade->causer_id ? User::find($atividade->causer_id) : null;

            return [
                'id' => $atividade->id,
                'description' => "Alterado para: " . ($nomeBonito['description'] ?? 'Descrição não disponível'),
                'objectId' => $atividade->subject_id,
                'user' => $user ? $user->name : 'Não definido',
                'created_at' => \Carbon\Carbon::parse($atividade->created_at)->format('d/m/Y H:i'),
                'updated_at' => \Carbon\Carbon::parse($atividade->updated_at)->format('d/m/Y H:i'),
            ];
        });

        return $resultadoFormatado;
    }

    /**
     * Retorna as submissões de formulário que o usuário têm permissão para ver, a depender do 
     * estado do objeto de workflow
     * 
     * @param Form $form
     * @return Collection<int, TModel>|\Illuminate\Support\Collection<int, \stdClass>
     */
    private function viewableSubmissions(Form $form)
    {
        $formSubmissions = $form->listSubmission();
        if (!Gate::allows('admin')) 
        {
            $formSubmissions = $formSubmissions->filter(function ($submission){
                $workflowDefinition = $this->definition;
                $transition = $submission['data']['transition'];
                $to = $workflowDefinition->definition['transitions'][$transition]['tos'];
                $initial = $workflowDefinition->definition['initial_places'];
                
                /**
                 * Caso o destino da transition não esteja no formato [chave => valor],
                 * a formata desta maneira.
                 * 
                 * Ainda, caso o destino da transition seja um vetor mas, da forma [nomePlace => nomePlace],
                 * atribui ao vetor '$toWithWeights' os 'nomePlace' e o valor 1, de tal forma que:
                 * 'toWithWeights' == [nomePlace1 => 1, nomePlace2 => 1, ...].
                 */
                if (!is_array($to)) 
                {
                    $to = [$to => 1];
                } 
                else 
                {
                    if (array_values($to) === $to) 
                    {
                        $toWithWeights = [];
                        foreach ($to as $place) 
                        {
                            $toWithWeights[$place] = 1;
                        }
                        $to = $toWithWeights;
                    }
                }

                $enabledTransitions =  $this->enabledTransitions();
                if (empty($enabledTransitions)) 
                {
                    return true;
                }

                // Verifica se a submissão tem um ou mais places a que se refere
                $submission_place_arr = array_map('trim',explode(',',$submission['data']['place']));
                if(count($submission_place_arr) >= 2)
                {
                    // Caso ao menos um dos places seja igual ao atual, permite ao usuário ver a submissão de formulário
                    $curr_places = array_keys($this->current_places);
                    $intersection = array_intersect($submission_place_arr,$curr_places);
                }

                return $submission['data']['place'] == $this->current_places || $this->current_places == $to || $submission['data']['place'] == $initial || !empty($intersection);
            });
        }

        return $formSubmissions;
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
    public static function getObjectData(int $object_id)
    {
        /** @var WorkflowObject */
        $workflowObject = WorkflowObject::findOrFail($object_id);
        /** @var WorkflowDefinition */
        $workflowDefinition = $workflowObject->definition;

        $workflowsTransitions['enabled'] = $workflowObject->enabledTransitions();
        $workflowsTransitions['all'] =  $workflowDefinition->transitions();
        $workflowsTransitions['currentState'] =  $workflowObject->current_places;
        $workflowsTransitions['allowed'] = [];
        
        $forms = $workflowObject->buildEnabledForms();

        $title = $workflowDefinition->definition['label'] ?? $workflowDefinition->name;
        $activities = SELF::obterAtividades($workflowObject->id);
        
        $formSubmissions = $workflowObject->viewableSubmissions(new Form(['key' => $workflowObject->id]));

        $workflowObjectData['workflowObject'] = $workflowObject;
        $workflowObjectData['workflowDefinition'] = $workflowDefinition;
        $workflowObjectData['workflowsTransitions'] = $workflowsTransitions;
        $workflowObjectData['forms'] = $forms;
        $workflowObjectData['name'] = $title;
        $workflowObjectData['activities'] = $activities;
        $workflowObjectData['formSubmissions'] = collect($formSubmissions);
        
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
    public function apply(string $transitionName, array $inputData = [], ?User $user = null): bool
    {
        /** @var WorkflowDefinition */
        $workflowDefinition = $this->definition()->firstOrFail();
        $transition = $workflowDefinition->getDefinitionData()->transition($transitionName);
        if (!$transition) {
            throw new TransitionNotAllowedException("A transição '{$transitionName}' não existe neste workflow.");
        }

        DB::transaction(function () use ($transitionName, $transition, $user, $inputData): void {
            /** @var WorkflowObject $object */
            $object = self::query()->lockForUpdate()->findOrFail($this->getKey());
            if (!$object->can($transitionName, $user)) {
                throw new TransitionNotAllowedException("Você não tem permissão para executar a ação '{$transitionName}' no estado atual.");
            }

            $formSubmission = null;

            // 3. valida form
            if (is_string($transition->form)) {
                $formDefinition = Forms::definition($transition->form);
                if ($formDefinition === null) {
                    throw new \InvalidArgumentException(
                        "Form definition '{$transition->form}' nao encontrada."
                    );
                }

                $formRequest = new Request(array_merge($inputData, [
                    'form_definition_id' => $formDefinition->getKey(),
                    'form_key' => (string) $object->getKey(),
                ]));
                $formRequest->setUserResolver(fn (): ?User => $user);

                $formSubmission = Forms::submit($formRequest);
            }

            if ($transition->bindings->isNotEmpty()) {
                $variables = $object->variables ?? [];

                // todo: precisa validar esta lógica
                foreach ($transition->bindings as $binding) {
                    // 1. Extrai o valor do input (ex: transforma 'form.user_codpes' em $inputData['user_codpes'])
                    $rawKey = str_replace('form.', '', $binding->from);
                    $rawValue = Arr::get($inputData, $rawKey);

                    // 2. Resolve o valor baseado na estratégia do 'resolver'
                    $resolvedValue = $object->resolveBindingValue($binding->resolver, $rawValue);

                    // 3. Alimenta o atributo do Model Local dinâmicamente
                    $variables[$binding->attribute] = $resolvedValue;
                }

                $object->variables = $variables;
            }

            $fromPlaces = $object->current_places;
            $object->current_places = $transition->tos;

            $object->save();
            $object->history()->create([
                'transition_name' => $transitionName,
                'from_places' => $fromPlaces,
                'to_places' => $transition->tos,
                'user_id' => $user?->getAuthIdentifier(),
                'form_submission_id' => $formSubmission instanceof Model
                    ? $formSubmission->getKey()
                    : null,
                'metadata' => [],
            ]);
        });

        $this->refresh();

        // TODO -  notifica quem precisar
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
    public function transitions(): array
    {
        return $this->enabledTransitions()->all();
    }

    /**
     * Retorna o estado completo do workflow formatado para o consumo da UI.
     *
     * Este método centraliza todas as informações necessárias para renderizar a interface,
     * incluindo o estado atual (place), permissões (actors), transições permitidas (actions),
     * além de dados para construção dinâmica de formulários e descrições complementares.
     *
     * @return array{
     *     current_places: array<int, string>,
     *     actors: array<int, string>,
     *     transitions: array<int, array<string, mixed>>
     * } Dados estruturados para o frontend.
     */
    public function workflowState(): array
    {
        $data = [
            'current_places' => $this->current_places,
            'actors' => [],
            'transitions' => [],
        ];

        $data['actors'] = $this->currentPlaces()
            ->flatMap(fn (PlaceDefinition $place): array => $place->roles)
            ->values()
            ->all();
        $data['transitions'] = $this->enabledTransitions()
            ->map(fn (TransitionDefinition $transition): array => $transition->toArray())
            ->values()
            ->all();
        return $data;
    }

    /**
     * Verifica se uma transição específica pode ser executada.
     *
     * @param  string  $transition  O nome da transição a ser verificada.
     * @param  User|null  $user  O usuário executando a ação (opcional).
     * @return bool  True se a transição for permitida, false caso contrário.
     */
    public function can(string $transition, ?User $user = null): bool
    {
        /** @var WorkflowDefinition */
        $workflowDefinition = $this->definition()->first();
        if ($workflowDefinition === null) {
            return false;
        }

        $definitionData = $workflowDefinition->getDefinitionData();
        $transitionData = $definitionData->transition($transition);
        if ($transitionData === null || !in_array($transitionData->from, $this->current_places, true)) {
            return false;
        }

        $roles = $definitionData->place($transitionData->from)?->roles ?? [];
        if ($roles === []) {
            return true;
        }

        return $user !== null
            && method_exists($user, 'hasRole')
            && $user->hasRole($roles);
    }

    /**
     * Retorna a instância do Model vinculada a este objeto de workflow.
     *
     * @return Model  A instância do modelo do Laravel.
     */
    public function model(): Model
    {
        return $this->object;
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
     * Relacionamento com histórico
     */
    public function history(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class);
    }

    /**
     * Retorna uma coleção com o histórico deste objeto
     * @return Collection<int, WorkflowHistory>
     */
    public function getHistory(): Collection
    {
        return $this->history()->orderBy('id')->get();
    }

    /**
     *  Obtém o 'state' atual do workflow
     *
     *  - Caso 'state' seja nulo, retorna um array vazio;
     *  @return array
     */
    public function getCurrentState()
    {
        return $this->current_places ?? [];
    }

    /**
     *  Atualiza o campo 'state' do workflow
     *
     *  @param array $state
     *  @return void
     */
    public function setCurrentState($state)
    {
        $this->current_places = $state;
    }

    /**
     * Retorna o objeto atrelado ao Model referenciado.
     * @param Model $model
     * @return object|WorkflowObject|null
     */
    public static function from(Model $model): ?WorkflowObject
    {
        return SELF::whereMorphedTo('object', $model)->first();
    }

    /**
     * Retorna os places atuais do workflow, como PlaceDefinition DTOs.
     * @return Collection<int, PlaceDefinition>
     */
    public function currentPlaces(): Collection
    {
        /** @var WorkflowDefinition */
        $workflowDef = $this->definition()->firstOrFail();

        return $workflowDef->places()
            ->filter(fn (PlaceDefinition $place): bool => in_array($place->name, $this->current_places, true))
            ->values();
    }

    /**
     * Retorna todas as transitions habilitadas para o objeto de workflow, com base no seu estado atual.
     * @return Collection<int, TransitionDefinition>
     */
    public function enabledTransitions(): Collection
    {
        /** @var WorkflowDefinition */
        $workflowDef = $this->definition()->firstOrFail();

        return $workflowDef->transitions()
            ->filter(fn (TransitionDefinition $transition): bool => in_array($transition->from, $this->current_places, true))
            ->values();
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
}
