<?php

namespace App\Exceptions;

use RuntimeException;

class MediaInUseException extends RuntimeException
{
    /**
     * @param  list<string>  $usages
     */
    public function __construct(public readonly array $usages)
    {
        parent::__construct('A mídia está em uso: '.implode(', ', $usages).'.');
    }
}
