<?php

namespace Uspdev\Workflow\Exceptions;

use RuntimeException;

class WorkflowSyncValidationException extends RuntimeException
{
    /**
     * @param array<int, string> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(implode(PHP_EOL, $errors));
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
