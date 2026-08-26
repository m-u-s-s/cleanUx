<div class="grid grid-cols-1 gap-6 xl:grid-cols-3" wire:loading.remove>
    {{-- Colonne principale --}}
    <div class="space-y-6 xl:col-span-2" aria-live="polite">

        {{-- Prochain rendez-vous --}}
        <x-app-card padding="p-6" :title="__('Prochain rendez-vous')" :subtitle="__('Votre prochaine intervention et les actions rapides.')">
            @if($prochainRendezVous)
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :status="$prochainRendezVous->status" />
                            <x-priority-badge :priority="$prochainRendezVous->priorite" />
                        </div>

                        <h3 class="mt-4 text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ $prochainRendezVous->service_display_name ?: __('Service non précisé') }}
                        </h3>

                        <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('Référence') }} :
                            <span class="font-semibold text-slate-700 dark:text-slate-200">
                                {{ $prochainRendezVous->booking_reference ?? '#' . $prochainRendezVous->id }}
                            </span>
                        </p>
                    </div>

                    <div class="shrink-0 rounded-xl border border-brand-100 bg-brand-50/50 px-4 py-3 text-brand-900">
                        <p class="text-xs font-bold uppercase tracking-wide text-brand-600">{{ __('Date prévue') }}</p>
                        <p class="mt-1 text-lg font-bold">
                            {{ optional($prochainRendezVous->date)->format('d/m/Y') ?? $prochainRendezVous->date }}
                        </p>
                        <p class="text-sm font-semibold text-brand-700">
                            {{ substr((string) $prochainRendezVous->heure, 0, 5) }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="rounded-xl border border-slate-200/70 bg-slate-50/50 p-3.5 dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="inline-flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <x-ui.icon name="user" class="w-3.5 h-3.5" />
                            {{ __('Prestataire') }}
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ $prochainRendezVous->employe->name ?? __('À confirmer') }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-slate-200/70 bg-slate-50/50 p-3.5 dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="inline-flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <x-ui.icon name="map-pin" class="w-3.5 h-3.5" />
                            {{ __('Adresse') }}
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-900 truncate dark:text-slate-100">
                            {{ $prochainRendezVous->adresse ?? '—' }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $prochainRendezVous->code_postal ?? '' }} {{ $prochainRendezVous->ville ?? '' }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-slate-200/70 bg-slate-50/50 p-3.5 dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="inline-flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <x-ui.icon name="globe" class="w-3.5 h-3.5" />
                            {{ __('Zone') }}
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ $prochainRendezVous->serviceZone->name ?? __('Non définie') }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-2">
                    @if(Route::has('client.rendezvous.index'))
                        <a href="{{ route('client.rendezvous.index') }}" class="brio-btn-primary inline-flex items-center gap-2">
                            <x-ui.icon name="arrow-right" class="w-4 h-4" />
                            <span>{{ __('Voir le détail') }}</span>
                        </a>
                    @endif

                    @if(!in_array($prochainRendezVous->status, ['en_route', 'sur_place', 'termine', 'refuse']))
                        <button type="button" wire:click="modifier({{ $prochainRendezVous->id }})" class="brio-btn-secondary">
                            {{ __('Modifier') }}
                        </button>

                        <button type="button" wire:click="annuler({{ $prochainRendezVous->id }})" class="brio-btn-danger">
                            {{ __('Annuler') }}
                        </button>
                    @endif
                </div>

                @if((int) $editRdvId === (int) $prochainRendezVous->id)
                    <form wire:submit.prevent="enregistrerModif" class="mt-6 rounded-xl border border-brand-100 bg-brand-50/40 p-5">
                        <p class="text-sm font-bold uppercase tracking-wide text-brand-700">
                            {{ __('Modifier ce rendez-vous') }}
                        </p>

                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="ui-label" for="editDate">{{ __('Nouvelle date') }}</label>
                                <input id="editDate" type="date" wire:model.defer="editDate" class="ui-input">
                            </div>

                            <div>
                                <label class="ui-label" for="editHeure">{{ __('Nouvelle heure') }}</label>
                                <input id="editHeure" type="time" wire:model.defer="editHeure" class="ui-input">
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="submit" class="brio-btn-primary">{{ __('Enregistrer') }}</button>
                            <button type="button" wire:click="fermerEdition" class="brio-btn-secondary">{{ __('Fermer') }}</button>
                        </div>
                    </form>
                @endif
            @else
                <x-empty-state
                    :title="__('Aucun rendez-vous à venir')"
                    :message="__('Planifiez une nouvelle prestation en quelques clics.')"
                    icon="📅">
                    @if(Route::has('client.rendezvous.create'))
                        <a href="{{ route('client.rendezvous.create') }}" class="brio-btn-primary inline-flex items-center gap-2">
                            <x-ui.icon name="plus" class="w-4 h-4" />
                            <span>{{ __('Réserver maintenant') }}</span>
                        </a>
                    @endif
                </x-empty-state>
            @endif
        </x-app-card>

        {{-- Réservation rapide + Adresses récentes --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Quick rebook : style brand clair au lieu du gradient slate sombre --}}
            <div class="rounded-2xl border border-brand-200 bg-gradient-to-br from-brand-50 to-white p-6 shadow-soft-sm dark:border-brand-800 dark:from-slate-900 dark:to-slate-800">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-600 text-white">
                        <x-ui.icon name="refresh" class="w-4 h-4" />
                    </span>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ __('Réservation rapide') }}</p>
                </div>

                <h3 class="mt-3 text-lg font-bold text-slate-900 dark:text-slate-100">{{ __('Même service que la dernière fois') }}</h3>

                @if($dernierRendezVous)
                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex gap-2">
                            <dt class="text-slate-500 shrink-0 dark:text-slate-400">{{ __('Service') }} :</dt>
                            <dd class="font-medium text-slate-800 truncate dark:text-slate-200">{{ $dernierRendezVous->service_display_name }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="text-slate-500 shrink-0 dark:text-slate-400">{{ __('Adresse') }} :</dt>
                            <dd class="font-medium text-slate-800 truncate dark:text-slate-200">{{ $dernierRendezVous->adresse ?? '—' }}, {{ $dernierRendezVous->ville ?? '—' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="text-slate-500 shrink-0 dark:text-slate-400">{{ __('Fréquence') }} :</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-200">{{ ucfirst(str_replace('_', ' ', $dernierRendezVous->frequency ?? '—')) }}</dd>
                        </div>
                    </dl>

                    @if(Route::has('client.rendezvous.create'))
                        <a href="{{ route('client.rendezvous.create', ['prefill' => 'last']) }}" class="brio-btn-primary mt-4 inline-flex items-center gap-2">
                            <x-ui.icon name="refresh" class="w-4 h-4" />
                            <span>{{ __('Reprendre') }}</span>
                        </a>
                    @endif
                @else
                    <p class="mt-3 text-sm text-slate-500">
                        {{ __('Votre dernière prestation apparaîtra ici pour accélérer vos prochaines réservations.') }}
                    </p>
                @endif
            </div>

            <x-app-card padding="p-6" :title="__('Adresses récentes')" :subtitle="__('Réutilisez une adresse en un clic.')">
                <div class="space-y-2.5">
                    @forelse($adressesRecentes as $adresse)
                        <div class="flex flex-col justify-between gap-3 rounded-xl border border-slate-200/70 bg-white p-3.5 sm:flex-row sm:items-center">
                            <div class="min-w-0">
                                <p class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-800">
                                    <x-ui.icon name="map-pin" class="w-3.5 h-3.5 text-slate-400" />
                                    <span class="truncate">{{ $adresse->adresse }}</span>
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500 pl-5">{{ $adresse->ville ?? '—' }} {{ $adresse->code_postal ?? '' }}</p>
                            </div>

                            @if(Route::has('client.rendezvous.create'))
                                <a href="{{ route('client.rendezvous.create', ['adresse' => $adresse->adresse, 'ville' => $adresse->ville, 'code_postal' => $adresse->code_postal]) }}"
                                    class="brio-btn-secondary !py-1.5 !text-xs shrink-0">
                                    {{ __('Utiliser') }}
                                </a>
                            @endif
                        </div>
                    @empty
                        <x-empty-state :title="__('Aucune adresse récente')" :message="__('Vos dernières adresses apparaîtront ici.')" icon="📍" />
                    @endforelse
                </div>
            </x-app-card>
        </div>

        {{-- Liste prochaines interventions --}}
        <x-app-card padding="p-6" wire:loading.class="opacity-60">
            <x-section-header
                :title="__('Mes prochaines interventions')"
                :subtitle="__('Services planifiés à venir.')"
                :action-label="__('Voir tous')"
                :action-href="Route::has('client.rendezvous.index') ? route('client.rendezvous.index') : '#'" />

            <div class="mt-5 space-y-3">
                @forelse($avenir as $rdv)
                    <div class="rounded-xl border border-slate-200/70 bg-white p-4 transition hover:border-slate-300 hover:shadow-soft-sm dark:border-slate-700 dark:bg-slate-800/60 dark:hover:border-slate-600">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <p class="text-base font-semibold text-slate-900 truncate dark:text-slate-100">
                                    {{ $rdv->service_display_name }}
                                </p>

                                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600 dark:text-slate-400">
                                    <span class="inline-flex items-center gap-1">
                                        <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-slate-400" />
                                        {{ optional($rdv->date)->format('d/m/Y') ?? $rdv->date }}
                                        à {{ substr((string) $rdv->heure, 0, 5) }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 min-w-0">
                                        <x-ui.icon name="map-pin" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                        <span class="truncate">{{ $rdv->adresse ?? __('Adresse non précisée') }}, {{ $rdv->ville ?? '—' }}</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <x-ui.icon name="user" class="w-3.5 h-3.5 text-slate-400" />
                                        {{ $rdv->employe->name ?? __('Prestataire à confirmer') }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <x-badge :status="$rdv->status" />
                                <x-priority-badge :priority="$rdv->priorite" />
                            </div>
                        </div>

                        @if($rdv->mission?->report_path)
                            <div class="mt-3 border-t border-slate-100 pt-3">
                                <a href="{{ \App\Support\Media\PrivateMedia::url($rdv->mission->report_path) }}" target="_blank"
                                   class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition">
                                    <x-ui.icon name="document" class="w-3.5 h-3.5" />
                                    {{ __('Rapport') }}
                                </a>
                            </div>
                        @endif
                    </div>
                @empty
                    <x-empty-state :title="__('Aucune intervention à venir')" :message="__('Dès qu\'un rendez-vous sera planifié, il apparaîtra ici.')" icon="🧹" />
                @endforelse
            </div>

            @if(method_exists($avenir, 'links'))
                <div class="mt-6">
                    {{ $avenir->links() }}
                </div>
            @endif
        </x-app-card>
    </div>

    {{-- Colonne latérale --}}
    <div class="space-y-6" aria-live="polite">
        {{-- Profil & couverture --}}
        <x-app-card padding="p-6" :title="__('Profil & couverture')" :subtitle="__('Résumé de votre compte.')">
            <div class="space-y-2.5">
                <div class="flex items-center justify-between rounded-lg border border-slate-200/70 bg-slate-50/50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Type de compte') }}</span>
                    <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $accountContext['type_label'] ?? __('Standard') }}</span>
                </div>

                <div class="flex items-center justify-between rounded-lg border border-slate-200/70 bg-slate-50/50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Zone principale') }}</span>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $accountContext['primary_zone'] ?? __('Non définie') }}</p>
                        @if(($accountContext['zone_count'] ?? 0) > 1)
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $accountContext['zone_count'] }} {{ __('zones') }}</p>
                        @endif
                    </div>
                </div>

                @if($accountContext['organization_name'] ?? false)
                    <div class="flex items-center justify-between rounded-lg border border-slate-200/70 bg-slate-50/50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Organisation') }}</span>
                        <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $accountContext['organization_name'] }}</span>
                    </div>
                @endif
            </div>
        </x-app-card>

        {{-- Premium --}}
        @if($isPremium)
            <x-app-card padding="p-6" :title="__('Premium')" :subtitle="__('Vos avantages et statut.')">
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">{{ __('Statut') }}</span>
                        <span class="inline-flex items-center gap-1 font-semibold text-emerald-700">
                            <x-ui.icon name="check-circle" class="w-3.5 h-3.5" />
                            {{ __('Actif') }}
                        </span>
                    </div>

                    @if(auth()->user()->premium_renewal_at)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">{{ __('Renouvellement') }}</span>
                            <span class="font-semibold text-slate-800">
                                {{ optional(auth()->user()->premium_renewal_at)->format('d/m/Y') }}
                            </span>
                        </div>
                    @endif
                </div>

                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/50 p-3.5">
                    <p class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-800">
                        <x-ui.icon name="star" class="w-3.5 h-3.5" />
                        {{ __('Vos avantages') }}
                    </p>
                    <ul class="mt-2 space-y-1.5 text-xs text-amber-700">
                        <li>{{ __('Choix des prestataires favoris') }}</li>
                        <li>{{ __('Visibilité sur les disponibilités') }}</li>
                        <li>{{ __('Expérience personnalisée') }}</li>
                    </ul>
                </div>
            </x-app-card>
        @else
            <x-app-card padding="p-6" :title="__('Offre Premium')" :subtitle="__('Personnalisez votre expérience.')">
                <ul class="space-y-2 text-sm text-slate-600">
                    <li class="inline-flex items-start gap-2">
                        <x-ui.icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" />
                        <span>{{ __('Choisissez vos prestataires favoris') }}</span>
                    </li>
                    <li class="inline-flex items-start gap-2">
                        <x-ui.icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" />
                        <span>{{ __('Consultez leurs disponibilités') }}</span>
                    </li>
                    <li class="inline-flex items-start gap-2">
                        <x-ui.icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" />
                        <span>{{ __('Réservez plus rapidement') }}</span>
                    </li>
                </ul>

                @if(Route::has('premium.offer'))
                    <a href="{{ route('premium.offer') }}" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 transition">
                        <x-ui.icon name="star" class="w-4 h-4" />
                        {{ __('Découvrir Premium') }}
                    </a>
                @endif
            </x-app-card>
        @endif

        {{-- Documents & finance --}}
        <x-app-card padding="p-6" :title="__('Documents & finance')" :subtitle="__('Devis, factures et reste à payer.')">
            <div class="grid grid-cols-2 gap-2.5">
                <div class="rounded-lg border border-slate-200/70 bg-slate-50/50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Devis') }}</p>
                    <p class="mt-0.5 text-xl font-bold text-slate-900 dark:text-slate-100">{{ $financeSnapshot['quotes_count'] ?? 0 }}</p>
                </div>

                <div class="rounded-lg border border-slate-200/70 bg-slate-50/50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Factures') }}</p>
                    <p class="mt-0.5 text-xl font-bold text-slate-900 dark:text-slate-100">{{ $financeSnapshot['invoices_count'] ?? 0 }}</p>
                </div>

                <div class="rounded-lg border border-rose-200/70 bg-rose-50/50 p-3 dark:border-rose-900/50 dark:bg-rose-950/30">
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ __('En retard') }}</p>
                    <p class="mt-0.5 text-xl font-bold text-rose-700 dark:text-rose-400">{{ $financeSnapshot['overdue_count'] ?? 0 }}</p>
                </div>

                <div class="rounded-lg border border-emerald-200/70 bg-emerald-50/50 p-3 dark:border-emerald-900/50 dark:bg-emerald-950/30">
                    <p class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('À payer') }}</p>
                    <p class="mt-0.5 text-base font-bold text-emerald-700 dark:text-emerald-400">
                        <x-money :amount="(float) ((float) ($financeSnapshot['outstanding_total'] ?? 0))" />
                    </p>
                </div>
            </div>

            @if(Route::has('client.finance'))
                <a href="{{ route('client.finance') }}" class="brio-btn-primary mt-4 inline-flex w-full items-center justify-center gap-2">
                    <x-ui.icon name="document" class="w-4 h-4" />
                    {{ __('Voir mes documents') }}
                </a>
            @endif
        </x-app-card>

        {{-- Services disponibles --}}
        <x-app-card padding="p-6" :title="__('Services disponibles')" :subtitle="__('Selon votre zone.')">
            <div class="space-y-2">
                @forelse($availableServices as $service)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200/70 bg-white p-3 dark:border-slate-700 dark:bg-slate-800/60">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate dark:text-slate-100">{{ $service['name'] }}</p>
                            <p class="text-xs text-slate-500 truncate dark:text-slate-400">{{ $service['zone_name'] ?: __('Zone couverte') }}</p>
                        </div>

                        <div class="text-right shrink-0">
                            @if(! is_null($service['base_price']))
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                    <x-money :amount="(float) ((float) $service['base_price'])" />
                                </p>
                            @endif

                            @if($service['requires_manual_validation'])
                                <p class="text-xs text-amber-700 dark:text-amber-400">{{ __('Validation manuelle') }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <x-empty-state :title="__('Aucun service disponible')" :message="__('Les services disponibles selon votre zone apparaîtront ici.')" icon="🗺️" />
                @endforelse
            </div>
        </x-app-card>

        {{-- Prestataires favoris --}}
        <x-app-card padding="p-6" :title="__('Prestataires favoris')" :subtitle="__('Réservez plus vite avec eux.')">
            @if($isPremium)
                <div class="space-y-2">
                    @forelse($favoriteEmployes as $employe)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200/70 bg-white p-3 dark:border-slate-700 dark:bg-slate-800/60">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="grid h-9 w-9 place-items-center rounded-full bg-amber-100 text-amber-700 font-bold text-xs shrink-0 dark:bg-amber-900/40 dark:text-amber-300">
                                    {{ strtoupper(substr($employe->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-800 truncate dark:text-slate-100">{{ $employe->name }}</p>
                                    <p class="text-xs text-amber-600 inline-flex items-center gap-1">
                                        <x-ui.icon name="star" class="w-3 h-3" />
                                        {{ __('Favori') }}
                                    </p>
                                </div>
                            </div>

                            @if(Route::has('client.rendezvous.create'))
                                <a href="{{ route('client.rendezvous.create', ['employe' => $employe->id]) }}"
                                   class="text-xs font-semibold text-brand-700 hover:text-brand-800 shrink-0">
                                    {{ __('Réserver') }} →
                                </a>
                            @endif
                        </div>
                    @empty
                        <x-empty-state title="Aucun prestataire favori" message="Ajoutez vos prestataires préférés pour accélérer vos prochaines réservations." icon="★" />
                    @endforelse
                </div>
            @else
                <x-empty-state title="Disponible avec Premium" message="Avec Premium, sélectionnez vos prestataires favoris." icon="⭐" />
            @endif
        </x-app-card>
    </div>
</div>
