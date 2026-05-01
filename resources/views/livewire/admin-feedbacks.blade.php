<div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-7xl space-y-8 px-4 pb-10 pt-6 sm:px-6 lg:px-8">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 text-white shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.35fr_0.75fr] lg:px-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-300">
                        Qualité & satisfaction
                    </p>

                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        Feedbacks clients
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200 sm:text-base">
                        Analysez les retours clients, détectez les notes faibles, répondez rapidement et exportez les données qualité.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button wire:click="exportPdf"
                                class="rounded-2xl bg-white px-4 py-2 text-sm font-black text-slate-900 transition hover:bg-slate-100">
                            📄 Export PDF
                        </button>

                        <button wire:click="exportCsv"
                                class="rounded-2xl border border-emerald-300/30 bg-emerald-400/10 px-4 py-2 text-sm font-bold text-emerald-100 transition hover:bg-emerald-400/20">
                            📥 Export CSV
                        </button>

                        <button wire:click="resetFilters"
                                class="rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/15">
                            Réinitialiser
                        </button>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                        Vue actuelle
                    </p>

                    <h2 class="mt-2 text-xl font-black text-white">
                        {{ $scopeLabel }}
                    </h2>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl bg-white/10 p-4">
                            <p class="text-xs text-slate-300">Satisfaction</p>
                            <p class="mt-1 text-2xl font-black text-white">
                                {{ $qualityStats['satisfaction_rate'] }}%
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/10 p-4">
                            <p class="text-xs text-slate-300">Moyenne</p>
                            <p class="mt-1 text-2xl font-black text-white">
                                {{ $qualityStats['average_note_label'] }}
                            </p>
                        </div>
                    </div>

                    <p class="mt-4 text-sm text-slate-200">
                        {{ $activeFiltersLabel }}
                    </p>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-kpi-card title="Feedbacks" :value="$qualityStats['total']" tone="blue" icon="💬" />
            <x-kpi-card title="Moyenne" :value="$qualityStats['average_note_label']" tone="amber" icon="⭐" />
            <x-kpi-card title="Sans réponse" :value="$qualityStats['unanswered']" tone="rose" icon="✍️" />
            <x-kpi-card title="Notes faibles" :value="$qualityStats['low_scores']" tone="red" icon="⚠️" />
            <x-kpi-card title="Répondus" :value="$qualityStats['answered']" tone="green" icon="✅" />
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                        Filtres qualité
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Affiner les feedbacks
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Les filtres impactent la liste, les KPIs et les exports PDF/CSV.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach($statusOptions as $value => $label)
                        <button wire:click="filterByStatus('{{ $value }}')"
                                class="rounded-full border px-3 py-1.5 text-xs font-black transition
                                {{ $status === $value ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="xl:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Recherche
                    </label>

                    <input type="text"
                           wire:model.live.debounce.350ms="search"
                           placeholder="Client, employé, service, ville, commentaire…"
                           class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Employé
                    </label>

                    <select wire:model.live="employe_id"
                            class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tous les employés</option>
                        @foreach($employes as $employe)
                            <option value="{{ $employe->id }}">{{ $employe->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Client
                    </label>

                    <select wire:model.live="client_id"
                            class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tous les clients</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Par page
                    </label>

                    <select wire:model.live="perPage"
                            class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="5">5</option>
                        <option value="8">8</option>
                        <option value="12">12</option>
                        <option value="20">20</option>
                    </select>
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Filtre actif
                    </p>

                    <p class="mt-1 text-sm font-bold text-slate-800">
                        {{ $activeFiltersLabel }}
                    </p>
                </div>

                <button wire:click="resetFilters" class="cu-btn-secondary">
                    Réinitialiser les filtres
                </button>
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                        Liste détaillée
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Retours clients
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Répondez directement depuis la carte du feedback. La réponse est sauvegardée automatiquement.
                    </p>
                </div>

                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                    {{ $feedbacks->total() }} résultat(s)
                </span>
            </div>

            <div class="space-y-5">
                @forelse($feedbacks as $feedback)
                    <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm transition hover:border-indigo-200 hover:bg-white hover:shadow-md">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-black text-amber-700 ring-1 ring-amber-200">
                                        ⭐ {{ $feedback->note }}/5
                                    </span>

                                    @if((int) $feedback->note <= 2)
                                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-black text-rose-700 ring-1 ring-rose-200">
                                            À surveiller
                                        </span>
                                    @endif

                                    @if(filled($feedback->reponse_admin))
                                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-200">
                                            Répondu
                                        </span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600 ring-1 ring-slate-200">
                                            Sans réponse
                                        </span>
                                    @endif
                                </div>

                                <h3 class="mt-3 text-lg font-black text-slate-900">
                                    {{ $feedback->client?->name ?? 'Client inconnu' }}
                                </h3>

                                <p class="mt-1 text-sm text-slate-600">
                                    Employé :
                                    <span class="font-semibold">
                                        {{ $feedback->rendezVous?->employe?->name ?? 'Non assigné' }}
                                    </span>
                                </p>

                                <p class="mt-1 text-sm text-slate-600">
                                    Service :
                                    <span class="font-semibold">
                                        {{ $feedback->rendezVous?->service_display_name ?? 'Service non précisé' }}
                                    </span>
                                </p>

                                <p class="mt-1 text-sm text-slate-500">
                                    {{ optional($feedback->created_at)->format('d/m/Y H:i') }}
                                    @if($feedback->rendezVous?->status)
                                        · Statut RDV : {{ $feedback->rendezVous->status }}
                                    @endif
                                </p>
                            </div>

                            <div class="shrink-0 text-right">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                    Feedback #{{ $feedback->id }}
                                </p>

                                @if($feedback->rendezVous?->serviceZone)
                                    <p class="mt-1 text-sm font-bold text-slate-700">
                                        {{ $feedback->rendezVous->serviceZone->name }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                Commentaire client
                            </p>

                            <p class="mt-2 text-sm leading-6 text-slate-700">
                                {{ $feedback->commentaire ?: 'Aucun commentaire écrit.' }}
                            </p>
                        </div>

                        <div class="mt-5">
                            <label class="mb-1 block text-sm font-bold text-slate-700">
                                Réponse admin
                            </label>

                            <textarea
                                wire:model.live.debounce.700ms="reponse.{{ $feedback->id }}"
                                rows="3"
                                placeholder="Écrivez une réponse courte, professionnelle et utile au client…"
                                class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>

                            <p class="mt-2 text-xs text-slate-500">
                                Sauvegarde automatique après modification.
                            </p>
                        </div>
                    </article>
                @empty
                    <x-empty-state
                        title="Aucun feedback trouvé"
                        message="Aucun retour ne correspond aux filtres actuels."
                        icon="💬" />
                @endforelse
            </div>

            <div class="mt-6">
                {{ $feedbacks->links() }}
            </div>
        </section>
    </div>
</div>
