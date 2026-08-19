<?php

namespace Uspdev\Workflow\Exceptions;

use Exception;
use Throwable;

class WorkflowDefinitionNotFoundException extends Exception
{
    /**
     * Construtor da exceção customizada.
     * * @param string $message Mensagem de erro informativa.
     * @param int $code Código do erro (opcional, padrão 404 por se tratar de um recurso não encontrado).
     * @param Throwable|null $previous Exceção anterior encadeada (opcional).
     */
    public function __construct(string $message = "Definição de workflow não encontrada.", int $code = 404, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
