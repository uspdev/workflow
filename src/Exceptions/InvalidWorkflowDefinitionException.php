<?php

namespace Uspdev\Workflow\Exceptions;

use InvalidArgumentException;

class InvalidWorkflowDefinitionException extends InvalidArgumentException
{
    /**
     * @param array<int, string> $errors
     */
    public function __construct(
        private readonly array $errors,
        private readonly mixed $partial = null,
    ) {
        parent::__construct(implode(PHP_EOL, $errors));
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function partial(): mixed
    {
        return $this->partial;
    }
}
