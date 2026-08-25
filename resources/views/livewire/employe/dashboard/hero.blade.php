<section class="ui-page-header">
    <div class="grid gap-6 lg:grid-cols-[1.35fr_0.85fr]">
        <div>
            <p class="ui-page-eyebrow">Portail prestataire</p>

            <h1 class="ui-page-title">Ma journée terrain</h1>

            <p class="ui-page-subtitle">
                Vue rapide de vos missions, priorités, zones, actions terrain et historique récent.
                L'objectif : savoir quoi faire maintenant, où aller et quoi clôturer.
            </p>

            <div class="mt-5 flex flex-wrap gap-2">
                @if(Route::has('employe.missions'))
                    <a href="{{ route('employe.missions') }}" class="brio-btn-primary inline-flex items-center gap-2">
                        <x-ui.icon name="briefcase" class="w-4 h-4" />
                        <span>Toutes mes missions</span>
                    </a>
                @endif

                @if(Route::has('employe.planning'))
                    <a href="{{ route('employe.planning') }}" class="brio-btn-secondary inline-flex items-center gap-2">
                        <x-ui.icon name="calendar" class="w-4 h-4" />
                        <span>Mon planning</span>
                    </a>
                @endif

                @if(Route::has('employe.historique'))
                    <a href="{{ route('employe.historique') }}" class="brio-btn-secondary inline-flex items-center gap-2">
                        <x-ui.icon name="history" class="w-4 h-4" />
                        <span>Historique</span>
                    </a>
                @endif

                @if(Route::has('employe.incident'))
                    <a href="{{ route('employe.incident') }}" class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100 transition">
                        <x-ui.icon name="exclamation-triangle" class="w-4 h-4" />
                        <span>Signaler incident</span>
                    </a>
                @endif
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
            {{-- Aujourd'hui --}}
            <div class="rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50 to-white p-5 shadow-soft-sm">
                <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-brand-700">
                    <x-ui.icon name="calendar" class="w-3.5 h-3.5" />
                    Aujourd'hui
                </p>
                {{--
                    L'AVANCEMENT DU JOUR, EN ANNEAU.

                    Il tenait dans un trait horizontal de deux pixels, sous deux lignes de texte :
                    la seule chose que l'employe cherche en ouvrant cet ecran demandait de lire
                    avant de voir. L'anneau se lit a distance, telephone dans une main.

                    La part passe par une propriete personnalisee plutot que par une classe : une
                    valeur continue ne se decrit pas avec un jeu de classes fixes.
                --}}
                <div class="mt-3 flex items-center gap-4">
                    <div class="brio-jauge shrink-0"
                         style="--brio-jauge-part: {{ min(100, max(0, (int) $statsJour['progression'])) }}"
                         role="img"
                         aria-label="{{ $statsJour['progression'] }}% des missions du jour terminées">
                        <span class="brio-jauge-valeur" aria-hidden="true">{{ $statsJour['progression'] }}%</span>
                    </div>

                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-slate-900">
                            {{ $statsJour['total'] }} mission{{ $statsJour['total'] > 1 ? 's' : '' }}
                        </p>
                        <p class="mt-0.5 text-sm text-slate-600">
                            {{ $statsJour['heures_prevues'] }}h prévues
                        </p>
                        <p class="mt-0.5 text-sm text-slate-600">
                            {{ $statsJour['terminees'] }} terminée{{ $statsJour['terminees'] > 1 ? 's' : '' }}
                            · {{ $statsJour['a_faire'] }} à faire
                        </p>
                    </div>
                </div>
            </div>

            {{-- Paiement --}}
            <div class="rounded-2xl border {{ $paymentStatus['ready'] ? 'border-emerald-200 bg-emerald-50/50' : 'border-amber-200 bg-amber-50/50' }} p-5">
                <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide {{ $paymentStatus['ready'] ? 'text-emerald-700' : 'text-amber-700' }}">
                    <x-ui.icon name="{{ $paymentStatus['ready'] ? 'check-circle' : 'exclamation-triangle' }}" class="w-3.5 h-3.5" />
                    Paiement prestataire
                </p>

                <p class="mt-2 text-base font-bold text-slate-900">
                    {{ $paymentStatus['label'] }}
                </p>

                @if(! $paymentStatus['ready'] && Route::has('employe.stripe-connect.start'))
                    <a href="{{ route('employe.stripe-connect.start') }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-700 transition">
                        <x-ui.icon name="credit-card" class="w-3.5 h-3.5" />
                        Configurer mes paiements
                    </a>
                @else
                    <p class="mt-2 text-xs text-emerald-700">
                        Votre compte est prêt pour recevoir les reversements.
                    </p>
                @endif
            </div>
        </div>
    </div>
</section>
