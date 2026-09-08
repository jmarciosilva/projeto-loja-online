<?php

namespace App\Enums;

/**
 * Para onde um item de menu aponta.
 *
 * String-backed e persistido em `VARCHAR(20)`, pela mesma razão de `PageStatus`
 * e `BannerPosition`: um `ENUM` nativo do MySQL amarraria cada novo destino a
 * uma migration de alteração de schema, enquanto aqui o conjunto fica definido
 * — e versionado — em PHP.
 *
 * São **dois** destinos, e não um alvo polimórfico: hoje a aplicação só tem
 * `Page` e URL customizada comprovados. `Product`, `Category`, `Brand` e
 * `Collection` pertencem a fases futuras ou sequer têm contrato, e modelar a
 * generalidade agora custaria a FK real para `pages` em troca de flexibilidade
 * para entidades cujo formato ninguém conhece.
 */
enum MenuItemType: string
{
    case Page = 'page';
    case Url = 'url';
}
