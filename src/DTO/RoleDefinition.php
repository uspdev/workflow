<?php

namespace Uspdev\Workflow\DTO;

readonly class RoleDefinition
{
    /**
     * @param string $name Identificador único da role no sistema (ex: 'chefia_departamento', 'secretaria_pos')
     * @param string $label Nome amigável para exibição na UI (ex: 'Chefia do Departamento')
     * @param array<string> $source (opcional)
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $source = []
    ) {}

    /**
     * Cria o DTO a partir de um array bruto.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            label: $data['label'] ?? '',
            source: $data['source'] ?? []
        );
    }

    /**
     * Converte o DTO de volta para array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'source' => $this->source,
        ];
    }
}
