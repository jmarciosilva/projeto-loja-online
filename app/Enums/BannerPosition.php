<?php

namespace App\Enums;

/**
 * Onde um banner é apresentado no site.
 *
 * String-backed e persistido em `VARCHAR(32)`, pela mesma razão de
 * `PageStatus`: um `ENUM` nativo do MySQL amarraria cada nova posição a uma
 * migration de alteração de schema, enquanto aqui o conjunto de posições fica
 * definido — e versionado — em PHP.
 *
 * A ordem dos banners é **contextual à posição**: comparar o `sort_order` de um
 * `hero` com o de um `footer` não significa nada.
 */
enum BannerPosition: string
{
    case Hero = 'hero';
    case Sidebar = 'sidebar';
    case Footer = 'footer';
}
