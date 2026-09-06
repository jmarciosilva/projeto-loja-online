<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateVisualIdentitySettingsRequest;
use App\Services\VisualIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Configuração administrativa de logo e favicon.
 *
 * Controller fino: toda a regra — chaves, contrato de ausência, resolução das
 * mídias e persistência — vive no `VisualIdentityService`. Nada de consulta ao
 * banco aqui, e nenhuma proteção paralela de exclusão: mídia em uso é
 * responsabilidade do `MediaUsageRegistry`, alimentado no bootstrap.
 */
class VisualIdentitySettingController extends Controller
{
    public function edit(VisualIdentityService $identity): View
    {
        return view('admin.settings.identity', [
            'logo' => $identity->logo(),
            'favicon' => $identity->favicon(),
            'logoMediaId' => $identity->logoMediaId(),
            'faviconMediaId' => $identity->faviconMediaId(),
            'availableMedia' => $identity->availableMedia(),
            'identity' => $identity,
        ]);
    }

    public function update(
        UpdateVisualIdentitySettingsRequest $request,
        VisualIdentityService $identity,
    ): RedirectResponse {
        $validated = $request->validated();

        // Campo ausente e campo vazio significam a mesma coisa — "sem mídia" —,
        // e o serviço grava isso como configuração `null`.
        $identity->save(
            $validated['logo_media_id'] ?? null,
            $validated['favicon_media_id'] ?? null,
        );

        return redirect()
            ->route('admin.settings.identity.edit')
            ->with('status', 'Identidade visual salva.');
    }
}
