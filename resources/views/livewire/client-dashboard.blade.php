<div class="space-y-6">
    <x-page-shell
        :eyebrow="__('Espace client')"
        :title="'Bonjour ' . \Illuminate\Support\Str::before(auth()->user()->name, ' ')"
        :subtitle="$isPremium
            ? __('Votre espace premium centralise vos rendez-vous, vos favoris, vos documents et vos services disponibles.')
            : __('Votre espace client centralise vos rendez-vous, vos documents, vos services et vos prochaines interventions.')">

        <x-slot name="actions">
            @if(Route::has('client.rendezvous.create'))
                <a href="{{ route('client.rendezvous.create') }}" class="cu-btn-primary">
                    {{ __('➕ Nouveau rendez-vous') }}
                </a>
            @endif

            @if(Route::has('client.rendezvous.index'))
                <a href="{{ route('client.rendezvous.index') }}" class="cu-btn-secondary">
                    {{ __('📅 Mes rendez-vous') }}
                </a>
            @endif

            @if(Route::has('client.finance'))
                <a href="{{ route('client.finance') }}" class="cu-btn-secondary">
                    {{ __('💳 Finance') }}
                </a>
            @endif

            @if(Route::has('client.subscriptions'))
                <a href="{{ route('client.subscriptions') }}" class="cu-btn-secondary">
                    {{ __('🔁 Abonnements') }}
                </a>
            @endif
        </x-slot>

        <div class="flex flex-wrap items-center gap-2">
            <span class="cu-chip {{ $isPremium ? '!border-amber-200 !bg-amber-50 !text-amber-700' : '' }}">
                {{ $isPremium ? __('★ Premium') : __('Standard') }}
            </span>

            @if($activeSubscription)
                <span class="cu-chip">{{ __('Abonnement actif') }}</span>
            @endif

            @if(($accountContext['type_label'] ?? null) === 'Entreprise')
                <span class="cu-chip">{{ __('Compte entreprise') }}</span>
            @endif

            @if($accountContext['primary_zone'] ?? false)
                <span class="cu-chip">
                    {{ __('Zone') }} : {{ $accountContext['primary_zone'] }}
                </span>
            @endif
        </div>
    </x-page-shell>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card :title="__('Total prestations')" :value="$statsClient['total'] ?? 0" tone="slate" icon="📦" />
        <x-kpi-card :title="__('À venir')" :value="$statsClient['avenir'] ?? 0" tone="blue" icon="📅" />
        <x-kpi-card :title="__('Terminées')" :value="$statsClient['termine'] ?? 0" tone="green" icon="✅" />
        <x-kpi-card :title="__('Feedbacks')" :value="$statsClient['feedbacks'] ?? 0" tone="amber" icon="⭐" />
    </div>

    <div wire:loading.flex class="cu-card items-center gap-4 p-6">
        <x-skeleton-block height="h-12" width="w-12" rounded="rounded-2xl" />
        <div class="flex-1 space-y-3">
            <x-skeleton-block width="w-40" />
            <x-skeleton-block height="h-5" width="w-80" />
            <x-skeleton-block width="w-56" />
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3" wire:loading.remove>
        <div class="space-y-6 xl:col-span-2">
            <x-app-card padding="p-6" :title="__('Prochain rendez-vous')" :subtitle="__('Votre prochaine intervention et les actions rapides disponibles.')">
                @if($prochainRendezVous)
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :status="$prochainRendezVous->status" />
                                <x-priority-badge :priority="$prochainRendezVous->priorite" />
                            </div>

                            <h3 class="mt-4 text-2xl font-black text-slate-900">
                                {{ $prochainRendezVous->service_display_name ?: __('Service non précisé') }}
                            </h3>

                            <p class="mt-2 text-sm text-slate-500">
                                {{ __('Référence') }} :
                                <span class="font-semibold text-slate-700">
                                    {{ $prochainRendezVous->booking_reference ?? '#' . $prochainRendezVous->id }}
                                </span>
                            </p>
                        </div>

                        <div class="rounded-3xl border border-blue-100 bg-blue-50 px-5 py-4 text-blue-900">
                            <p class="text-xs font-bold uppercase tracking-wide text-blue-600">{{ __('Date prévue') }}</p>
                            <p class="mt-1 text-lg font-black">
                                {{ optional($prochainRendezVous->date)->format('d/m/Y') ?? $prochainRendezVous->date }}
                            </p>
                            <p class="text-sm font-semibold">
                                {{ substr((string) $prochainRendezVous->heure, 0, 5) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="cu-card-muted p-4">
                            <p class="text-sm text-slate-500">{{ __('Employé') }}</p>
                            <p class="mt-1 font-bold text-slate-900">
                                {{ $prochainRendezVous->employe->name ?? __('À confirmer') }}
                            </p>
                        </div>

                        <div class="cu-card-muted p-4">
                            <p class="text-sm text-slate-500">{{ __('Adresse') }}</p>
                            <p class="mt-1 font-bold text-slate-900">
                                {{ $prochainRendezVous->adresse ?? '—' }}
                            </p>
                            <p class="text-sm text-slate-500">
                                {{ $prochainRendezVous->code_postal ?? '' }} {{ $prochainRendezVous->ville ?? '' }}
                            </p>
                        </div>

                        <div class="cu-card-muted p-4">
                            <p class="text-sm text-slate-500">{{ __('Zone') }}</p>
                            <p class="mt-1 font-bold text-slate-900">
                                {{ $prochainRendezVous->serviceZone->name ?? __('Non définie') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        @if(Route::has('client.rendezvous.index'))
                            <a href="{{ route('client.rendezvous.index') }}" class="cu-btn-primary">
                                {{ __('Voir le détail') }}
                            </a>
                        @endif

                        @if(!in_array($prochainRendezVous->status, ['en_route', 'sur_place', 'termine', 'refuse']))
                            <button type="button" wire:click="modifier({{ $prochainRendezVous->id }})" class="cu-btn-secondary">
                                {{ __('Modifier') }}
                            </button>

                            <button type="button" wire:click="annuler({{ $prochainRendezVous->id }})" class="cu-btn-danger">
                                {{ __('Annuler') }}
                            </button>
                        @endif
                    </div>

                    @if((int) $editRdvId === (int) $prochainRendezVous->id)
                        <form wire:submit.prevent="enregistrerModif" class="mt-6 rounded-3xl border border-blue-100 bg-blue-50 p-5">
                            <p class="text-sm font-black uppercase tracking-wide text-blue-700">
                                {{ __('Modifier ce rendez-vous') }}
                            </p>

                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-slate-700">{{ __('Nouvelle date') }}</label>
                                    <input type="date" wire:model.defer="editDate" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-slate-700">{{ __('Nouvelle heure') }}</label>
                                    <input type="time" wire:model.defer="editHeure" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm">
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="submit" class="cu-btn-primary">
                                    {{ __('Enregistrer') }}
                                </button>

                                <button type="button" wire:click="fermerEdition" class="cu-btn-secondary">
                                    {{ __('Fermer') }}
                                </button>
                            </div>
                        </form>
                    @endif
                @else
                    <x-empty-state
                        :title="__('Aucun rendez-vous à venir')"
                        :message="__('Planifiez une nouvelle prestation en quelques clics pour garder un suivi clair de vos interventions.')"
                        icon="📅">
                        @if(Route::has('client.rendezvous.create'))
                            <a href="{{ route('client.rendezvous.create') }}" class="cu-btn-primary">
                                {{ __('Réserver maintenant') }}
                            </a>
                        @endif
                    </x-empty-state>
                @endif
            </x-app-card>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="overflow-hidden rounded-[28px] bg-gradient-to-r from-slate-950 via-slate-800 to-slate-700 p-6 text-white shadow-sm">
                    <p class="text-sm text-slate-300">{{ __('Réservation rapide') }}</p>
                    <h3 class="mt-1 text-xl font-black">{{ __('Même service que la dernière fois') }}</h3>

                    @if($dernierRendezVous)
                        <div class="mt-4 space-y-2 text-sm text-slate-200">
                            <p><span class="font-semibold text-white">{{ __('Service') }} :</span> {{ $dernierRendezVous->service_display_name }}</p>
                            <p><span class="font-semibold text-white">{{ __('Adresse') }} :</span> {{ $dernierRendezVous->adresse ?? '—' }}, {{ $dernierRendezVous->ville ?? '—' }}</p>
                            <p><span class="font-semibold text-white">{{ __('Fréquence') }} :</span> {{ ucfirst(str_replace('_', ' ', $dernierRendezVous->frequence ?? '—')) }}</p>
                        </div>

                        @if(Route::has('client.rendezvous.create'))
                            <div class="mt-5">
                                <a href="{{ route('client.rendezvous.create', ['prefill' => 'last']) }}" class="inline-flex items-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-slate-100">
                                    🔁 {{ __('Reprendre une réservation similaire') }}
                                </a>
                            </div>
                        @endif
                    @else
                        <p class="mt-4 text-sm text-slate-300">
                            {{ __('Votre dernière prestation apparaîtra ici pour accélérer vos prochaines réservations.') }}
                        </p>
                    @endif
                </div>

                <x-app-card padding="p-6" :title="__('Adresses récentes')" :subtitle="__('Réutilisez rapidement une adresse déjà utilisée.')">
                    <div class="space-y-3">
                        @forelse($adressesRecentes as $adresse)
                            <div class="cu-list-item flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                                <div>
                                    <p class="font-semibold text-slate-800">{{ $adresse->adresse }}</p>
                                    <p class="text-sm text-slate-500">{{ $adresse->ville ?? '—' }} {{ $adresse->code_postal ?? '' }}</p>
                                </div>

                                @if(Route::has('client.rendezvous.create'))
                                    <a href="{{ route('client.rendezvous.create', ['adresse' => $adresse->adresse, 'ville' => $adresse->ville, 'code_postal' => $adresse->code_postal]) }}"
                                        class="cu-btn-secondary">
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

            <x-app-card padding="p-6" wire:loading.class="opacity-60">
                <x-section-header
                    :title="__('Mes prochaines interventions')"
                    :subtitle="__('Retrouvez vos prochains services planifiés.')"
                    :action-label="__('Voir tous mes rendez-vous')"
                    :action-href="Route::has('client.rendezvous.index') ? route('client.rendezvous.index') : '#'" />

                <div class="mt-5 space-y-4">
                    @forelse($avenir as $rdv)
                        <div class="cu-list-item">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <p class="text-lg font-semibold text-slate-900">
                                        {{ $rdv->service_display_name }}
                                    </p>

                                    <p class="mt-1 text-sm text-slate-600">
                                        📅 {{ optional($rdv->date)->format('d/m/Y') ?? $rdv->date }}
                                        à {{ substr((string) $rdv->heure, 0, 5) }}
                                    </p>

                                    <p class="text-sm text-slate-600">
                                        📍 {{ $rdv->adresse ?? __('Adresse non précisée') }}, {{ $rdv->ville ?? '—' }}
                                    </p>

                                    <p class="text-sm text-slate-600">
                                        🧑‍💼 {{ $rdv->employe->name ?? __('Employé à confirmer') }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <x-badge :status="$rdv->status" />
                                    <x-priority-badge :priority="$rdv->priorite" />
                                </div>
                            </div>

                            @if($rdv->mission?->report_path)
                                <div class="mt-4 border-t border-slate-100 pt-4">
                                    <a
                                        href="{{ asset('storage/'.$rdv->mission->report_path) }}"
                                        target="_blank"
                                        class="rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white">
                                        {{ __('Télécharger le rapport') }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    @empty
                        <x-empty-state title="Aucune intervention à venir" message="Dès qu’un prochain rendez-vous sera planifié, il apparaîtra ici." icon="🧹" />
                    @endforelse
                </div>

                @if(method_exists($avenir, 'links'))
                    <div class="mt-6">
                        {{ $avenir->links() }}
                    </div>
                @endif
            </x-app-card>
        </div>

        <div class="space-y-6">
            <x-app-card padding="p-6" :title="__('Profil & couverture')" :subtitle="__('Résumé de votre compte et de votre zone principale.')">
                <div class="space-y-3">
                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('Type de compte') }}</p>
                        <p class="mt-1 text-lg font-black text-slate-900">
                            {{ $accountContext['type_label'] ?? __('Standard') }}
                        </p>
                    </div>

                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('Zone principale') }}</p>
                        <p class="mt-1 text-lg font-black text-slate-900">
                            {{ $accountContext['primary_zone'] ?? __('Non définie') }}
                        </p>

                        @if(($accountContext['zone_count'] ?? 0) > 1)
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $accountContext['zone_count'] }} {{ __('zones couvertes') }}
                            </p>
                        @endif
                    </div>

                    @if($accountContext['organization_name'] ?? false)
                        <div class="cu-card-muted p-4">
                            <p class="text-sm text-slate-500">{{ __('Organisation') }}</p>
                            <p class="mt-1 text-lg font-black text-slate-900">
                                {{ $accountContext['organization_name'] }}
                            </p>
                        </div>
                    @endif
                </div>
            </x-app-card>

            @if($isPremium)
                <x-app-card padding="p-6" :title="__('Abonnement Premium')" :subtitle="__('Vos avantages et votre statut d’abonnement.')">
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">{{ __('Statut') }}</span>
                            <span class="font-semibold text-emerald-700">{{ __('Actif') }}</span>
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

                    <div class="mt-5 cu-card-muted p-4 !border-amber-100 !bg-amber-50">
                        <p class="text-sm font-semibold text-amber-800">{{ __('Vos avantages') }}</p>
                        <ul class="mt-2 space-y-2 text-sm text-amber-700">
                            <li>{{ __('• Choix des employés favoris') }}</li>
                            <li>{{ __('• Visibilité sur les disponibilités') }}</li>
                            <li>{{ __('• Expérience plus personnalisée') }}</li>
                        </ul>
                    </div>
                </x-app-card>
            @else
                <x-app-card padding="p-6" :title="__('Offre Premium')" :subtitle="__('Passez à une expérience plus personnalisée.')">
                    <ul class="space-y-2 text-sm text-slate-600">
                        <li>{{ __('• Choisissez vos employés favoris') }}</li>
                        <li>{{ __('• Consultez leurs disponibilités') }}</li>
                        <li>{{ __('• Réservez plus rapidement') }}</li>
                    </ul>

                    @if(Route::has('premium.offer'))
                        <a href="{{ route('premium.offer') }}" class="cu-btn-primary mt-5 !bg-amber-500 hover:!bg-amber-600">
                            {{ __('Découvrir Premium') }}
                        </a>
                    @endif
                </x-app-card>
            @endif

            <x-app-card padding="p-6" :title="__('Documents & finance')" :subtitle="__('Suivez vos devis, factures et reste à payer.')">
                <div class="grid grid-cols-2 gap-3">
                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('Devis') }}</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ $financeSnapshot['quotes_count'] ?? 0 }}</p>
                    </div>

                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('Factures') }}</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ $financeSnapshot['invoices_count'] ?? 0 }}</p>
                    </div>

                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('En retard') }}</p>
                        <p class="mt-1 text-2xl font-black text-rose-700">{{ $financeSnapshot['overdue_count'] ?? 0 }}</p>
                    </div>

                    <div class="cu-card-muted p-4">
                        <p class="text-sm text-slate-500">{{ __('À payer') }}</p>
                        <p class="mt-1 text-lg font-black text-emerald-700">
                            {{ number_format((float) ($financeSnapshot['outstanding_total'] ?? 0), 2, ',', ' ') }} €
                        </p>
                    </div>
                </div>

                @if(Route::has('client.finance'))
                    <div class="mt-5">
                        <a href="{{ route('client.finance') }}" class="cu-btn-primary">
                            {{ __('Voir mes documents') }}
                        </a>
                    </div>
                @endif
            </x-app-card>

            <x-app-card padding="p-6" :title="__('Services disponibles')" :subtitle="__('Services actifs selon votre zone.')">
                <div class="space-y-3">
                    @forelse($availableServices as $service)
                        <div class="cu-list-item flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $service['name'] }}</p>
                                <p class="text-sm text-slate-500">{{ $service['zone_name'] ?: __('Zone couverte') }}</p>
                            </div>

                            <div class="text-right">
                                @if(! is_null($service['base_price']))
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ number_format((float) $service['base_price'], 2, ',', ' ') }} €
                                    </p>
                                @endif

                                @if($service['requires_manual_validation'])
                                    <p class="text-xs text-amber-700">{{ __('Validation manuelle') }}</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <x-empty-state :title="__('Aucun service disponible')" :message="__('Les services disponibles selon votre zone apparaîtront ici.')" icon="🗺️" />
                    @endforelse
                </div>
            </x-app-card>

            <x-app-card padding="p-6" :title="__('Employés favoris')" :subtitle="__('Retrouvez vos favoris et réservez plus vite avec eux.')">
                @if($isPremium)
                    <div class="space-y-3">
                        @forelse($favoriteEmployes as $employe)
                            <div class="cu-list-item flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-800">{{ $employe->name }}</p>
                                    <p class="text-sm text-slate-500">{{ __('Employé favori') }}</p>
                                </div>

                                @if(Route::has('client.rendezvous.create'))
                                    <a href="{{ route('client.rendezvous.create', ['employe' => $employe->id]) }}" class="text-sm font-semibold text-sky-600 transition hover:text-sky-700">
                                        {{ __('Réserver') }}
                                    </a>
                                @endif
                            </div>
                        @empty
                            <x-empty-state title="Aucun employé favori" message="Ajoutez vos employés préférés pour accélérer vos prochaines réservations premium." icon="★" />
                        @endforelse
                    </div>
                @else
                    <x-empty-state title="Disponible avec Premium" message="Avec Premium, vous pouvez sélectionner vos employés favoris." icon="⭐" />
                @endif
            </x-app-card>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-4">
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                {{ __('Sécurité') }}
            </p>
            <h2 class="text-xl font-black text-slate-900">
                {{ __('Sessions actives') }}
            </h2>
        </div>

        <x-active-sessions />
    </div>
</div>
