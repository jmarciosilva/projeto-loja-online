@php
    $inputClass = 'theme-focus mt-1 min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3 text-base text-slate-900 placeholder:text-slate-400';
@endphp

<div class="w-full">
    @if ($saved)
        <div role="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
            Dados salvos. Você pode revisá-los e alterá-los quando quiser. A escolha de entrega será liberada na próxima etapa.
        </div>
    @endif

    <form wire:submit="save" novalidate class="space-y-8">
        <fieldset class="space-y-4">
            <legend class="text-lg font-bold text-slate-900">Seus dados</legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="buyer-name" class="block text-sm font-semibold text-slate-800">Nome completo <span class="text-red-700" aria-hidden="true">*</span></label>
                    <input id="buyer-name" type="text" wire:model="name" autocomplete="name" required aria-required="true" @error('name') aria-invalid="true" aria-describedby="buyer-name-error" @enderror class="{{ $inputClass }}">
                    @error('name') <p id="buyer-name-error" role="alert" class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="buyer-email" class="block text-sm font-semibold text-slate-800">E-mail <span class="text-red-700" aria-hidden="true">*</span></label>
                    <input id="buyer-email" type="email" wire:model="email" autocomplete="email" inputmode="email" required aria-required="true" @error('email') aria-invalid="true" aria-describedby="buyer-email-error" @enderror class="{{ $inputClass }}">
                    @error('email') <p id="buyer-email-error" role="alert" class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="buyer-phone" class="block text-sm font-semibold text-slate-800">Celular com DDD <span class="text-red-700" aria-hidden="true">*</span></label>
                    <input id="buyer-phone" type="tel" wire:model="phone" autocomplete="tel-national" inputmode="tel" placeholder="(11) 91234-5678" required aria-required="true" @error('phone') aria-invalid="true" aria-describedby="buyer-phone-error" @enderror class="{{ $inputClass }}">
                    @error('phone') <p id="buyer-phone-error" role="alert" class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="text-lg font-bold text-slate-900">Endereço de entrega</legend>

            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-2">
                    <label for="buyer-cep" class="block text-sm font-semibold text-slate-800">CEP <span class="text-red-700" aria-hidden="true">*</span></label>
                    <input id="buyer-cep" type="text" wire:model="postalCode" autocomplete="postal-code" inputmode="numeric" placeholder="01310-100" required aria-required="true" @error('postalCode') aria-invalid="true" aria-describedby="buyer-cep-error" @enderror class="{{ $inputClass }}">
                    @error('postalCode') <p id="buyer-cep-error" role="alert" class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-4">
                    <label for="buyer-street" class="block text-sm font-semibold text-slate-800">Logradouro <span class="text-red-700" aria-hidden="true">*</span></label>
                    <input id="buyer-street" type="text" wire:model="street" autocomplete="address-line1" required aria-required="true" @error('street') aria-invalid="true" aria-describedby="buyer-street-error" @enderror class="{{ $inputClass }}">
                    @error('street') <p id="buyer-street-error" role="alert" class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="buyer-number" class="block text-sm font-semibold text-slate-800">Número <span class="text-red-700" aria-hidden="true">*</span></label>
                    <input id="buyer-number" type="text" wire:model="number" autocomplete="off" required aria-required="true" @error('number') aria-invalid="true" aria-describedby="buyer-number-error" @enderror class="{{ $inputClass }}">
                    @error('number') <p id="buyer-number-error" role="alert" class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-4">
                    <label for="buyer-complement" class="block text-sm font-semibold text-slate-800">Complemento</label>
                    <input id="buyer-complement" type="text" wire:model="complement" autocomplete="address-line2" @error('complement') aria-invalid="true" aria-describedby="buyer-complement-error" @enderror class="{{ $inputClass }}">
                    @error('complement') <p id="buyer-complement-error" role="alert" class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-3">
                    <label for="buyer-district" class="block text-sm font-semibold text-slate-800">Bairro <span class="text-red-700" aria-hidden="true">*</span></label>
                    <input id="buyer-district" type="text" wire:model="district" required aria-required="true" @error('district') aria-invalid="true" aria-describedby="buyer-district-error" @enderror class="{{ $inputClass }}">
                    @error('district') <p id="buyer-district-error" role="alert" class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-3">
                    <label for="buyer-city" class="block text-sm font-semibold text-slate-800">Cidade <span class="text-red-700" aria-hidden="true">*</span></label>
                    <input id="buyer-city" type="text" wire:model="city" autocomplete="address-level2" required aria-required="true" @error('city') aria-invalid="true" aria-describedby="buyer-city-error" @enderror class="{{ $inputClass }}">
                    @error('city') <p id="buyer-city-error" role="alert" class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="buyer-state" class="block text-sm font-semibold text-slate-800">UF <span class="text-red-700" aria-hidden="true">*</span></label>
                    <select id="buyer-state" wire:model="state" autocomplete="address-level1" required aria-required="true" @error('state') aria-invalid="true" aria-describedby="buyer-state-error" @enderror class="{{ $inputClass }}">
                        <option value="">Selecione</option>
                        @foreach ($states as $option)
                            <option value="{{ $option->value }}">{{ $option->value }}</option>
                        @endforeach
                    </select>
                    @error('state') <p id="buyer-state-error" role="alert" class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-6">
                    <label for="buyer-reference" class="block text-sm font-semibold text-slate-800">Ponto de referência</label>
                    <input id="buyer-reference" type="text" wire:model="reference" @error('reference') aria-invalid="true" aria-describedby="buyer-reference-error" @enderror class="{{ $inputClass }}">
                    @error('reference') <p id="buyer-reference-error" role="alert" class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>

        <p class="text-sm text-slate-600"><span class="text-red-700" aria-hidden="true">*</span> Campos obrigatórios.</p>

        <div class="flex flex-col gap-3 sm:flex-row">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="theme-focus theme-bg-primary inline-flex min-h-12 items-center justify-center rounded-xl px-6 text-base font-bold text-white shadow-sm disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Salvar e continuar</span>
                <span wire:loading wire:target="save">Salvando…</span>
            </button>
            <a href="{{ route('cart.index') }}" class="theme-focus inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-6 font-semibold text-slate-800 hover:bg-slate-50">Voltar ao carrinho</a>
        </div>
    </form>
</div>
