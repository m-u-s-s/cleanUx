{{-- SP2 Task 6 — Sélection du type de prestataire (3 paliers) — refonte visuelle design-system (logique Livewire inchangée) --}}
<div class="brio-card space-y-5">
    <div>
        <h3 class="brio-section-title text-base md:text-lg">Type de prestataire</h3>
        <p class="brio-section-subtitle">Choisissez qui interviendra. « Peu importe » laisse notre système trouver le meilleur prestataire disponible.</p>
    </div>

    {{-- Palier 1 — sélecteur de TYPE --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach ([
            'any' => ['Peu importe', 'Le meilleur prestataire disponible'],
            'independent' => ['Indépendant', 'Un prestataire particulier'],
            'company' => ['Société', 'Une entreprise prestataire'],
        ] as $value => $labels)
            <button
                type="button"
                wire:click="$set('providerTypePreference', '{{ $value }}')"
                aria-pressed="{{ $providerTypePreference === $value ? 'true' : 'false' }}"
                class="flex min-h-[44px] flex-col justify-center gap-0.5 rounded-2xl border px-4 py-3 text-left transition {{ $providerTypePreference === $value ? 'border-sky-500 bg-sky-50 shadow-sm ring-1 ring-sky-200' : 'border-slate-200 bg-white hover:border-sky-200 hover:bg-sky-50/40' }}">
                <span class="text-sm font-bold text-slate-900">{{ $labels[0] }}</span>
                <span class="text-xs text-slate-500">{{ $labels[1] }}</span>
            </button>
        @endforeach
    </div>
    @error('providerTypePreference') <p class="ui-error-msg">{{ $message }}</p> @enderror

    {{-- Société actuellement imposée (SP3) --}}
    @if ($assignedProviderOrganizationId)
        <div class="flex items-center justify-between gap-3 rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-indigo-800">
                <x-ui.badge tone="blue" icon="🏢" label="Société choisie" />
                <span class="text-indigo-700">#{{ $assignedProviderOrganizationId }}</span>
            </div>
            <button type="button" wire:click="clearAssignedCompany" class="min-h-[44px] text-sm font-semibold text-indigo-700 underline underline-offset-2 hover:text-indigo-900">
                Retirer
            </button>
        </div>
    @endif

    {{-- Prestataire actuellement imposé --}}
    @if ($preferredProviderUserId)
        <div class="flex items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-emerald-800">
                <x-ui.badge tone="green" icon="👤" label="Prestataire choisi" />
                <span class="text-emerald-700">#{{ $preferredProviderUserId }}</span>
            </div>
            <button type="button" wire:click="clearPreferredProvider" class="min-h-[44px] text-sm font-semibold text-emerald-700 underline underline-offset-2 hover:text-emerald-900">
                Retirer
            </button>
        </div>
    @endif

    {{-- Palier 2 — Mes prestataires favoris (re-réservation 1 clic) --}}
    @if ($this->clientFavorites->isNotEmpty())
        <div class="space-y-2">
            <label class="ui-label">Mes prestataires favoris</label>
            <div class="space-y-2">
                @foreach ($this->clientFavorites as $favorite)
                    <button
                        type="button"
                        wire:click="rebookFavorite({{ $favorite->id }})"
                        class="flex min-h-[44px] w-full items-center justify-between gap-3 rounded-2xl border px-4 py-3 text-left transition {{ (int) $preferredProviderUserId === (int) $favorite->preferred_provider_user_id ? 'border-sky-500 bg-sky-50' : 'border-slate-200 bg-white hover:border-sky-200 hover:bg-sky-50/40' }}">
                        <span class="min-w-0 truncate text-sm font-semibold text-slate-900">
                            {{ $favorite->label ?: optional($favorite->provider)->name ?: 'Prestataire #'.$favorite->preferred_provider_user_id }}
                        </span>
                        <span class="shrink-0 text-xs font-semibold text-sky-600">Re-réserver</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Palier 3 — Choisir un NOUVEAU prestataire (premium) --}}
    {{-- SP2 : aligné sur le gate backend (ProviderSelectionResolver = customerProfile->isPremium). --}}
    @if ($this->canPickPremiumProvider())
        <div class="space-y-3">
            <button
                type="button"
                wire:click="toggleProviderPicker"
                class="brio-btn-secondary min-h-[44px] w-full sm:w-auto">
                <span>{{ $showProviderPicker ? '✕ Masquer la recherche' : '🔍 Choisir un prestataire' }}</span>
            </button>

            @if ($showProviderPicker)
                <div class="brio-card-muted p-3 sm:p-4">
                    @if ($providerTypePreference === 'company')
                        <p class="mb-3 text-xs text-slate-500">Choisissez une société prestataire.</p>
                        <livewire:client.browse-companies
                            :selection-mode="true"
                            :service-zone-id="$resolvedServiceZoneId"
                            :trade-id="$selectedTradeId"
                            :key="'booking-company-picker'" />
                    @else
                        <p class="mb-3 text-xs text-slate-500">Recherchez et sélectionnez un prestataire.</p>
                        <livewire:client.browse-providers :selection-mode="true" :key="'booking-provider-picker'" />
                    @endif
                </div>
            @endif
        </div>
    @else
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <span class="text-base leading-none">⭐</span>
            <p>
                <span class="font-semibold">Premium :</span> choisissez un prestataire spécifique parmi tout le catalogue.
                Réservé au pack Premium (vos favoris restent disponibles pour tous).
            </p>
        </div>
    @endif
</div>

{{-- Encart visuel commun « créneaux alternatifs » : titre + message + chips de créneau cliquables. --}}
{{-- Les chips réutilisent EXACTEMENT le wire:click existant ($set rdvDate/rdvHeure) — mécanisme inchangé. --}}
@php
    $cuAlternativeBlocks = array_values(array_filter([
        ['message' => $preferredProviderMessage, 'slots' => $preferredProviderAlternativeSlots],
        ['message' => $preferredCompanyMessage, 'slots' => $preferredCompanyAlternativeSlots],
    ], fn ($b) => ! empty($b['message'])));
@endphp

@foreach ($cuAlternativeBlocks as $block)
    <div class="brio-card space-y-4 border-amber-200 bg-amber-50/70">
        <div class="flex items-start gap-3">
            <span class="mt-0.5 text-lg leading-none">🕒</span>
            <div>
                <h3 class="brio-section-title text-base text-amber-900">Créneaux alternatifs</h3>
                <p class="mt-1 text-sm leading-6 text-amber-800">{{ $block['message'] }}</p>
            </div>
        </div>

        @if (! empty($block['slots']))
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($block['slots'] as $slot)
                    <button
                        type="button"
                        wire:click="$set('rdvDate', '{{ $slot['date'] }}'); $set('rdvHeure', '{{ $slot['heure'] }}')"
                        class="min-h-[44px] rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-sky-300 hover:bg-sky-50">
                        {{ \Illuminate\Support\Carbon::parse($slot['date'])->format('d/m') }} · {{ $slot['heure'] }}
                    </button>
                @endforeach
            </div>
        @endif

        <button
            type="button"
            wire:click="bookAnyAvailableProvider"
            class="brio-btn-primary min-h-[44px] w-full sm:w-auto">
            Je suis pressé — n’importe quel prestataire disponible
        </button>
    </div>
@endforeach
