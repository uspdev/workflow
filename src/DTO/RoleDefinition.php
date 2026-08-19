<?php

namespace Uspdev\Workflow\DTO;

class RoleDefinition extends AbstractWfDto
{
    /**
     * @param string $name Identificador único da role no sistema (ex: 'chefia_departamento', 'secretaria_pos')
     * @param string $label Nome amigável para exibição na UI (ex: 'Chefia do Departamento')
     * @param string|array<string, mixed>|null $source
     */
    public function __construct(
        public string $name,
        public string $label,
        public string|array|null $source = null,
    ) {}

    /**
     * Cria o DTO a partir de um array bruto.
     */
    public static function fromArray(array $data): static
    {
        self::validate($data);

        return new static(
            name: $data['name'],
            label: $data['label'] ?? $data['name'],
            source: $data['source'] ?? null,
        );
    }

    public static function validate(array $data): void
    {
        $errors = [];
        self::requireString($data, 'name', $errors);
        self::optionalString($data, 'label', $errors);

        if (array_key_exists('source', $data)
            && $data['source'] !== null
            && !is_string($data['source'])
            && !is_array($data['source'])) {
            $errors[] = "'source' deve ser uma string, um objeto ou null.";
        }

        self::throwIfInvalid($errors);
    }

    /**
     * Converte o DTO de volta para array.
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'label' => $this->label,
        ];

        if ($this->source !== null) {
            $data['source'] = $this->source;
        }

        return $data;
    }
}
