<div class="rounded-3xl border bg-white p-6 space-y-6" wire:poll.30s="refreshAlerts">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Centre d’alertes</h1>
            <p class="text-sm text-slate-500">Surveillance opérationnelle en temps réel</p>
        </div>

        <button wire:click="refreshAlerts" class="rounded-xl bg-slate-900 px-4 py-2 text-white">
            Actualiser
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-red-50 border border-red-100 p-4">
            <p class="text-sm text-red-600">Missions en retard</p>
            <p class="text-3xl font-bold text-red-700">{{ count($alerts['late_missions'] ?? []) }}</p>
        </div>

        <div class="rounded-2xl bg-amber-50 border border-amber-100 p-4">
            <p class="text-sm text-amber-600">Départ bientôt</p>
            <p class="text-3xl font-bold text-amber-700">{{ count($alerts['not_started_soon'] ?? []) }}</p>
        </div>

        <div class="rounded-2xl bg-blue-50 border border-blue-100 p-4">
            <p class="text-sm text-blue-600">Tracking coupé</p>
            <p class="text-3xl font-bold text-blue-700">{{ count($alerts['tracking_inactive'] ?? []) }}</p>
        </div>

        <div class="rounded-2xl bg-purple-50 border border-purple-100 p-4">
            <p class="text-sm text-purple-600">Paiement à capturer</p>
            <p class="text-3xl font-bold text-purple-700">{{ count($alerts['payment_not_captured'] ?? []) }}</p>
        </div>
    </div>

    {{--
        LA DISPONIBILITÉ — l'amont, qu'aucune alerte ne regardait.

        Les quatre indicateurs ci-dessus surveillent des missions qui EXISTENT déjà : retard,
        non-démarrage, suivi coupé, paiement. Un prestataire injoignable à la planification, lui,
        ne produit aucune mission — donc aucun retard, donc aucun signal. Son silence passait pour
        du calme.
    --}}
    <div>
        <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Disponibilité des prestataires</h3>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @php $sansDispo = $alerts['providers_without_availability'] ?? collect(); @endphp
            <a href="{{ route('admin.availability.center') }}"
               class="rounded-2xl border p-4 transition {{ count($sansDispo) > 0 ? 'border-red-200 bg-red-50 hover:bg-red-100' : 'border-emerald-100 bg-emerald-50' }}">
                <p class="text-sm {{ count($sansDispo) > 0 ? 'text-red-600' : 'text-emerald-600' }}">Aucune disponibilité</p>
                <p class="text-3xl font-bold {{ count($sansDispo) > 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ count($sansDispo) }}</p>
                <p class="mt-1 text-xs text-slate-500">injoignables à la planification</p>
            </a>

            @php $semaineFermee = $alerts['providers_fully_closed_week'] ?? collect(); @endphp
            <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
                <p class="text-sm text-amber-600">Semaine entièrement fermée</p>
                <p class="text-3xl font-bold text-amber-700">{{ count($semaineFermee) }}</p>
                <p class="mt-1 text-xs text-slate-500">configurés, mais absents les 7 prochains jours</p>
            </div>

            @php $fermetures = $alerts['providers_closing_spree'] ?? collect(); @endphp
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm text-slate-600">Fermetures nombreuses</p>
                <p class="text-3xl font-bold text-slate-700">{{ count($fermetures) }}</p>
                <p class="mt-1 text-xs text-slate-500">15 jours fermés ou plus sur 30</p>
            </div>
        </div>

        {{-- Le chiffre appelle un nom : sans lui, l'alerte ne dit pas quoi faire. --}}
        @if(count($sansDispo) > 0)
            <ul class="mt-3 space-y-1">
                @foreach($sansDispo as $presta)
                    <li class="text-sm">
                        <a href="{{ route('admin.availability.provider', $presta) }}" class="text-indigo-600 hover:underline">
                            {{ $presta->name }}
                        </a>
                        <span class="text-slate-400">— {{ $presta->email }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>