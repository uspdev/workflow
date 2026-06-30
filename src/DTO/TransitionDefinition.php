<?php

namespace Uspdev\Workflow\DTO;

use Illuminate\Support\Collection;
use Uspdev\Forms\Form;
use Uspdev\Workflow\Data\AbstractWfDto;

class TransitionDefinition extends AbstractWfDto
{
    /**
     * @param string $name Nome único da ação (ex: 'aprovar')
     * @param string $label Texto do botão na UI (ex: 'Aprovar Pedido')
     * @param array<string> $from Locais de origem que permitem esta ação (ex: ['analise'])
     * @param array<string> $tos Locais de destino após a ação (ex: ['aprovado'])
     * @param string $form Nome do formulário associado a esta transição na UI (opcional)
     * @param Collection $bindings
     * @param Collection $notifications
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $from,
        public array $tos,
        public ?string $form = null,
        public Collection $bindings = new Collection(),
        public Collection $notifications = new Collection()
    ) {}

    /**
     * Cria uma instância do DTO a partir de um array bruto (banco ou request).
     */
    public static function fromArray(array $data): static
    {
        self::validate($data);

        return new self(
            name: $data['name'],
            label: $data['label'] ?? $data['name'],
            from: $data['from'],
            tos: $data['tos'],
            form: $data['form'] ?? null,
            bindings: collect($data['bindings'])
                ->map(fn($b) => BindingDefinition::fromArray($b)),
            notifications: collect($data['notifications'])
                ->map(fn($n) => NotificationDefinition::fromArray($n)),
        );
    }

    /**
     * Valida a definição de workflow informada em $data
     */
    public static function validate(array $data): void
    {
        self::requireString($data, 'name');
        self::optionalString($data, 'label');
        self::requireString($data, 'from');
        self::requireArray($data, 'tos');
        self::optionalString($data, 'form');
        self::optionalArray($data, 'bindings');
        self::optionalArray($data, 'notifications');

        // TODO (WorkflowDefinitionData):
        // - validar que from referencia places existentes
        // - validar que tos referencia places existentes
    }

    /**
     * Converte o DTO de volta para array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'from' => $this->from,
            'tos' => $this->tos,
            'form' => $this->form,
            'bindings' => $this->bindings,
            'notifications' => $this->notifications,
        ];
    }

    // Dentro de src/DTO/TransitionDefinition.php

    /**
     * Resolve os destinatários finais desta transição com base no grafo do workflow.
     * * @param WorkflowDefinitionData $graph O grafo completo para buscar as roles dos 'tos'
     */
    public function resolveNotificationDestinations(WorkflowDefinitionData $graph): array
    {
        // 1. Busca as roles padrão dos locais de destino ('tos')
        $defaultRoles = [];
        foreach ($this->tos as $toPlaceName) {
            $place = $graph->place($toPlaceName);
            if ($place) {
                $defaultRoles = array_merge($defaultRoles, $place->roles);
            }
        }

        // 2. Se não houver configuração de notificação personalizada, retorna o padrão
        if ($this->notifications->isEmpty()) {
            return [
                'roles'  => array_unique($defaultRoles),
                'users'  => [],
                'emails' => [],
            ];
        }

        // 3. Inicializa os acumuladores
        $finalRoles = [];
        $finalUsers = [];
        $finalEmails = [];

        // Flag para controlar se alguma das notificações na lista aplicou um override (sobrescrita)
        $hasOverride = false;

        // 4. Itera sobre cada DTO de notificação para consolidar os dados
        foreach ($this->notifications as $notification) {
            // Se houver overrideRoles, ele substitui o padrão
            if (!empty($notification->overrideRoles)) {
                $hasOverride = true;
                $finalRoles = array_merge($finalRoles, $notification->overrideRoles);
            } else {
                $finalRoles = array_merge($finalRoles, $notification->appendRoles ?? []);
            }

            $finalUsers = array_merge($finalUsers, $notification->users ?? []);
            $finalEmails = array_merge($finalEmails, $notification->emails ?? []);
        }

        // Se NENHUMA notificação deu override, nós injetamos os defaultRoles acumulados do Place
        if (!$hasOverride) {
            $finalRoles = array_merge($defaultRoles, $finalRoles);
        }

        return [
            'roles'  => array_values(array_unique($finalRoles)),
            'users'  => array_values(array_unique($finalUsers)),
            'emails' => array_values(array_unique($finalEmails)),
        ];
    }

    /**
     * Retorna instância do form associado à transição
     */
    public function form(): ?Form
    {
        if ($this->form === null) {
            return null;
        }

        return new Form(['name' => $this->form]);
    }
}
