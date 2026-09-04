<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingsRequest;
use App\Services\SiteSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SiteSettingController extends Controller
{
    /**
     * Mapa entre os campos do formulário e as chaves suportadas.
     *
     * É deliberadamente fixo: a interface administra um conjunto conhecido de
     * configurações, não a tabela `site_settings` inteira. Qualquer campo
     * enviado fora deste mapa é ignorado, o que impede a criação de chaves
     * arbitrárias por quem manipular o formulário.
     *
     * Os nomes dos campos não usam ponto porque o PHP converte `.` em `_` ao
     * popular dados de formulário.
     *
     * @var array<string, string>
     */
    private const SUPPORTED_KEYS = [
        'name' => 'site.name',
        'support_email' => 'site.support_email',
        'phone' => 'site.phone',
        'address' => 'site.address',
    ];

    public function edit(SiteSettingService $siteSettings): View
    {
        return view('admin.settings.edit', [
            'settings' => [
                'name' => $siteSettings->get('site.name', config('app.name')),
                'support_email' => $siteSettings->get('site.support_email', ''),
                'phone' => $siteSettings->get('site.phone', ''),
                'address' => $siteSettings->get('site.address', ''),
            ],
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request, SiteSettingService $siteSettings): RedirectResponse
    {
        $validated = $request->validated();
        $settings = [];

        foreach (self::SUPPORTED_KEYS as $field => $key) {
            // Campos opcionais ausentes ou nulos viram string vazia: as quatro
            // configurações têm contrato `string`, e persistir null exigiria o
            // tipo `null`, mudando o contrato da chave a cada formulário salvo.
            $settings[$key] = [
                'type' => 'string',
                'value' => $validated[$field] ?? '',
            ];
        }

        $siteSettings->setMany($settings);

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'Configurações salvas.');
    }
}
