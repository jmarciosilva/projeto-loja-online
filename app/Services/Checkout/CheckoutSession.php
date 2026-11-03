<?php

namespace App\Services\Checkout;

use Illuminate\Contracts\Session\Session;

/**
 * Dados do comprador preservados temporariamente na sessão durante o checkout.
 *
 * Não há tabela de clientes nem de endereços nesta etapa. Só são guardados
 * dados já validados e normalizados.
 */
final class CheckoutSession
{
    private const KEY = 'checkout.buyer';

    public function __construct(private readonly Session $session) {}

    /** @return array<string, string>|null */
    public function buyer(): ?array
    {
        $stored = $this->session->get(self::KEY);

        return is_array($stored) ? $stored : null;
    }

    /** @param array<string, string> $buyer */
    public function putBuyer(array $buyer): void
    {
        $this->session->put(self::KEY, $buyer);
    }

    public function forget(): void
    {
        $this->session->forget(self::KEY);
    }
}
