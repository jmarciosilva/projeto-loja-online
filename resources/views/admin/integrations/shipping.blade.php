@extends('layouts.admin')

@section('title', 'Integrações de frete')

@section('content')
    <div class="max-w-4xl space-y-6">
        <header>
            <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Integrações</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Frete</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">
                Configure Frenet e Melhor Envio separadamente. Nesta etapa, as configurações são armazenadas com segurança,
                mas não há chamadas às APIs, cotações, compras de etiquetas ou validação de conexão.
            </p>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="text-sm font-medium text-red-800">Não foi possível salvar</p>
                <ul class="mt-1 space-y-1 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section aria-labelledby="provedor-ativo" class="rounded-xl border border-gray-200 bg-white p-5 sm:p-7">
            <h2 id="provedor-ativo" class="text-lg font-semibold">Provedor selecionado</h2>
            <p class="mt-1 text-sm text-gray-600">
                A seleção será usada por futuras cotações depois que os adaptadores forem implementados.
                Ela não ativa uma integração operacional nesta etapa.
            </p>

            <form method="POST" action="{{ route('admin.integrations.shipping.active-provider.update') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                @method('PUT')
                <div class="min-w-0 flex-1">
                    <label for="active_provider" class="block text-sm font-medium">Frete</label>
                    <select id="active_provider" name="active_provider" class="mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5">
                        <option value="" @selected($activeProvider === null)>Desativado</option>
                        @foreach (\App\Enums\ShippingProviderCode::cases() as $provider)
                            <option value="{{ $provider->value }}" @selected($activeProvider === $provider)>{{ $provider->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="min-h-11 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                    Salvar seleção
                </button>
            </form>

            @if ($activeProvider !== null)
                @php($activeConfiguration = $providers[$activeProvider->value])
                <p class="mt-3 text-sm {{ $activeConfiguration['configurationStatus'] === 'configured' ? 'text-amber-800' : 'text-red-700' }}">
                    {{ $activeProvider->label() }} está selecionado, mas {{ $activeConfiguration['configurationStatus'] === 'configured' ? 'a conexão ainda não foi validada' : 'a configuração está incompleta' }}.
                </p>
            @endif
        </section>

        @foreach ($providers as $providerKey => $providerConfig)
            @php($provider = $providerConfig['provider'])
            <section aria-labelledby="provider-{{ $providerKey }}" class="rounded-xl border border-gray-200 bg-white p-5 sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="provider-{{ $providerKey }}" class="text-lg font-semibold">{{ $provider->label() }}</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            @if ($providerConfig['configurationStatus'] === 'not_configured')
                                Não configurado.
                            @elseif ($providerConfig['configurationStatus'] === 'incomplete')
                                Configuração parcial; confira os campos e as orientações abaixo.
                            @else
                                Configurado localmente. {{ $providerConfig['connectionStatus']->label() }}.
                            @endif
                        </p>
                    </div>
                    @if ($activeProvider === $provider)
                        <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">Selecionado</span>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.integrations.shipping.provider.update', $provider->value) }}" class="mt-6 space-y-5">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="{{ $providerKey }}-environment" class="block text-sm font-medium">Ambiente</label>
                            <select id="{{ $providerKey }}-environment" name="environment" required class="mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5">
                                <option value="">Selecione</option>
                                @foreach ($providerConfig['environmentOptions'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('environment', $providerConfig['environment']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="{{ $providerKey }}-origin" class="block text-sm font-medium">CEP de origem</label>
                            <input id="{{ $providerKey }}-origin" name="origin_postal_code" type="text" inputmode="numeric" autocomplete="postal-code" maxlength="9" required value="{{ old('origin_postal_code', $providerConfig['originPostalCode']) }}" placeholder="00000-000" class="mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5">
                        </div>
                    </div>

                    <div>
                        <label for="{{ $providerKey }}-services" class="block text-sm font-medium">Códigos de serviços habilitados</label>
                        <textarea id="{{ $providerKey }}-services" name="enabled_service_codes" rows="3" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 font-mono text-sm" placeholder="Um código por linha">{{ implode("\n", (array) old('enabled_service_codes', $providerConfig['enabledServiceCodes'])) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Opcional. Informe códigos conhecidos, um por linha. A consulta automática do catálogo de serviços ainda não está disponível.</p>
                    </div>

                    <fieldset class="space-y-4 border-t border-gray-100 pt-5">
                        <legend class="text-sm font-semibold">Credenciais</legend>
                        @foreach ($providerConfig['credentialFields'] as $field => $credential)
                            <div>
                                <label for="{{ $providerKey }}-{{ $field }}" class="block text-sm font-medium">{{ $credential['label'] }}</label>
                                <input id="{{ $providerKey }}-{{ $field }}" name="credentials[{{ $field }}]" type="password" autocomplete="new-password" value="" class="mt-1 block min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5" placeholder="{{ $credential['configured'] ? 'Já configurado — deixe vazio para preservar' : 'Ainda não informado' }}">
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $credential['configured'] ? 'Segredo salvo e oculto. Digite outro valor somente para substituí-lo.' : 'O valor será criptografado ao ser salvo.' }}
                                </p>
                            </div>
                        @endforeach
                        @if ($provider === \App\Enums\ShippingProviderCode::MelhorEnvio)
                            <p class="rounded-lg bg-amber-50 p-3 text-sm leading-6 text-amber-900">
                                O Client ID e o Client Secret identificam o aplicativo. A autorização OAuth da conta e a renovação de tokens ainda não foram implementadas; por isso, salvar esses dados não configura uma conexão nem permite cotações.
                            </p>
                        @else
                            <p class="rounded-lg bg-blue-50 p-3 text-sm leading-6 text-blue-900">
                                A Frenet solicita Token do cliente e Partner Token. Os tokens serão armazenados criptografados; nenhum teste de conexão será feito ao salvar.
                            </p>
                        @endif
                    </fieldset>

                    <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 pt-5">
                        <button type="submit" class="min-h-11 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">Salvar configuração</button>
                        <span class="text-xs text-gray-500">Conexão: {{ $providerConfig['connectionStatus']->label() }}. Nenhuma chamada externa foi realizada.</span>
                    </div>
                </form>

                @if (collect($providerConfig['credentialFields'])->contains(fn (array $field): bool => $field['configured']))
                    <form method="POST" action="{{ route('admin.integrations.shipping.credentials.destroy', $provider->value) }}" class="mt-3 border-t border-gray-100 pt-4" onsubmit="return confirm('Remover as credenciais salvas deste provedor?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="min-h-11 rounded-lg px-3 py-2 text-sm font-medium text-red-700 underline underline-offset-4">Remover credenciais salvas</button>
                    </form>
                @endif
            </section>
        @endforeach
    </div>
@endsection
