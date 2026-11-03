<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Operação de carrinho recusada. A mensagem é exibida ao cliente.
 */
class CartException extends RuntimeException {}
