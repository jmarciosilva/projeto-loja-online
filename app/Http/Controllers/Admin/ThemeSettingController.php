<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateThemeSettingsRequest;
use App\Services\SiteSettingService;
use App\Services\ThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ThemeSettingController extends Controller
{
    public function edit(ThemeService $theme): View
    {
        return view('admin.settings.theme', [
            'colors' => $theme->colors(),
        ]);
    }

    public function update(
        UpdateThemeSettingsRequest $request,
        ThemeService $theme,
        SiteSettingService $siteSettings,
    ): RedirectResponse {
        $validated = $request->validated();
        $settings = [];

        // As chaves vêm do ThemeService para não repetir aqui o contrato; o
        // lote é fixo nos três nomes conhecidos, então nada enviado fora deles
        // chega à persistência.
        foreach ($theme->keys() as $name => $key) {
            $settings[$key] = [
                'type' => 'string',
                // Normaliza para maiúsculas: #abcdef e #ABCDEF são a mesma cor,
                // e gravar as duas formas faria comparações e diffs divergirem.
                'value' => strtoupper($validated["{$name}_color"]),
            ];
        }

        $siteSettings->setMany($settings);

        return redirect()
            ->route('admin.settings.theme.edit')
            ->with('status', 'Tema salvo.');
    }
}
