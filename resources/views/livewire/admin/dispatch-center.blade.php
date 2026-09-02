{{--
    LE CENTRE DE RÉPARTITION.

    Il répond à une seule question, celle qui se pose quand une course n'aboutit pas : POURQUOI.
    Les quatre causes possibles — personne trouvé, personne en ligne, refus, silence — se
    ressemblent tant qu'on ne voit pas la chaîne d'offres, et l'exploitation conclut « pas assez de
    prestataires » là où le problème est un réglage de rayon.
--}}
<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Centre de répartition</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                Les recherches en cours, celles qui n’ont trouvé personne, et l’histoire complète de
                chacune.
            </p>
        </div>

        <div class="flex flex-wrap gap-2 text-xs text-slate-600 dark:text-slate-300">
            <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-white/5">
                TTL immédiat {{ $reglages['ttl_immediat'] }} s
            </span>
            <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-white/5">
                Vagues {{ (int) ($reglages['rayon_initial'] / 1000) }} → {{ (int) ($reglages['rayon_max'] / 1000) }} km
            </span>
            <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-white/5">
                Échéance {{ (int) ($reglages['echeance'] / 60) }} min
            </span>
            <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-white/5">
                Position &lt; {{ $reglages['fraicheur'] }} min
            </span>
        </div>
    </header>

    {{-- ─── Compteurs ──────────────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            ['label' => 'En recherche', 'valeur' => $this->compteurs['en_cours'], 'ton' => 'text-slate-900'],
            ['label' => 'Sans candidat', 'valeur' => $this->compteurs['sans_candidat'], 'ton' => 'text-rose-600'],
            ['label' => 'Acceptées', 'valeur' => $this->compteurs['acceptees'], 'ton' => 'text-emerald-600'],
            ['label' => 'Offres 24 h', 'valeur' => $this->compteurs['offres_24h'], 'ton' => 'text-slate-900'],
            ['label' => 'Refus 24 h', 'valeur' => $this->compteurs['refus_24h'], 'ton' => 'text-amber-600'],
            ['label' => 'Silences 24 h', 'valeur' => $this->compteurs['silences_24h'], 'ton' => 'text-amber-600'],
        ] as $tuile)
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-900">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $tuile['label'] }}</p>
                <p class="mt-1 text-2xl font-bold {{ $tuile['ton'] }} dark:text-white">{{ $tuile['valeur'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ─── Simulateur ─────────────────────────────────────────────────────────────────────── --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-900">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Simuler une répartition</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
            Pour cette réservation : qui serait candidat, et dans quel ordre. Le simulateur appelle le
            <strong>même</strong> service que le dispatch.
        </p>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <input type="number" wire:model="simulerBookingId" placeholder="ID de réservation"
                class="w-56 rounded-lg border-slate-300 text-sm dark:bg-slate-800 dark:text-white">
            <button type="button" wire:click="simuler"
                class="min-h-[40px] rounded-lg bg-slate-900 px-4 text-sm font-medium text-white dark:bg-white dark:text-slate-900">
                Simuler
            </button>
        </div>

        @if ($erreurSimulation)
            <p class="mt-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                {{ $erreurSimulation }}
            </p>
        @endif

        @if (is_array($simulation))
            @if ($simulation === [])
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                    Aucun candidat. En immédiat, cela veut dire : personne du bon métier, en ligne,
                    avec une position fraîche, dans le rayon maximal.
                </p>
            @else
                <table class="mt-3 w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="py-2">#</th>
                            <th>Prestataire</th>
                            <th>Distance</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($simulation as $rang => $candidat)
                            <tr>
                                <td class="py-2 text-slate-500">{{ $rang + 1 }}</td>
                                <td class="font-medium text-slate-900 dark:text-white">{{ $candidat['name'] }}</td>
                                <td class="text-slate-600 dark:text-slate-300">
                                    {{ $candidat['distance_km'] !== null ? $candidat['distance_km'].' km' : '—' }}
                                </td>
                                <td class="text-slate-600 dark:text-slate-300">{{ $candidat['score'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    </section>

    {{-- ─── Onglets ────────────────────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-2">
        @foreach ([
            'recherches' => 'Recherches',
            'sans_intervenant' => 'Sans intervenant',
            'poids' => 'Poids du score',
            'metriques' => 'Métriques prestataires',
        ] as $cle => $libelle)
            <button type="button" wire:click="$set('onglet', '{{ $cle }}')"
                class="min-h-[36px] rounded-lg px-3 text-sm {{ $onglet === $cle ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-slate-100 text-slate-700 dark:bg-white/5 dark:text-slate-300' }}">
                {{ $libelle }}
            </button>
        @endforeach
    </div>

    {{-- ─── Recherches ─────────────────────────────────────────────────────────────────────── --}}
    @if ($onglet === 'recherches')
    <section class="rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900">
        <div class="flex flex-wrap gap-2 border-b border-slate-100 p-4 dark:border-white/5">
            @foreach ([
                'searching' => 'En recherche',
                'expired' => 'Sans candidat',
                'accepted' => 'Acceptées',
                'all' => 'Toutes',
            ] as $cle => $libelle)
                <button type="button" wire:click="$set('filtre', '{{ $cle }}')"
                    class="min-h-[36px] rounded-lg px-3 text-sm {{ $filtre === $cle ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-slate-100 text-slate-700 dark:bg-white/5 dark:text-slate-300' }}">
                    {{ $libelle }}
                </button>
            @endforeach
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="p-4">Réservation</th>
                        <th>Métier</th>
                        <th>Vague</th>
                        <th>Rayon</th>
                        <th>Prévenus</th>
                        <th>État</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @forelse ($recherches as $recherche)
                        <tr>
                            <td class="p-4 font-medium text-slate-900 dark:text-white">
                                {{ $recherche->booking?->booking_reference ?? '—' }}
                                <span class="block text-xs text-slate-500">
                                    {{ $recherche->booking?->postal_code }} {{ $recherche->booking?->city }}
                                </span>
                            </td>
                            <td class="text-slate-600 dark:text-slate-300">{{ $recherche->trade?->name }}</td>
                            <td class="text-slate-600 dark:text-slate-300">{{ $recherche->wave }}</td>
                            <td class="text-slate-600 dark:text-slate-300">
                                {{ number_format($recherche->radius_m / 1000, 1, ',', ' ') }} km
                            </td>
                            <td class="text-slate-600 dark:text-slate-300">{{ $recherche->notified_count }}</td>
                            <td>
                                <span class="rounded-full px-2 py-0.5 text-xs
                                    {{ $recherche->status === 'expired' ? 'bg-rose-50 text-rose-700' : ($recherche->status === 'accepted' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700') }}">
                                    {{ \App\Support\Domain\AsapStatus::label($recherche->status) }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <button type="button" wire:click="ouvrir({{ $recherche->id }})"
                                    class="text-sm font-medium text-indigo-600 hover:underline">
                                    L’histoire
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-sm text-slate-500">
                                Aucune recherche dans cet état.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">{{ $recherches->links() }}</div>
    </section>
    @endif

    @if ($onglet === 'sans_intervenant')
        @include('livewire.admin.dispatch.sans-intervenant')
    @endif

    @if ($onglet === 'poids')
        @include('livewire.admin.dispatch.poids-du-score')
    @endif

    @if ($onglet === 'metriques')
        @include('livewire.admin.dispatch.metriques-prestataires')
    @endif

    {{-- ─── La chaîne d'offres ─────────────────────────────────────────────────────────────── --}}
    @if ($rechercheOuverte)
        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    Chaîne d’offres — recherche #{{ $rechercheOuverte }}
                </h2>
                <button type="button" wire:click="fermer" class="text-sm text-slate-500 hover:underline">Fermer</button>
            </div>

            @if ($chaine === [])
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                    Aucune offre n’a été émise : aucun candidat ne remplissait les conditions —
                    métier déclaré, zone, en ligne avec une position fraîche, profil vérifié.
                </p>
            @else
                <ol class="mt-4 space-y-2">
                    @foreach ($chaine as $rang => $offre)
                        <li class="rounded-xl border border-slate-100 p-3 text-sm dark:border-white/5">
                            <span class="font-medium text-slate-900 dark:text-white">
                                {{ $rang + 1 }}. {{ $offre['provider'] ?? '—' }}
                            </span>
                            <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-white/5 dark:text-slate-300">
                                {{ $offre['statut'] }}
                            </span>
                            <span class="ml-2 text-xs text-slate-500">
                                envoyée {{ $offre['envoyee_a'] }} · expire {{ $offre['expire_a'] }}
                                @if ($offre['repondue_en'] !== null)
                                    · répondu en {{ $offre['repondue_en'] }} s
                                @endif
                            </span>
                            @if ($offre['motif'])
                                <p class="mt-1 text-xs text-slate-500">{{ $offre['motif'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endif
</div>
