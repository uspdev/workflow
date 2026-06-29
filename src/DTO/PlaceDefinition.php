<?php

namespace Uspdev\Workflow\DTO;

readonly class PlaceDefinition
{
    /**
     * @param string $name Nome único identificador (ex: 'analise_chefia')
     * @param string $label Nome amigável para exibição na UI (ex: 'Análise da Chefia')
     * @param array<string> $roles Papéis/Grupos permitidos neste local (ex: ['Chefia', 'Secretaria'])
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $roles = []
    ) {}

    /**
     * Cria uma instância do DTO a partir de um array bruto (banco ou request).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            label: $data['label'] ?? '',
            roles: $data['roles'] ?? []
        );
    }

    /**
     * Converte o DTO de volta para array (útil para salvar no banco ou retornar em APIs).
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'roles' => $this->roles,
        ];
    }
}
