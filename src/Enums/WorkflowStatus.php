<?php

namespace Uspdev\Workflow\Enums;

enum WorkflowStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    /**
     * Retorna todos os valores possíveis para a migration.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Retorna o label amigável para exibição na interface do usuário.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::PUBLISHED => 'Publicado',
            self::ARCHIVED => 'Arquivado',
        };
    }

    /**
     * Retorna um array mapeado [value => label] para usar em selects de formulários.
     * * @return array<string, string>
     */
    public static function asSelectArray(): array
    {
        $options = [];
        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }
        return $options;
    }
}
