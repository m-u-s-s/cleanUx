{{-- Onglet « Satisfaction (NPS) » de /admin/analytics/exploration : la page porte le titre,
     cette vue ne pose que ses cartes. --}}
@php
    $nps = $score['nps'];

    // LES CLASSES SONT ECRITES EN ENTIER. Une classe assemblee a l'execution
    // (`text-{$ton}-600`) n'est jamais generee par Tailwind : le chiffre sortait sans couleur.
    $tonDuScore = match (true) {
        $nps === null => ['texte' => 'text-slate-500', 'verdict' => null],
        $nps >= 50 => ['texte' => 'text-emerald-600', 'verdict' => ['text-emerald-600', '🌟 Excellent — quartile haut mondial']],
        $nps >= 30 => ['texte' => 'text-emerald-600', 'verdict' => ['text-emerald-600', '👍 Très bon']],
        $nps >= 0 => ['texte' => 'text-amber-600', 'verdict' => ['text-amber-600', '📊 Moyen — à améliorer']],
        default => ['texte' => 'text-rose-600', 'verdict' => ['text-rose-600', '🚨 Critique — action immédiate']],
    };

    $tonsDeCategorie = [
        'promoter' => 'bg-emerald-100 text-emerald-700',
        'passive' => 'bg-amber-100 text-amber-700',
        'detractor' => 'bg-rose-100 text-rose-700',
    ];
@endphp

<div class="space-y-6">

    <div class="flex flex-wrap gap-2">
        @foreach (['7d' => '7 jours', '30d' => '30 jours', '90d' => '90 jours', 'all' => 'Tout'] as $cle => $libelle)
            <button type="button" wire:click="setPeriod('{{ $cle }}')"
                    @if($period === $cle) aria-current="true" @endif
                    class="{{ $period === $cle ? 'brio-btn-primary' : 'brio-btn-secondary' }} !px-3 !py-1.5 !text-xs">
                {{ $libelle }}
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        <x-app-card class="lg:col-span-2" title="Score NPS" subtitle="Promoteurs moins détracteurs, de -100 à +100.">
            <p class="text-6xl font-black tabular-nums {{ $tonDuScore['texte'] }}">{{ $nps ?? '—' }}</p>
            <p class="brio-section-subtitle mt-2">
                Calculé sur {{ number_format($score['total'], 0, ',', ' ') }} réponses
                @if ($nps !== null)
                    — {{ $score['promoter_percent'] }}% promoteurs, {{ $score['detractor_percent'] }}% détracteurs
                @endif
            </p>
            @if ($tonDuScore['verdict'])
                <p class="mt-2 text-xs font-bold {{ $tonDuScore['verdict'][0] }}">{{ $tonDuScore['verdict'][1] }}</p>
            @endif
        </x-app-card>

        <x-kpi-card title="Promoteurs (9-10)"
                    :value="number_format($score['promoters'], 0, ',', ' ')"
                    :hint="$score['total'] > 0 ? $score['promoter_percent'].'%' : null"
                    tone="green" icon="😍" />

        <x-kpi-card title="Détracteurs (0-6)"
                    :value="number_format($score['detractors'], 0, ',', ' ')"
                    :hint="$score['total'] > 0 ? $score['detractor_percent'].'%' : null"
                    tone="rose" icon="😞" />
    </div>

    <x-table-shell title="Réponses" subtitle="Le détail derrière le score, filtrable par enquête et par catégorie.">
        <div class="flex flex-col gap-3 px-5 pt-4 md:flex-row md:px-6">
            <select wire:model.live="surveyFilter" class="rounded-lg text-sm">
                <option value="">Toutes enquêtes</option>
                <option value="post_booking">Post-mission</option>
                <option value="monthly">Mensuel</option>
                <option value="annual">Annuel</option>
                <option value="onboarding">Onboarding</option>
                <option value="churn">Départ client</option>
            </select>
            <select wire:model.live="categoryFilter" class="rounded-lg text-sm">
                <option value="">Toutes catégories</option>
                <option value="promoter">Promoteurs</option>
                <option value="passive">Passifs</option>
                <option value="detractor">Détracteurs</option>
            </select>
        </div>

        <table class="min-w-full brio-table">
            <thead>
                <tr>
                    <th class="text-left">Utilisateur</th>
                    <th class="text-left">Enquête</th>
                    <th class="text-left">Score</th>
                    <th class="text-left">Catégorie</th>
                    <th class="text-left">Commentaire</th>
                    <th class="text-left">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>
                            <p class="font-semibold">{{ $r->user?->name }}</p>
                            <p class="brio-section-subtitle">{{ $r->user?->email }}</p>
                        </td>
                        <td class="text-xs">{{ $r->survey_code }}</td>
                        <td class="font-bold tabular-nums">{{ $r->score }}/10</td>
                        <td>
                            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $tonsDeCategorie[$r->category] ?? 'bg-slate-100 text-slate-700' }}">
                                {{ $r->category }}
                            </span>
                        </td>
                        <td class="max-w-xs truncate text-xs">{{ $r->comment }}</td>
                        <td class="text-xs">{{ $r->responded_at?->format('d/m H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-empty-state title="Aucune réponse" message="Aucune réponse sur cette période." icon="💬" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-5 py-3 md:px-6">{{ $rows->links() }}</div>
    </x-table-shell>
</div>
