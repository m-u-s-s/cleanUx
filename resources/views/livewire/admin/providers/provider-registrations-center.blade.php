{{--
    Approbation des inscriptions prestataires en libre-service.
    Le composant ne liste que les profils portant self_registered_at (voir son docblock).
--}}
<div class="p-6 space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Inscriptions prestataires</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Comptes créés depuis l'application mobile. Tant qu'ils ne sont pas approuvés, ils ne peuvent que
                constituer leur dossier d'inscription.
            </p>
        </div>

        <input
            type="search"
            wire:model.live.debounce.400ms="search"
            placeholder="Nom, email ou société…"
            class="w-72 rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800"
        />
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        @foreach ([
            'pending' => 'En attente',
            'approved' => 'Approuvés',
            'rejected' => 'Refusés',
            'all' => 'Tous',
        ] as $value => $label)
            <button
                type="button"
                wire:click="$set('filter', '{{ $value }}')"
                @class([
                    'rounded-full px-4 py-1.5 text-sm font-medium transition',
                    'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' => $filter === $value,
                    'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' => $filter !== $value,
                ])
            >
                {{ $label }}
                @isset($counts[$value])
                    <span class="ml-1 opacity-70">{{ $counts[$value] }}</span>
                @endisset
            </button>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th class="px-4 py-3">Prestataire</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Inscrit le</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3 text-right">Décision</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                @forelse ($registrations as $registration)
                    <tr wire:key="registration-{{ $registration->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900 dark:text-slate-100">
                                {{ $registration->user?->name ?? 'Compte supprimé' }}
                            </div>
                            <div class="text-sm text-slate-500">{{ $registration->user?->email }}</div>
                            @if ($registration->user?->phone)
                                <div class="text-xs text-slate-400">{{ $registration->user->phone }}</div>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            @if ($registration->organization)
                                <div class="font-medium text-indigo-700 dark:text-indigo-400">Société</div>
                                <div class="text-sm text-slate-600 dark:text-slate-300">{{ $registration->organization->name }}</div>
                                @if ($registration->organization->tva_number)
                                    <div class="text-xs text-slate-400">TVA {{ $registration->organization->tva_number }}</div>
                                @else
                                    <div class="text-xs text-amber-600">TVA non renseignée</div>
                                @endif
                            @else
                                <span class="font-medium text-amber-700 dark:text-amber-500">Indépendant</span>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                            {{ optional($registration->self_registered_at)->format('d/m/Y H:i') }}
                        </td>

                        <td class="px-4 py-3">
                            @php
                                $badge = match ($registration->status) {
                                    'active' => ['Approuvé', 'bg-emerald-100 text-emerald-800'],
                                    'rejected' => ['Refusé', 'bg-rose-100 text-rose-800'],
                                    default => ['En attente', 'bg-amber-100 text-amber-800'],
                                };
                            @endphp
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>
                        </td>

                        <td class="px-4 py-3 text-right">
                            @if ($registration->status === 'pending')
                                @php
                                    // L'approbation posait `status = active` sans rien vérifier :
                                    // l'administrateur décidait à l'aveugle. Il voit désormais
                                    // l'état réel du dossier avant de trancher.
                                    $dossier = $this->dossierFor($registration);
                                @endphp

                                <div class="mb-2 text-left text-xs">
                                    @php $autoReason = $registration->metadata['auto_review_reason'] ?? null; @endphp

                                    {{-- Un dossier renvoyé ici par le contrôle automatique n'est PAS refusé : il
                                         attend un regard humain. Le dire évite de traiter à l'aveugle un compte
                                         que le robot a seulement jugé douteux. --}}
                                    @if ($autoReason)
                                        <div class="mb-1 rounded bg-rose-50 px-2 py-1 font-medium text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                            À traiter manuellement — {{ $autoReason }}
                                        </div>
                                    @endif

                                    <div class="font-medium {{ $dossier['is_complete'] ? 'text-emerald-700' : 'text-amber-700' }}">
                                        Dossier {{ $dossier['journey']['done'] }}/{{ $dossier['journey']['total'] }}
                                        — {{ $dossier['is_complete'] ? 'complet' : 'incomplet' }}
                                    </div>

                                    @foreach ($dossier['blockers'] as $blocker)
                                        <div class="text-rose-700 dark:text-rose-400">• {{ $blocker }}</div>
                                    @endforeach

                                    @foreach ($dossier['warnings'] as $warning)
                                        <div class="text-slate-500 dark:text-slate-400">• {{ $warning }}</div>
                                    @endforeach
                                </div>

                                @unless ($dossier['is_complete'])
                                    {{-- Le passage en force reste possible, mais jamais silencieux :
                                         le motif est consigné dans les métadonnées du profil. --}}
                                    <input
                                        type="text"
                                        wire:model="overrideReason.{{ $registration->id }}"
                                        placeholder="Motif d'approbation malgré le dossier incomplet"
                                        aria-label="Motif d'approbation pour {{ $registration->user?->name }}"
                                        class="mb-2 w-full rounded-lg border border-amber-300 px-2 py-1 text-xs dark:border-amber-600 dark:bg-slate-800"
                                    />
                                @endunless

                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        wire:click="approve({{ $registration->id }})"
                                        wire:confirm="Approuver cette inscription ? Le prestataire aura accès à l'application."
                                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700"
                                    >
                                        Approuver
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="reject({{ $registration->id }})"
                                        wire:confirm="Refuser cette inscription ? Le compte restera bloqué."
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300"
                                    >
                                        Refuser
                                    </button>
                                </div>
                            @else
                                <span class="text-sm text-slate-400">Traitée</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                            Aucune inscription à afficher pour ce filtre.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $registrations->links() }}</div>
</div>
