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

class WorkflowObject extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_definition_id', 'object_type', 'object_id', 'current_places', 'variables'];

    protected $casts = [
        'current_places' => 'array',
        'variables' => 'array',
    ];

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
        $transition = $this->workflowDefinition->transition($transitionName);
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
                'transition' => $transitionName,
                'from_place' => implode(',', $transition->from),
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
     * @return array<\App\DTOs\WorkflowTransitionDTO> Lista de DTOs das transições disponíveis.
     */
    public function transitions(): array
    {
        // TODO: Implementar lógica que lê o "place" atual e busca as transições
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
        // TODO: Implementar montagem da estrutura para a interface gráfica
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
        // TODO: Implementar lógica de verificação da transição
    }

    /**
     * Retorna a instância do Model vinculada a este objeto de workflow.
     *
     * @return \Illuminate\Database\Eloquent\Model  A instância do modelo do Laravel.
     */
    public function model(): Model
    {
        // TODO: Implementar retorno do modelo
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
    // public function getCurrentState()
    // {
    //     return $this->state ?? [];
    // }

    /**
     *  Atualiza o campo 'state' do workflow
     *
     *  @param array $state
     *  @return void
     */
    // public function setCurrentState($state)
    // {
    //     $this->state = $state;
    // }

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
