{{--
    LE CENTRE DE VÉRIFICATION FACIALE.

    Direction : sobre, dense, et LISIBLE EN UN COUP D'ŒIL. Un écran de sécurité se lit debout, à
    huit heures du matin, quand quelque chose vient de tomber. La couleur n'est donc jamais
    décorative : le rose dit « bloqué », l'ambre « à regarder », le vert « en règle ». Rien d'autre
    n'est coloré.

    Les composants viennent tous du design system existant (`x-page-shell`, `x-kpi-card`,
    `x-app-card`, `x-table-shell`, `x-empty-state`, `x-badge`) et la couche `.brio-*` : un écran
    d'administration qui invente sa propre palette est un écran qu'on reconnaît immédiatement comme
    rapporté, et c'est exactement le reproche fait à l'écran des feature flags.
--}}
<div class="space-y-6" wire:key="face-check-center">

    <x-page-shell
        eyebrow="Sécurité"
        title="Vérification faciale"
        subtitle="Enrôlement des visages, contrôles aléatoires avant intervention, appariement avec la pièce d'identité et revue manuelle.">
        <x-slot:actions>
            @if($moduleDeclare)
                <span @class([
                    'brio-inline-stat',
                    'text-emerald-700 border-emerald-200 bg-emerald-50' => $moduleActif,
                    'text-slate-500' => ! $moduleActif,
                ])>
                    <span class="relative flex h-2 w-2">
                        <span @class([
                            'relative inline-flex h-2 w-2 rounded-full',
                            'bg-emerald-500' => $moduleActif,
                            'bg-slate-300' => ! $moduleActif,
                        ])></span>
                    </span>
                    {{ $moduleActif ? 'Module actif' : 'Module éteint' }}
                </span>

                <span class="brio-inline-stat text-slate-500">
                    {{ count($zonesCouvertes) }} {{ Str::plural('zone', count($zonesCouvertes)) }} couverte{{ count($zonesCouvertes) > 1 ? 's' : '' }}
                </span>
            @endif

            <a href="{{ route('admin.modules') }}" class="brio-inline-stat text-slate-500 hover:text-slate-900">
                Audience &amp; zones →
            </a>
        </x-slot:actions>
    </x-page-shell>

    @unless($moduleDeclare)
        <x-app-card>
            <p class="text-sm text-rose-700">
                Le module <code>security.face_check</code> n'existe pas en base : lancez
                <code>php artisan db:seed --class=PlatformModuleSeeder</code>. Tant qu'il est absent,
                aucun prestataire n'est soumis au contrôle.
            </p>
        </x-app-card>
    @endunless

    {{-- ── Les chiffres qui décident de la journée ───────────────────────── --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <x-kpi-card title="À revoir" :value="$kpis['a_revoir']" tone="amber" icon="👁️" hint="dossiers en attente d'un œil" />
        <x-kpi-card title="Bloqués" :value="$kpis['bloques']" tone="rose" icon="⛔" hint="ne peuvent plus intervenir" />
        <x-kpi-card title="Fraudes possibles" :value="$kpis['fraudes_possibles']" tone="red" icon="🚨" hint="incidents critiques ouverts" />
        <x-kpi-card title="Incidents ouverts" :value="$kpis['incidents_ouverts']" tone="orange" icon="🛠️" hint="pannes signalées comprises" />
        <x-kpi-card title="Contrôles 24 h" :value="$kpis['controles_24h']" tone="blue" icon="📸" />
        <x-kpi-card title="Échecs 24 h" :value="$kpis['echecs_24h']" tone="slate" icon="✖️" />
    </div>

    {{-- ── Onglets ────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-2">
        @foreach ([
            'revue' => 'À revoir',
            'incidents' => 'Incidents',
            'historique' => 'Historique',
            'reglages' => 'Réglages',
        ] as $cle => $libelle)
            <button type="button"
                    wire:click="$set('tab', '{{ $cle }}')"
                    @class([
                        'rounded-full px-4 py-2 text-sm font-semibold transition',
                        'bg-slate-900 text-white shadow-sm' => $tab === $cle,
                        'bg-white text-slate-600 border border-slate-200 hover:text-slate-900' => $tab !== $cle,
                    ])>
                {{ $libelle }}
            </button>
        @endforeach

        @if($tab !== 'reglages')
            <div class="ml-auto flex items-center gap-2">
                @if($tab === 'incidents')
                    <select wire:model.live="filtreSeverite" class="rounded-xl border-slate-200 text-sm">
                        <option value="">Toutes gravités</option>
                        <option value="critical">Critique</option>
                        <option value="warning">Avertissement</option>
                        <option value="info">Information</option>
                    </select>
                @endif
                <input type="search" wire:model.live.debounce.400ms="search"
                       placeholder="Nom ou e-mail…"
                       class="w-56 rounded-xl border-slate-200 text-sm" />
            </div>
        @endif
    </div>

    {{-- ── À REVOIR ───────────────────────────────────────────────────────── --}}
    @if($tab === 'revue')
        <x-app-card padding="p-0">
            @if($items->isEmpty())
                <div class="p-6">
                    <x-empty-state title="Rien à revoir" message="Aucun dossier n'attend de décision humaine." icon="✅" />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="brio-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left">Prestataire</th>
                                <th class="px-4 py-3 text-left">État</th>
                                <th class="px-4 py-3 text-left">Pièce d'identité</th>
                                <th class="px-4 py-3 text-left">Échecs</th>
                                <th class="px-4 py-3 text-right">Décision</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($items as $ligne)
                            <tr wire:key="profil-{{ $ligne->id }}">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-900">{{ $ligne->user?->name ?? '—' }}</div>
                                    <div class="text-xs text-slate-500">{{ $ligne->user?->email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($ligne->isBlocked())
                                        <span class="brio-inline-stat border-rose-200 bg-rose-50 text-rose-700">Bloqué · {{ $ligne->block_reason }}</span>
                                    @elseif($ligne->status === \App\Models\ProviderFaceProfile::STATUS_PENDING)
                                        <span class="brio-inline-stat border-amber-200 bg-amber-50 text-amber-700">Jamais enrôlé</span>
                                    @else
                                        <span class="brio-inline-stat border-emerald-200 bg-emerald-50 text-emerald-700">Enrôlé</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @php $verdict = $ligne->id_match_status; @endphp
                                    <span @class([
                                        'brio-inline-stat',
                                        'border-emerald-200 bg-emerald-50 text-emerald-700' => in_array($verdict, ['match', 'manual_override'], true),
                                        'border-rose-200 bg-rose-50 text-rose-700' => $verdict === 'mismatch',
                                        'border-amber-200 bg-amber-50 text-amber-700' => $verdict === 'inconclusive',
                                        'text-slate-500' => $verdict === 'pending',
                                    ])>
                                        {{ [
                                            'match' => 'Correspond',
                                            'manual_override' => 'Validé à la main',
                                            'mismatch' => 'Ne correspond pas',
                                            'inconclusive' => 'Non concluant',
                                        ][$verdict] ?? 'En attente' }}
                                        @if($ligne->id_match_score !== null)
                                            <span class="ml-1 opacity-70">{{ number_format((float) $ligne->id_match_score, 1) }} %</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="px-4 py-3 tabular-nums">{{ $ligne->consecutive_failures }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" wire:click="ouvrirLeProfil({{ $ligne->id }})"
                                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-900 hover:text-slate-900">
                                        Examiner
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 p-4">{{ $items->links() }}</div>
            @endif
        </x-app-card>
    @endif

    {{-- ── INCIDENTS ──────────────────────────────────────────────────────── --}}
    @if($tab === 'incidents')
        <x-app-card padding="p-0">
            @if($items->isEmpty())
                <div class="p-6">
                    <x-empty-state title="Aucun incident ouvert" message="Ni panne signalée, ni soupçon de fraude." icon="🕊️" />
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($items as $incident)
                        <div class="flex flex-wrap items-start gap-4 p-4" wire:key="incident-{{ $incident->id }}">
                            <span @class([
                                'mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-full',
                                'bg-rose-500' => $incident->severity === 'critical',
                                'bg-amber-500' => $incident->severity === 'warning',
                                'bg-slate-300' => $incident->severity === 'info',
                            ])></span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-slate-900">{{ $incident->user?->name ?? '—' }}</span>
                                    <span class="brio-inline-stat text-slate-500">{{ [
                                        'provider_report' => 'Panne signalée',
                                        'repeated_abandon' => 'Abandons répétés',
                                        'repeated_failure' => 'Échecs répétés',
                                        'liveness_fail' => 'Vivacité en échec',
                                        'id_mismatch' => 'Pièce d’identité',
                                    ][$incident->type] ?? $incident->type }}</span>
                                    @if($incident->occurrence_count > 1)
                                        <span class="brio-inline-stat text-slate-500">×{{ $incident->occurrence_count }}</span>
                                    @endif
                                    @if($incident->status === 'acknowledged')
                                        <span class="brio-inline-stat border-blue-200 bg-blue-50 text-brand-700">Pris en charge</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-slate-600">{{ $incident->message }}</p>
                                @if(filled($incident->diagnostics))
                                    <p class="mt-1 text-xs text-slate-400">
                                        @foreach($incident->diagnostics as $cle => $valeur)
                                            @if(is_scalar($valeur))<span class="mr-3">{{ $cle }} : {{ $valeur }}</span>@endif
                                        @endforeach
                                    </p>
                                @endif
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-2">
                                @if($incident->status === 'open')
                                    <button type="button" wire:click="accuserReception({{ $incident->id }})"
                                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-900">
                                        Prendre en charge
                                    </button>
                                @endif
                                <button type="button" wire:click="cloreLIncident({{ $incident->id }}, 'fixed')"
                                        class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">
                                    Résolu
                                </button>
                                <button type="button" wire:click="cloreLIncident({{ $incident->id }}, 'fraud_confirmed')"
                                        class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-500">
                                    Fraude confirmée
                                </button>
                                <button type="button" wire:click="cloreLIncident({{ $incident->id }}, 'dismissed')"
                                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900">
                                    Écarter
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-slate-100 p-4">{{ $items->links() }}</div>
            @endif
        </x-app-card>
    @endif

    {{-- ── HISTORIQUE ─────────────────────────────────────────────────────── --}}
    @if($tab === 'historique')
        <x-app-card padding="p-0">
            @if($items->isEmpty())
                <div class="p-6">
                    <x-empty-state title="Aucun contrôle" message="Aucun contrôle facial n'a encore été demandé." icon="📭" />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="brio-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left">Prestataire</th>
                                <th class="px-4 py-3 text-left">Demandé</th>
                                <th class="px-4 py-3 text-left">Motif</th>
                                <th class="px-4 py-3 text-left">Verdict</th>
                                <th class="px-4 py-3 text-left">Score</th>
                                <th class="px-4 py-3 text-left">Vivacité</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($items as $controle)
                            <tr wire:key="controle-{{ $controle->id }}">
                                <td class="px-4 py-3">{{ $controle->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $controle->requested_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ [
                                    'enrollment' => 'Enrôlement',
                                    'interval' => 'Cadence',
                                    'risk_device' => 'Nouvel appareil',
                                    'risk_failures' => 'Échecs récents',
                                    'risk_abandons' => 'Abandons récents',
                                    'admin_forced' => 'Forcé par un admin',
                                ][$controle->triggered_by] ?? $controle->triggered_by }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'brio-inline-stat',
                                        'border-emerald-200 bg-emerald-50 text-emerald-700' => $controle->status === 'passed',
                                        'border-rose-200 bg-rose-50 text-rose-700' => $controle->status === 'failed',
                                        'text-slate-500' => ! in_array($controle->status, ['passed', 'failed'], true),
                                    ])>{{ [
                                        'pending' => 'En cours',
                                        'passed' => 'Réussi',
                                        'failed' => 'Échoué',
                                        'abandoned' => 'Abandonné',
                                        'expired' => 'Expiré',
                                        'error' => 'Erreur',
                                    ][$controle->status] ?? $controle->status }}</span>
                                    @if($controle->failure_reason)
                                        <span class="ml-2 text-xs text-slate-400">{{ $controle->failure_reason }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 tabular-nums">{{ $controle->score !== null ? number_format((float) $controle->score, 1).' %' : '—' }}</td>
                                <td class="px-4 py-3">{{ [
                                    'pass' => '✅',
                                    'fail' => '⚠️',
                                ][$controle->liveness_result] ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 p-4">{{ $items->links() }}</div>
            @endif
        </x-app-card>
    @endif

    {{-- ── RÉGLAGES ───────────────────────────────────────────────────────── --}}
    @if($tab === 'reglages')
        <form wire:submit.prevent="enregistrerLesReglages" class="space-y-4">
            <x-app-card title="Le module" subtitle="L'audience par zone se règle sur la page des modules ; ici on décide du comportement.">
                <label class="inline-flex items-center gap-3">
                    <input type="checkbox" wire:model="moduleActif" class="rounded text-emerald-600" />
                    <span class="text-sm font-semibold text-slate-800">Contrôle facial actif</span>
                </label>
                <p class="mt-2 text-xs text-slate-500">
                    Un prestataire n'est soumis que si TOUT est vrai : module actif, sa zone dans l'audience,
                    et au moins un de ses métiers coche « vérification faciale » dans le catalogue.
                </p>
            </x-app-card>

            <x-app-card title="Cadence" subtitle="Le moment exact est tiré au sort par le serveur dans cette fenêtre, et n'est jamais communiqué au prestataire.">
                <div class="brio-filter-grid">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Au plus un contrôle toutes les (heures)</span>
                        <input type="number" wire:model="minHours" min="1" max="720" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                        @error('minHours') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Au moins un contrôle toutes les (heures)</span>
                        <input type="number" wire:model="maxHours" min="1" max="720" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                        @error('maxHours') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                </div>
            </x-app-card>

            <x-app-card title="Décision" subtitle="Ce qui fait passer, ce qui fait échouer, et à partir de quand on bloque.">
                <div class="brio-filter-grid">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Similarité minimale (%)</span>
                        <input type="number" step="0.5" wire:model="matchThreshold" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Similarité pièce d'identité (%)</span>
                        <input type="number" step="0.5" wire:model="idMatchThreshold" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Essais par contrôle</span>
                        <input type="number" wire:model="maxAttempts" min="1" max="10" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contrôles échoués avant blocage</span>
                        <input type="number" wire:model="failureThreshold" min="1" max="10" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                    </label>
                </div>
                <label class="mt-4 inline-flex items-center gap-3">
                    <input type="checkbox" wire:model="livenessRequired" class="rounded text-rose-600" />
                    <span class="text-sm font-semibold text-slate-800">Exiger la détection de vivacité</span>
                </label>
                <p class="mt-1 text-xs text-slate-500">
                    Sans elle, la photo d'une photo passe le contrôle. À ne désactiver que pour un diagnostic.
                </p>
            </x-app-card>

            <x-app-card title="Alertes" subtitle="On n'alerte pas au premier abandon : réseau coupé, batterie vide et évitement produisent le même état.">
                <div class="brio-filter-grid">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Abandons avant alerte</span>
                        <input type="number" wire:model="abandonThreshold" min="1" max="50" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fenêtre d'observation (jours)</span>
                        <input type="number" wire:model="abandonWindowDays" min="1" max="90" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Abandons = fraude possible</span>
                        <input type="number" wire:model="abandonFraudThreshold" min="1" max="50" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                        @error('abandonFraudThreshold') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                </div>
            </x-app-card>

            <x-app-card title="Conservation" subtitle="Le visage de référence vit tant que le compte vit ; les selfies de contrôle sont éphémères.">
                <label class="block max-w-xs">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Selfies de contrôle conservés (jours)</span>
                    <input type="number" wire:model="selfieRetentionDays" min="1" max="365" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                </label>
                <p class="mt-2 text-xs text-slate-500">
                    Passé ce délai, le fichier est effacé du disque : seuls le verdict et le score subsistent.
                    Une donnée biométrique relève de l'article 9 du RGPD — la durée doit rester la plus courte possible.
                </p>
            </x-app-card>

            <div class="flex justify-end">
                <button type="submit" class="brio-btn-primary">Enregistrer les réglages</button>
            </div>
        </form>
    @endif

    {{-- ── LE DOSSIER OUVERT ──────────────────────────────────────────────── --}}
    @if($profil)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-0 backdrop-blur-sm sm:items-center sm:p-6"
             wire:key="dossier-{{ $profil->id }}">
            <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-t-3xl bg-white p-6 shadow-2xl sm:rounded-3xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="brio-eyebrow">Dossier</p>
                        <h3 class="mt-1 text-xl font-black tracking-tight text-slate-900">{{ $profil->user?->name ?? '—' }}</h3>
                        <p class="text-sm text-slate-500">{{ $profil->user?->email }}</p>
                    </div>
                    <button type="button" wire:click="fermerLeProfil" class="text-slate-400 hover:text-slate-900">✕</button>
                </div>

                {{-- La comparaison à l'œil : c'est ce que l'admin est venu faire. --}}
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="brio-card-muted p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Visage de référence</p>
                        @if($this->urlDeLaReference($profil))
                            <img src="{{ $this->urlDeLaReference($profil) }}" alt="Visage de référence"
                                 class="mt-3 aspect-square w-full rounded-2xl object-cover" />
                        @else
                            <p class="mt-3 text-sm text-slate-400">Aucun visage enregistré.</p>
                        @endif
                        <p class="mt-2 text-xs text-slate-400">
                            Enrôlé {{ $profil->captured_at?->diffForHumans() ?? '—' }} ·
                            consentement v{{ $profil->consent_version ?? '—' }}
                        </p>
                    </div>

                    <div class="brio-card-muted p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dernier selfie de contrôle</p>
                        @php $dernier = $derniersControles->first(fn ($c) => $c->selfie_path !== null); @endphp
                        @if($dernier && $this->urlDuSelfie($dernier))
                            <img src="{{ $this->urlDuSelfie($dernier) }}" alt="Selfie de contrôle"
                                 class="mt-3 aspect-square w-full rounded-2xl object-cover" />
                            <p class="mt-2 text-xs text-slate-400">
                                {{ $dernier->requested_at?->diffForHumans() }} ·
                                score {{ $dernier->score !== null ? number_format((float) $dernier->score, 1).' %' : '—' }}
                            </p>
                        @else
                            <p class="mt-3 text-sm text-slate-400">Aucun selfie disponible (jamais pris, ou purgé par la rétention).</p>
                        @endif
                    </div>
                </div>

                {{-- Les dix derniers contrôles : la forme du dossier se lit dans la répétition. --}}
                <div class="mt-6">
                    <p class="brio-section-title text-base">Dix derniers contrôles</p>
                    <div class="mt-2 divide-y divide-slate-100 text-sm">
                        @forelse($derniersControles as $c)
                            <div class="flex items-center justify-between gap-3 py-2">
                                <span class="text-slate-500">{{ $c->requested_at?->format('d/m H:i') }}</span>
                                <span class="text-slate-500">{{ $c->triggered_by }}</span>
                                <span @class([
                                    'font-semibold',
                                    'text-emerald-600' => $c->status === 'passed',
                                    'text-rose-600' => $c->status === 'failed',
                                    'text-slate-400' => ! in_array($c->status, ['passed', 'failed'], true),
                                ])>{{ $c->status }}</span>
                                <span class="tabular-nums text-slate-500">{{ $c->score !== null ? number_format((float) $c->score, 1).' %' : '—' }}</span>
                            </div>
                        @empty
                            <p class="py-3 text-slate-400">Aucun contrôle enregistré.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Les décisions. Séparées visuellement de la lecture : on ne clique pas par erreur. --}}
                <div class="mt-6 flex flex-wrap gap-2 border-t border-slate-100 pt-5">
                    @if($profil->isBlocked())
                        <button type="button" wire:click="leverLeBlocage({{ $profil->id }})"
                                class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                            Lever le blocage
                        </button>
                    @else
                        <button type="button" wire:click="bloquer({{ $profil->id }})"
                                class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500">
                            Bloquer
                        </button>
                    @endif

                    <button type="button" wire:click="validerLAppariement({{ $profil->id }})"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-slate-900">
                        C'est bien la même personne
                    </button>
                    <button type="button" wire:click="refuserLAppariement({{ $profil->id }})"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-rose-500 hover:text-rose-600">
                        Ce n'est pas la même personne
                    </button>
                    <button type="button" wire:click="forcerUnControle({{ $profil->id }})"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-slate-900">
                        Exiger un contrôle maintenant
                    </button>
                    <button type="button" wire:click="revoquerLeVisage({{ $profil->id }})"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-500 hover:text-rose-600">
                        Révoquer le visage
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
