<div class="min-h-screen bg-slate-900 p-6">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-white">🗺️ Centre de dispatch</h1>
            <p class="text-sm text-slate-400">Assignez et suivez les missions de votre équipe</p>
        </div>
        <div class="flex items-center gap-3">
            <input wire:model.live="filterDate" type="date"
                class="rounded-xl border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-white outline-none focus:border-amber-500">
            <select wire:model.live="filterStatus"
                class="rounded-xl border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-200 outline-none focus:border-amber-500">
                <option value="">Tous statuts</option>
                <option value="pending">En attente</option>
                <option value="assigned">Assignée</option>
                <option value="in_progress">En cours</option>
                <option value="completed">Complétée</option>
                <option value="cancelled">Annulée</option>
            </select>
        </div>
    </div>

    {{-- Missions --}}
    <div class="space-y-3">
        @forelse ($missions as $mission)
            @php
                $statusConfig = [
                    'pending'     => ['bg' => 'border-slate-600 bg-slate-800', 'dot' => 'bg-slate-500', 'label' => 'En attente'],
                    'assigned'    => ['bg' => 'border-blue-700 bg-blue-900/20', 'dot' => 'bg-blue-400', 'label' => 'Assignée'],
                    'in_progress' => ['bg' => 'border-amber-700 bg-amber-900/20', 'dot' => 'bg-amber-400', 'label' => 'En cours'],
                    'completed'   => ['bg' => 'border-green-700 bg-green-900/20', 'dot' => 'bg-green-400', 'label' => 'Complétée'],
                    'cancelled'   => ['bg' => 'border-red-900 bg-red-900/10', 'dot' => 'bg-red-500', 'label' => 'Annulée'],
                ];
                $sc = $statusConfig[$mission->status ?? 'pending'] ?? $statusConfig['pending'];
            @endphp

            <div class="rounded-2xl border {{ $sc['bg'] }} p-4 transition">
                <div class="flex flex-wrap items-center gap-4">

                    {{-- Heure + statut --}}
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-3 w-3 flex-shrink-0 rounded-full {{ $sc['dot'] }}"></div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-white">
                                {{-- `scheduled_at` n'existe pas sur `missions` : l'heure s'affichait « – »
                                     pour TOUTE mission. Le créneau se lit sur `planned_start_at`, la
                                     colonne sur laquelle ce même écran filtre et trie déjà. Le défaut
                                     est resté invisible tant que le tableau n'avait aucune mission. --}}
                                {{ $mission->planned_start_at?->format('H:i') ?? '–' }}
                                <span class="ml-2 text-[10px] font-medium text-slate-400 uppercase">
                                    {{ $sc['label'] }}
                                </span>
                            </p>
                            <p class="truncate text-xs text-slate-400">
                                {{ $mission->bookingSite?->name ?? 'Site non défini' }} —
                                {{ $mission->bookingSite?->city }}
                            </p>
                        </div>
                    </div>

                    {{-- Prestataires assignés --}}
                    <div class="flex items-center gap-2">
                        @if (($mission->assignments ?? collect())->isNotEmpty())
                            <div class="flex -space-x-2">
                                @foreach (($mission->assignments ?? collect())->take(4) as $a)
                                    <img src="{{ $a->provider?->profile_photo_url }}"
                                         title="{{ $a->provider?->name }}"
                                         class="h-8 w-8 rounded-full border-2 border-slate-900 object-cover">
                                @endforeach
                            </div>
                        @else
                            <span class="text-xs text-slate-500 italic">Non assignée</span>
                        @endif
                    </div>

                    {{-- Bouton assigner --}}
                    <div class="ml-auto flex items-center gap-2">
                        @if (in_array($mission->status ?? 'pending', ['pending', 'assigned']))
                            <button wire:click="startAssign({{ $mission->id }})"
                                class="rounded-xl border border-amber-600 px-3 py-1.5 text-xs font-semibold text-amber-400 hover:bg-amber-900/20 transition">
                                {{ ($mission->assignments ?? collect())->isEmpty() ? '+ Assigner' : '↻ Réassigner' }}
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Panel d'assignation --}}
                @if ($assigningId === $mission->id)
                    <div class="mt-4 rounded-xl border border-slate-600 bg-slate-900 p-4">
                        <p class="mb-3 text-sm font-semibold text-white">Assigner à un membre de l'équipe</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 max-h-48 overflow-y-auto">
                            @foreach ($availableWorkers as $worker)
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border p-2 transition
                                    {{ $assigneeId == $worker->user_id
                                        ? 'border-amber-500 bg-amber-900/30'
                                        : 'border-slate-600 hover:border-slate-500' }}">
                                    <input type="radio" wire:model="assigneeId" value="{{ $worker->user_id }}" class="sr-only">
                                    <img src="{{ $worker->user?->profile_photo_url }}"
                                         class="h-8 w-8 flex-shrink-0 rounded-full object-cover border border-slate-600">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-white">{{ $worker->user?->name }}</p>
                                        <p class="text-[10px] text-slate-400">
                                            {{ $worker->role->label() }}
                                            {{--
                                                INDICATIF, JAMAIS BLOQUANT. Le bouton de confirmation
                                                reste actif : un répartiteur qui connaît son équipe
                                                passe outre pour de bonnes raisons — un échange entre
                                                collègues, une heure sup consentie. Le désactiver
                                                obligerait à prévoir un moyen de forcer, qui
                                                deviendrait le geste ordinaire.
                                            --}}
                                            @if (($disponibilites[$worker->user_id] ?? true) === false)
                                                · <span class="text-amber-400">déjà pris</span>
                                            @endif
                                        </p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button wire:click="cancelAssign"
                                class="flex-1 rounded-xl border border-slate-600 py-2 text-xs text-slate-300 hover:bg-slate-800">
                                Annuler
                            </button>
                            <button wire:click="confirmAssign"
                                :disabled="{{ is_null($assigneeId) ? 'true' : 'false' }}"
                                class="flex-1 rounded-xl bg-amber-600 py-2 text-xs font-bold text-white hover:bg-amber-700 disabled:opacity-50">
                                ✓ Confirmer comme responsable
                            </button>
                        </div>

                        {{--
                            LE RENFORT : une seconde personne SANS déloger le responsable.
                            `mission_assignments.role_on_mission` distingue `lead` de `helper`
                            depuis toujours ; rien ne s'en servait, et un grand nettoyage à deux —
                            le cas ordinaire d'une société — n'était pas représentable.
                        --}}
                        @if ($assigneeId)
                            <button wire:click="ajouterRenfort({{ $mission->id }}, {{ $assigneeId }})"
                                class="mt-2 w-full rounded-xl border border-slate-600 py-2 text-xs text-slate-300 hover:bg-slate-800">
                                + Ajouter en renfort (sans changer le responsable)
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center rounded-2xl border-2 border-dashed border-slate-700 py-16 text-center">
                <p class="text-4xl mb-3">🗺️</p>
                <p class="text-slate-400">Aucune mission pour cette période</p>
            </div>
        @endforelse
    </div>

    {{-- Mes contrats partenaires (lecture seule) --}}
    <div class="mt-10">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-black text-white">🤝 Mes contrats partenaires</h2>
            <span class="text-xs text-slate-500">Lecture seule — contrats où votre société est le partenaire</span>
        </div>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            @forelse ($partnerContracts as $contract)
                @php
                    $partnerStatusConfig = match ($contract->status) {
                        'active', 'signed', 'pilot' => ['dot' => 'bg-green-400', 'pill' => 'border-green-700 bg-green-900/20 text-green-300', 'label' => ucfirst((string) $contract->status)],
                        'draft'                       => ['dot' => 'bg-slate-500', 'pill' => 'border-slate-600 bg-slate-700/40 text-slate-300', 'label' => 'Brouillon'],
                        'expired', 'cancelled', 'terminated' => ['dot' => 'bg-red-500', 'pill' => 'border-red-900 bg-red-900/10 text-red-300', 'label' => ucfirst((string) $contract->status)],
                        default                       => ['dot' => 'bg-slate-500', 'pill' => 'border-slate-600 bg-slate-700/40 text-slate-300', 'label' => ucfirst((string) $contract->status)],
                    };
                    $contractMissionsCount = $missions->where('organization_contract_id', $contract->id)->count();
                    $clientName = $contract->organizationAccount?->name ?? 'Société non définie';
                @endphp

                <div class="rounded-2xl border border-slate-700 bg-slate-800 p-4 transition hover:border-slate-600">
                    {{-- En-tête : avatar initiale + client + statut --}}
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border border-slate-600 bg-slate-900 text-sm font-black text-slate-300">
                            {{ mb_strtoupper(mb_substr($clientName, 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-white">{{ $clientName }}</p>
                            <p class="truncate text-xs text-slate-400">
                                {{ $contract->contract_reference ?? 'Contrat #'.$contract->id }}
                            </p>
                        </div>
                        <span class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase {{ $partnerStatusConfig['pill'] }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $partnerStatusConfig['dot'] }}"></span>
                            {{ $partnerStatusConfig['label'] }}
                        </span>
                    </div>

                    {{-- Métriques --}}
                    <div class="mt-4 grid grid-cols-3 gap-2">
                        {{-- Grille / remise négociée --}}
                        <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-3">
                            <p class="text-[10px] uppercase tracking-wide text-slate-500">Tarification</p>
                            @if (($contract->rateCards ?? collect())->isNotEmpty())
                                <p class="mt-0.5 text-sm font-bold text-white">{{ $contract->rateCards->count() }}</p>
                                <p class="text-[10px] text-slate-400">grille(s) négociée(s)</p>
                            @elseif (($contract->negotiated_discount_percent ?? 0) > 0)
                                <p class="mt-0.5 text-sm font-bold text-amber-400">
                                    −{{ rtrim(rtrim(number_format((float) $contract->negotiated_discount_percent, 2, ',', ' '), '0'), ',') }}%
                                </p>
                                <p class="text-[10px] text-slate-400">remise négociée</p>
                            @else
                                <p class="mt-0.5 text-sm font-semibold text-slate-300">Standard</p>
                                <p class="text-[10px] text-slate-400">tarif catalogue</p>
                            @endif
                        </div>

                        {{-- Fenêtre de validité --}}
                        <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-3">
                            <p class="text-[10px] uppercase tracking-wide text-slate-500">Fenêtre</p>
                            <p class="mt-0.5 truncate text-xs font-semibold text-white">
                                {{ $contract->effective_from?->format('d/m/y') ?? '–' }}
                            </p>
                            <p class="truncate text-[10px] text-slate-400">
                                → {{ $contract->effective_to?->format('d/m/y') ?? '∞' }}
                            </p>
                        </div>

                        {{-- Obligations SLA entrantes (missions liées au contrat sur la période) --}}
                        <div class="rounded-xl border {{ $contractMissionsCount > 0 ? 'border-amber-700/60 bg-amber-900/10' : 'border-slate-700 bg-slate-900/60' }} p-3">
                            <p class="text-[10px] uppercase tracking-wide text-slate-500">SLA entrants</p>
                            <p class="mt-0.5 text-sm font-bold {{ $contractMissionsCount > 0 ? 'text-amber-400' : 'text-white' }}">
                                {{ $contractMissionsCount }}
                            </p>
                            <p class="text-[10px] text-slate-400">mission(s) période</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center rounded-2xl border-2 border-dashed border-slate-700 py-10 text-center lg:col-span-2">
                    <p class="text-3xl mb-2">🤝</p>
                    <p class="text-slate-400">Aucun contrat partenaire pour le moment</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
