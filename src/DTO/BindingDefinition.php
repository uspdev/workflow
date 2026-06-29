<?php

namespace Uspdev\Workflow\DTO;

readonly class BindingDefinition
{
    public function __construct(
        public string $attribute, // O campo que será salvo no seu Model (ex: 'analista_id')
        public string $from,      // De onde vem o dado (ex: 'form.user_codpes')
        public string $resolver   // O método ou classe que vai transformar o dado (ex: 'user_by_codpes')
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            attribute: $data['attribute'] ?? '',
            from: $data['from'] ?? '',
            resolver: $data['resolver'] ?? ''
        );
    }

    public function toArray(): array
    {
        return [
            'attribute' => $this->attribute,
            'from' => $this->from,
            'resolver' => $this->resolver,
        ];
    }
}
