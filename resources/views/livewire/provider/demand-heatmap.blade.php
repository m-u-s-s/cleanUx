@php
    $maximum = collect($lignes)->max('demand_count') ?: 1;
@endphp

<div class="mx-auto max-w-4xl px-4 py-8">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Où me placer</h1>
        <p class="mt-1 text-sm text-slate-500">
            La demande observée par zone et par tranche horaire.
        </p>
    </header>

    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <label for="heatmap-metier" class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Métier</span>
                <select id="heatmap-metier" wire:model.live="tradeId"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    <option value="">Tous les métiers</option>
                    @foreach ($metiers as $metier)
                    <option value="{{ $metier->id }}">{{ $metier->name }}</option>
                    @endforeach
                </select>
            </label>

            <label for="heatmap-jours" class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Observation</span>
                <select id="heatmap-jours" wire:model.live="jours"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    <option value="7">7 derniers jours</option>
                    <option value="28">28 derniers jours</option>
                    <option value="90">90 derniers jours</option>
                </select>
            </label>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Créneaux les plus demandés
        </h2>

        @forelse ($lignes as $ligne)
        <div class="border-b border-slate-50 px-5 py-3 last:border-0">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">
                        {{ $ligne['zone_name'] }} — {{ $ligne['slot_label'] }}
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ $ligne['per_day'] }} demande(s) par jour
                        · {{ $ligne['immediate_count'] }} immédiate(s),
                        {{ $ligne['scheduled_count'] }} planifiée(s)
                    </p>
                </div>
                <span class="shrink-0 text-sm font-semibold tabular-nums text-slate-900">
                    {{ $ligne['demand_count'] }}
                </span>
            </div>

            <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-slate-900"
                    style="width: {{ max(3, (int) round($ligne['demand_count'] / $maximum * 100)) }}%"></div>
            </div>
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">
            Pas assez de demande observée sur cette période pour dire quoi que ce soit d'utile.
        </p>
        @endforelse

        {{--
            CE N'EST PAS UNE PROMESSE. Une demande passée ne garantit pas une demande future :
            afficher un classement sans dire sur combien de jours il porte ferait lire un pic isolé
            comme une tendance, et déplacer quelqu'un pour rien.
        --}}
        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
            Observation sur {{ $jours }} jours. Les demandes passées n'engagent pas les prochaines :
            ce tableau dit où la demande a eu lieu, pas où elle aura lieu.
        </p>
    </div>
</div>
