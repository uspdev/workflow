<?php

namespace Uspdev\Workflow\DTO;

readonly class NotificationDefinition
{
    /**
     * @param array<string> $overrideRoles Substitui completamente as roles default de destino
     * @param array<string> $appendRoles Adiciona mais roles à lista de notificação
     * @param array<string> $users Adiciona usuários específicos (ex: codpes ou usernames)
     * @param array<string> $emails Adiciona e-mails diretos de fora do sistema
     */
    public function __construct(
        public array $overrideRoles = [],
        public array $appendRoles = [],
        public array $users = [],
        public array $emails = []
    ) {}

    /**
     * Cria o DTO de notificação garantindo que a estrutura do JSON seja respeitada.
     */
    public static function fromArray(array $data): self
    {
        // Validação de Conflito de Regras: Não faz sentido ter override e append ao mesmo tempo!
        if (!empty($data['override_roles']) && !empty($data['append_roles'])) {
            throw new \InvalidArgumentException(
                "Erro na configuração de notificação: Você não pode definir 'override_roles' e 'append_roles' simultaneamente na mesma transição."
            );
        }

        return new self(
            overrideRoles: (array) ($data['override_roles'] ?? []),
            appendRoles: (array) ($data['append_roles'] ?? []),
            users: (array) ($data['users'] ?? []),
            emails: (array) ($data['emails'] ?? [])
        );
    }

    /**
     * Converte o DTO de volta para array.
     */
    public function toArray(): array
    {
        return [
            'override_roles' => $this->overrideRoles,
            'append_roles' => $this->appendRoles,
            'users' => $this->users,
            'emails' => $this->emails,
        ];
    }
}
