<?php

namespace App\Enums;

/**
 * Ciclo de publicação de uma página estática.
 *
 * O enum é string-backed e a coluna `status` é um VARCHAR indexado: um ENUM
 * nativo do MySQL amarraria cada novo estado a uma migration de alteração de
 * schema, enquanto aqui o conjunto de estados é definido — e versionado — em
 * PHP.
 */
enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
