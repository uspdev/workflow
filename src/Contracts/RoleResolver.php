<?php

namespace Uspdev\Workflow\Contracts;

interface RoleResolver
{
    /**
     * Informa se a role declarada pelo Workflow existe no consumidor.
     */
    public function exists(string $role): bool;
}
