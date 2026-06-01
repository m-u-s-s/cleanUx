{{-- SP2 Task 6 — Sélection du type de prestataire (3 paliers) --}}
<div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4">
    <div>
        <label class="block text-sm font-semibold text-slate-700">Type de prestataire</label>
        <p class="mt-1 text-xs text-slate-500">Choisissez qui interviendra. « Peu importe » laisse notre système trouver le meilleur prestataire disponible.</p>
    </div>

    {{-- Palier 1 — sélecteur de TYPE --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        @foreach ([
            'any' => ['Peu importe', 'Le meilleur prestataire disponible'],
            'independent' => ['Indépendant', 'Un prestataire particulier'],
            'company' => ['Société', 'Une entreprise prestataire'],
        ] as $value => $labels)
            <button
                type="button"
                wire:click="$set('providerTypePreference', '{{ $value }}')"
                class="rounded-2xl border px-4 py-3 text-left transition {{ $providerTypePreference === $value ? 'border-blue-500 bg-blue-50 shadow-sm' : 'border-slate-200 bg-white hover:border-blue-200 hover:bg-blue-50/40' }}">
                <div class="font-bold text-slate-900">{{ $labels[0] }}</div>
                <div class="text-xs text-slate-600">{{ $labels[1] }}</div>
            </button>
        @endforeach
    </div>
    @error('providerTypePreference') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

    {{-- Société actuellement imposée (SP3) --}}
    @if ($assignedProviderOrganizationId)
        <div class="flex items-center justify-between rounded-2xl border border-indigo-300 bg-indigo-50 px-4 py-3">
            <div class="text-sm text-indigo-800">
                <span class="font-semibold">Société choisie</span>
                <span class="text-indigo-700">(#{{ $assignedProviderOrganizationId }})</span>
            </div>
            <button type="button" wire:click="clearAssignedCompany" class="text-sm font-semibold text-indigo-700 underline">
                Retirer
            </button>
        </div>
    @endif

    {{-- Prestataire actuellement imposé --}}
    @if ($preferredProviderUserId)
        <div class="flex items-center justify-between rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3">
            <div class="text-sm text-emerald-800">
                <span class="font-semibold">Prestataire choisi</span>
                <span class="text-emerald-700">(#{{ $preferredProviderUserId }})</span>
            </div>
            <button type="button" wire:click="clearPreferredProvider" class="text-sm font-semibold text-emerald-700 underline">
                Retirer
            </button>
        </div>
    @endif

    {{-- Palier 2 — Mes prestataires favoris (re-réservation 1 clic) --}}
    @if ($this->clientFavorites->isNotEmpty())
        <div class="space-y-2">
            <label class="block text-sm font-semibold text-slate-700">Mes prestataires favoris</label>
            <div class="space-y-2">
                @foreach ($this->clientFavorites as $favorite)
                    <button
                        type="button"
                        wire:click="rebookFavorite({{ $favorite->id }})"
                        class="flex w-full items-center justify-between rounded-2xl border px-4 py-3 text-left transition {{ (int) $preferredProviderUserId === (int) $favorite->preferred_provider_user_id ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white hover:border-blue-200 hover:bg-blue-50/40' }}">
                        <span class="font-semibold text-slate-900">
                            {{ $favorite->label ?: optional($favorite->provider)->name ?: 'Prestataire #'.$favorite->preferred_provider_user_id }}
                        </span>
                        <span class="text-xs font-semibold text-blue-600">Re-réserver</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Palier 3 — Choisir un NOUVEAU prestataire (premium) --}}
    {{-- SP2 : aligné sur le gate backend (ProviderSelectionResolver = customerProfile->isPremium). --}}
    @if ($this->canPickPremiumProvider())
        <div class="space-y-2">
            <button
                type="button"
                wire:click="toggleProviderPicker"
                class="inline-flex items-center gap-2 rounded-2xl border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">
                {{ $showProviderPicker ? 'Masquer la recherche' : 'Choisir un prestataire' }}
            </button>

            @if ($showProviderPicker)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                    @if ($providerTypePreference === 'company')
                        <p class="mb-2 text-xs text-slate-500">Choisissez une société prestataire.</p>
                        <livewire:client.browse-companies
                            :selection-mode="true"
                            :service-zone-id="$resolvedServiceZoneId"
                            :trade-id="$selectedTradeId"
                            :key="'booking-company-picker'" />
                    @else
                        <p class="mb-2 text-xs text-slate-500">Recherchez et sélectionnez un prestataire.</p>
                        <livewire:client.browse-providers :selection-mode="true" :key="'booking-provider-picker'" />
                    @endif
                </div>
            @endif
        </div>
    @else
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <span class="font-semibold">Premium :</span> choisissez un prestataire spécifique parmi tout le catalogue.
            Réservé au pack Premium (vos favoris restent disponibles pour tous).
        </div>
    @endif
</div>

{{-- Flux indispo — créneaux alternatifs si le presta préféré est indisponible --}}
@if ($preferredProviderMessage)
    <div class="space-y-3 rounded-2xl border border-amber-300 bg-amber-50 p-4">
        <p class="text-sm font-semibold text-amber-800">{{ $preferredProviderMessage }}</p>

        @if (! empty($preferredProviderAlternativeSlots))
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach ($preferredProviderAlternativeSlots as $slot)
                    <button
                        type="button"
                        wire:click="$set('rdvDate', '{{ $slot['date'] }}'); $set('rdvHeure', '{{ $slot['heure'] }}')"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:border-blue-300 hover:bg-blue-50">
                        {{ \Illuminate\Support\Carbon::parse($slot['date'])->format('d/m') }} · {{ $slot['heure'] }}
                    </button>
                @endforeach
            </div>
        @endif

        <button
            type="button"
            wire:click="bookAnyAvailableProvider"
            class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Je suis pressé — n’importe quel prestataire disponible
        </button>
    </div>
@endif

{{-- Flux indispo — créneaux alternatifs si la société préférée n'a aucun worker dispo --}}
@if ($preferredCompanyMessage)
    <div class="space-y-3 rounded-2xl border border-amber-300 bg-amber-50 p-4">
        <p class="text-sm font-semibold text-amber-800">{{ $preferredCompanyMessage }}</p>

        @if (! empty($preferredCompanyAlternativeSlots))
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach ($preferredCompanyAlternativeSlots as $slot)
                    <button
                        type="button"
                        wire:click="$set('rdvDate', '{{ $slot['date'] }}'); $set('rdvHeure', '{{ $slot['heure'] }}')"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:border-blue-300 hover:bg-blue-50">
                        {{ \Illuminate\Support\Carbon::parse($slot['date'])->format('d/m') }} · {{ $slot['heure'] }}
                    </button>
                @endforeach
            </div>
        @endif

        <button
            type="button"
            wire:click="bookAnyAvailableProvider"
            class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Je suis pressé — n’importe quel prestataire disponible
        </button>
    </div>
@endif
