<?php

namespace Uspdev\Workflow\Data;

use InvalidArgumentException;

abstract class AbstractWfDto
{

    abstract public static function fromArray(array $data): static;

    abstract public function toArray(): array;

    abstract public static function validate(array $data): void;

    // === Métodos Privados Auxiliares de Validação ===

    private static function invalidType(string $field, string $type): never
    {
        throw new InvalidArgumentException("O campo '{$field}' deve ser {$type}.");
    }

    private static function ensureExists(array $data, string $field): void
    {
        if (!array_key_exists($field, $data)) {
            throw new InvalidArgumentException("O campo '{$field}' é obrigatório.");
        }
    }

    private static function ensureNotEmptyString(string $field, string $value): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("O campo '{$field}' deve ser uma string não vazia.");
        }
    }

    private static function ensureNotEmptyArray(string $field, array $value): void
    {
        if (count($value) === 0) {
            throw new InvalidArgumentException("O campo '{$field}' deve possuir pelo menos um elemento.");
        }
    }

    // === Métodos Protegidos de Validação ===

    protected static function requireArray(array $data, string $field, bool $allowEmpty = false): void
    {
        self::ensureExists($data, $field);

        if (!is_array($data[$field])) {
            self::invalidType($field, 'um array');
        }

        if (!$allowEmpty) {
            self::ensureNotEmptyArray($field, $data[$field]);
        }
    }

    protected static function requireString(array $data, string $field): void
    {
        self::ensureExists($data, $field);

        if (!is_string($data[$field])) {
            self::invalidType($field, 'uma string não vazia');
        }

        self::ensureNotEmptyString($field, $data[$field]);
    }

    protected static function optionalArray(array $data, string $field): void
    {
        if (!array_key_exists($field, $data)) {
            return;
        }

        if (!is_array($data[$field])) {
            self::invalidType($field, 'um array');
        }
    }

    protected static function optionalString(array $data, string $field): void
    {
        if (!array_key_exists($field, $data)) {
            return;
        }

        if (!is_string($data[$field])) {
            self::invalidType($field, 'uma string');
        }
    }
}
