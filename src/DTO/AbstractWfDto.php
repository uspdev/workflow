<?php

namespace Uspdev\Workflow\DTO;

use Uspdev\Workflow\Exceptions\InvalidWorkflowDefinitionException;

abstract class AbstractWfDto
{

    abstract public static function fromArray(array $data): static;

    abstract public function toArray(): array;

    abstract public static function validate(array $data): void;

    /**
     * @param array<int, string> $errors
     */
    protected static function throwIfInvalid(array $errors, mixed $partial = null): void
    {
        if ($errors !== []) {
            throw new InvalidWorkflowDefinitionException($errors, $partial);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $errors
     */
    protected static function requireString(array $data, string $field, array &$errors): ?string
    {
        if (!array_key_exists($field, $data) || !is_string($data[$field]) || trim($data[$field]) === '') {
            $errors[] = "'{$field}' deve ser uma string não vazia.";
            return null;
        }

        return $data[$field];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $errors
     */
    protected static function optionalString(array $data, string $field, array &$errors): ?string
    {
        if (!array_key_exists($field, $data)) {
            return null;
        }

        if (!is_string($data[$field])) {
            $errors[] = "'{$field}' deve ser uma string.";
            return null;
        }

        return $data[$field];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $errors
     * @return array<int, string>
     */
    protected static function stringList(
        array $data,
        string $field,
        array &$errors,
        bool $allowEmpty = false,
        bool $required = true,
    ): array {
        if (!array_key_exists($field, $data)) {
            if ($required) {
                $errors[] = "'{$field}' deve ser uma lista de strings.";
            }
            return [];
        }

        if (!is_array($data[$field]) || !array_is_list($data[$field])) {
            $errors[] = "'{$field}' deve ser uma lista de strings.";
            return [];
        }

        if (!$allowEmpty && $data[$field] === []) {
            $errors[] = "'{$field}' deve possuir pelo menos um item.";
            return [];
        }

        $values = [];
        foreach ($data[$field] as $index => $value) {
            if (!is_string($value) || trim($value) === '') {
                $errors[] = "'{$field}.{$index}' deve ser uma string não vazia.";
                continue;
            }
            $values[] = $value;
        }

        return $values;
    }
}
