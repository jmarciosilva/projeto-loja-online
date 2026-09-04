<?php

namespace App\Services;

class ThemeService
{
    /**
     * Contrato das cores do tema: nome interno, chave persistida e default.
     *
     * É a fonte única — controller, view e testes derivam daqui em vez de
     * repetir as chaves ou os valores padrão. Duplicá-los faria um default
     * divergir do outro silenciosamente.
     *
     * @var array<string, array{key: string, default: string}>
     */
    private const COLORS = [
        'primary' => ['key' => 'theme.primary_color', 'default' => '#111827'],
        'secondary' => ['key' => 'theme.secondary_color', 'default' => '#4B5563'],
        'accent' => ['key' => 'theme.accent_color', 'default' => '#2563EB'],
    ];

    public function __construct(private readonly SiteSettingService $siteSettings) {}

    /**
     * Cores do tema prontas para apresentação, no formato `#RRGGBB`.
     *
     * A leitura aplica os defaults sem persistir: exibir o tema nunca cria
     * registros que o administrador não salvou.
     *
     * @return array<string, string>
     */
    public function colors(): array
    {
        $colors = [];

        foreach (self::COLORS as $name => $color) {
            $colors[$name] = $this->siteSettings->get($color['key'], $color['default']);
        }

        return $colors;
    }

    /**
     * @return array<string, string>
     */
    public function defaults(): array
    {
        return array_map(static fn (array $color): string => $color['default'], self::COLORS);
    }

    /**
     * Nome interno => chave persistida, para quem precisa montar o lote de escrita.
     *
     * @return array<string, string>
     */
    public function keys(): array
    {
        return array_map(static fn (array $color): string => $color['key'], self::COLORS);
    }
}
