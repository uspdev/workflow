<?php

namespace Uspdev\Workflow\DTO;

use Uspdev\Workflow\Enums\WorkflowStatus;
use Uspdev\Workflow\Exceptions\InvalidWorkflowDefinitionException;

class WorkflowDefinitionImport extends AbstractWfDto
{
    public function __construct(
        public WorkflowDefinitionData $definition,
        public int $version,
        public WorkflowStatus $status,
    ) {}

    public static function fromArray(array $data): static
    {
        $errors = [];
        $version = $data['version'] ?? 1;
        $status = $data['status'] ?? WorkflowStatus::PUBLISHED->value;

        if (!is_int($version) || $version < 1) {
            $errors[] = "'version' deve ser um inteiro maior que zero.";
        }

        if (!is_string($status) || !in_array($status, [
            WorkflowStatus::PUBLISHED->value,
            WorkflowStatus::ARCHIVED->value,
        ], true)) {
            $errors[] = $status === WorkflowStatus::DRAFT->value
                ? "status 'draft' não é permitido para uma definição sincronizada."
                : "'status' deve ser 'published' ou 'archived'.";
        }

        $definition = null;
        try {
            $definition = WorkflowDefinitionData::fromArray($data);
        } catch (InvalidWorkflowDefinitionException $exception) {
            $errors = array_merge($errors, $exception->errors());
            if ($exception->partial() instanceof WorkflowDefinitionData) {
                $definition = $exception->partial();
            }
        }

        $parsedStatus = is_string($status) ? WorkflowStatus::tryFrom($status) : null;
        $partial = $definition !== null && is_int($version) && $version > 0 && $parsedStatus !== null
            ? new static($definition, $version, $parsedStatus)
            : null;

        self::throwIfInvalid($errors, $partial ?? $definition);

        return $partial;
    }

    public static function validate(array $data): void
    {
        self::fromArray($data);
    }

    public function toArray(): array
    {
        return array_merge($this->definition->toArray(), [
            'version' => $this->version,
            'status' => $this->status->value,
        ]);
    }

    /**
     * @return array{name: string, description: ?string, definition: array<string, mixed>, version: int, status: string}
     */
    public function persistenceAttributes(): array
    {
        return [
            'name' => $this->definition->name,
            'description' => $this->definition->description,
            'definition' => $this->definition->toArray(),
            'version' => $this->version,
            'status' => $this->status->value,
        ];
    }
}
