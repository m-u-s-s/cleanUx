<div class="p-4 md:p-6 space-y-6">
    
    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">Administration</p>
            <h1 class="text-2xl md:text-3xl font-bold text-slate-900">Gestion des clients Premium</h1>
            <p class="text-sm text-slate-500 mt-1">
                Activez, suspendez ou désactivez les plans Premium de vos clients.
            </p>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-5 py-4">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filtres --}}
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Recherche</label>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Nom ou email..."
                       class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Plan</label>
                <select wire:model.live="filterPlan"
                        class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                    <option value="all">Tous</option>
                    <option value="standard">Standard</option>
                    <option value="premium">Premium</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Statut</label>
                <select wire:model.live="filterStatus"
                        class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                    <option value="all">Tous</option>
                    <option value="active">Actif</option>
                    <option value="inactive">Inactif</option>
                    <option value="past_due">Suspendu</option>
                    <option value="cancelled">Annulé</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Liste clients --}}
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100">
            <h2 class="text-lg font-bold text-slate-900">Clients</h2>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($clients as $client)
                <div class="p-6">
                    <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
                        <div class="space-y-3">
                            <div class="flex items-center gap-3 flex-wrap">
                                <h3 class="text-lg font-bold text-slate-900">{{ $client->name }}</h3>

                                @if($client->plan_type === 'premium')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">
                                        Premium
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700">
                                        Standard
                                    </span>
                                @endif

                                @if($client->plan_status === 'active')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700">
                                        Actif
                                    </span>
                                @elseif($client->plan_status === 'past_due')
                                    <span class="inline-flex items-center rounded-full bg-red-50 border border-red-200 px-3 py-1 text-xs font-semibold text-red-700">
                                        Suspendu
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700">
                                        {{ ucfirst($client->plan_status) }}
                                    </span>
                                @endif
                            </div>

                            <div class="text-sm text-slate-600 space-y-1">
                                <p><span class="font-semibold text-slate-800">Email :</span> {{ $client->email }}</p>
                                <p><span class="font-semibold text-slate-800">Début Premium :</span> {{ optional($client->premium_started_at)->format('d/m/Y') ?? '—' }}</p>
                                <p><span class="font-semibold text-slate-800">Renouvellement :</span> {{ optional($client->premium_renewal_at)->format('d/m/Y') ?? '—' }}</p>
                                <p><span class="font-semibold text-slate-800">Employés favoris :</span> {{ $client->favoriteEmployes->count() }}</p>
                            </div>

                            @if($client->favoriteEmployes->count())
                                <div class="flex flex-wrap gap-2 pt-1">
                                    @foreach($client->favoriteEmployes as $employe)
                                        <span class="inline-flex items-center rounded-full bg-sky-50 border border-sky-200 px-3 py-1 text-xs font-semibold text-sky-700">
                                            {{ $employe->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @if($client->plan_type !== 'premium')
                                <button type="button"
                                        wire:click="setPremium({{ $client->id }})"
                                        class="inline-flex items-center px-4 py-2.5 rounded-xl bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600 transition">
                                    Activer Premium
                                </button>
                            @endif

                            @if($client->plan_type === 'premium' && $client->plan_status === 'active')
                                <button type="button"
                                        wire:click="suspendPlan({{ $client->id }})"
                                        class="inline-flex items-center px-4 py-2.5 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-semibold hover:bg-red-100 transition">
                                    Suspendre
                                </button>

                                <button type="button"
                                        wire:click="setStandard({{ $client->id }})"
                                        class="inline-flex items-center px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                                    Repasser Standard
                                </button>
                            @endif

                            @if($client->plan_type === 'premium' && $client->plan_status === 'past_due')
                                <button type="button"
                                        wire:click="reactivatePlan({{ $client->id }})"
                                        class="inline-flex items-center px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition">
                                    Réactiver
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-6 text-sm text-slate-500 italic">
                    Aucun client trouvé.
                </div>
            @endforelse
        </div>

        @if(method_exists($clients, 'links'))
            <div class="px-6 py-4 border-t border-slate-100">
                {{ $clients->links() }}
            </div>
        @endif
    </div>
</div>