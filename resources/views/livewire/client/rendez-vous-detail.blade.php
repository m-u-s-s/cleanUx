<x-page-shell
    :title="$reservation->service_display_name"
    :subtitle="__('Votre intervention : le suivi, le détail et ce que vous pouvez encore changer.')">

    <x-slot name="actions">
        <a href="{{ route('client.rendezvous.index') }}" class="brio-btn-secondary inline-flex items-center gap-2">
            <x-ui.icon name="arrow-left" class="w-4 h-4" />
            <span>{{ __('Mes rendez-vous') }}</span>
        </a>

        {{-- LE SUIVI PLEIN ECRAN : son seul lien vivait sur la ligne de la liste. --}}
        @if($reservation->mission && Route::has('client.missions.tracking'))
            <a href="{{ route('client.missions.tracking', $reservation->mission) }}" class="brio-btn-secondary inline-flex items-center gap-2">
                <x-ui.icon name="map-pin" class="w-4 h-4" />
                <span>{{ __('Suivre mon prestataire') }}</span>
            </a>
        @endif

        @if($reservation->recurring_series_id && Route::has('client.rendezvous.series'))
            <a href="{{ route('client.rendezvous.series', $reservation) }}" class="brio-btn-secondary inline-flex items-center gap-2">
                <x-ui.icon name="refresh" class="w-4 h-4" />
                <span>{{ __('Gérer la série') }}</span>
            </a>
        @endif
    </x-slot>

    <div class="space-y-5">
        {{-- CE QUE LA LIGNE NE DIT PLUS : l'etat, l'heure et le prestataire, en tete de page. --}}
        <div class="flex flex-wrap items-center gap-3">
            <x-badge :status="$reservation->status" />
            <x-priority-badge :priority="$reservation->priorite" />
            <span class="text-sm text-slate-600 dark:text-slate-400">
                {{ $reservation->date }} à {{ $reservation->heure }}
            </span>
            <span class="text-sm text-slate-600 dark:text-slate-400">
                {{ $reservation->employe->name ?? __('Prestataire à confirmer') }}
            </span>
        </div>

        {{-- LE SUIVI, DEPLACE DEPUIS CHAQUE LIGNE DE LA LISTE. Il monte le cockpit complet
             (carte, codes, taches, consigne, annulation) des qu'une mission existe. --}}
        @include('livewire.client.rendezvous.mission-tracking-panel', ['rdv' => $reservation])

        {{-- LES HUIT CHAMPS QUE LA LIGNE PORTAIT : ici, une seule fois. --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('Le détail') }}</h3>

            <div class="mt-3 grid grid-cols-1 gap-4 text-sm text-slate-700 md:grid-cols-2 dark:text-slate-300">
                <div class="space-y-1">
                    <p><span class="font-medium">{{ __('Type de lieu') }} :</span> {{ ucfirst($reservation->place_type ?? '—') }}</p>
                    <p><span class="font-medium">{{ __('Fréquence') }} :</span> {{ ucfirst(str_replace('_', ' ', $reservation->frequency ?? '—')) }}</p>
                    <p><span class="font-medium">{{ __('Surface') }} :</span> {{ $reservation->surface ?? ($reservation->surface_m2 ? $reservation->surface_m2 . ' m²' : '—') }}</p>
                    <p><span class="font-medium">{{ __('Durée estimée') }} :</span> {{ $reservation->estimated_duration_minutes ? $reservation->estimated_duration_minutes . ' min' : '—' }}</p>
                </div>

                <div class="space-y-1">
                    <p><span class="font-medium">{{ __('Adresse') }} :</span> {{ $reservation->adresse ?? '—' }}</p>
                    <p><span class="font-medium">{{ __('Ville') }} :</span> {{ $reservation->ville ?? '—' }}</p>
                    <p><span class="font-medium">{{ __('Code postal') }} :</span> {{ $reservation->postal_code_display }}</p>
                    <p><span class="font-medium">{{ __('Téléphone') }} :</span> {{ $reservation->contact_phone ?? '—' }}</p>
                </div>
            </div>

            @if($reservation->customer_comment)
                <p class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    <span class="font-medium">{{ __('Remarque') }} :</span> {{ $reservation->customer_comment }}
                </p>
            @endif
        </div>
    </div>
</x-page-shell>
