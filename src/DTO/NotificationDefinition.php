<?php

namespace Uspdev\Workflow\DTO;

class NotificationDefinition extends AbstractWfDto
{
    /**
     * @param array<int, string> $appendRoles Roles acrescentadas às roles dos places de destino
     */
    public function __construct(
        public array $appendRoles = [],
    ) {}

    /**
     * Cria o DTO de notificação garantindo que a estrutura do JSON seja respeitada.
     */
    public static function fromArray(array $data): static
    {
        self::validate($data);

        return new static(
            appendRoles: $data['append_roles'] ?? [],
        );
    }

    public static function validate(array $data): void
    {
        $errors = [];
        self::stringList($data, 'append_roles', $errors, allowEmpty: true, required: false);

        foreach (['override_roles', 'users', 'emails'] as $unsupportedField) {
            if (array_key_exists($unsupportedField, $data)) {
                $errors[] = "'{$unsupportedField}' não é suportado em notifications no Workflow V2.";
            }
        }

        self::throwIfInvalid($errors);
    }

    /**
     * Converte o DTO de volta para array.
     */
    public function toArray(): array
    {
        return [
            'append_roles' => $this->appendRoles,
        ];
    }
}
