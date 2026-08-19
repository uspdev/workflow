<?php

namespace Uspdev\Workflow\Exceptions;

use Exception;
use Throwable;

class TransitionNotAllowedException extends Exception
{
    /**
     * Construtor da exceção de transição não permitida.
     *
     * @param string $message Mensagem detalhando o motivo do bloqueio.
     * @param int $code Código do erro (opcional, padrão 403 por ser uma ação proibida/negada).
     * @param Throwable|null $previous Exceção anterior encadeada (opcional).
     */
    public function __construct(
        string $message = "Esta transição não é permitida no estado atual ou para este usuário.",
        int $code = 403,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
