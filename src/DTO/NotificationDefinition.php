<?php

namespace Uspdev\Workflow\DTO;

class NotificationDefinition extends AbstractWfDto
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
    public static function fromArray(array $data): static
    {
        self::validate($data);

        return new static(
            overrideRoles: $data['override_roles'] ?? [],
            appendRoles: $data['append_roles'] ?? [],
            users: $data['users'] ?? [],
            emails: $data['emails'] ?? [],
        );
    }

    public static function validate(array $data): void
    {
        $errors = [];
        self::stringList($data, 'override_roles', $errors, allowEmpty: true, required: false);
        self::stringList($data, 'append_roles', $errors, allowEmpty: true, required: false);
        self::stringList($data, 'users', $errors, allowEmpty: true, required: false);
        self::stringList($data, 'emails', $errors, allowEmpty: true, required: false);
        self::throwIfInvalid($errors);
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
