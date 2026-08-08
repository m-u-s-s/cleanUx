<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Sites desservis</h1>
            <p class="text-sm text-slate-500">
                Déduits de vos missions et de vos contrats-cadres — rien à saisir.
            </p>
        </div>

        <input wire:model.live.debounce.300ms="recherche" type="search"
            placeholder="Rechercher un site…"
            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder-slate-400">
    </div>

    @forelse ($sites as $site)
        <div class="rounded-2xl border border-slate-200 bg-white p-4" wire:key="site-{{ $site->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate font-semibold text-slate-900">{{ $site->name }}</p>
                    <p class="truncate text-sm text-slate-500">
                        {{ collect([$site->address, $site->postal_code, $site->city])->filter()->implode(' · ') ?: 'Adresse non renseignée' }}
                    </p>
                </div>

                @if ($site->surface_m2)
                    <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-600">
                        {{ $site->surface_m2 }} m²
                    </span>
                @endif
            </div>

            {{--
                Les référents affichés sont UNIQUEMENT ceux de notre société : la relation est
                chargée scopée dans le composant. Deux prestataires peuvent desservir le même
                immeuble, et la composition de l'équipe adverse ne nous regarde pas.
            --}}
            <div class="mt-3 flex flex-wrap items-center gap-2">
                @forelse ($site->providerAssignments as $affectation)
                    <span class="flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-900"
                        wire:key="ref-{{ $affectation->id }}">
                        {{ $affectation->user?->name ?? 'Compte supprimé' }}
                        <button wire:click="retirerReferent({{ $affectation->id }})"
                            title="Retirer le référent"
                            class="text-slate-500 hover:text-red-600">✕</button>
                    </span>
                @empty
                    <span class="text-xs text-slate-400">Aucun référent désigné</span>
                @endforelse
            </div>

            <div class="mt-3">
                <select wire:change="designerReferent({{ $site->id }}, $event.target.value)"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 sm:w-auto">
                    <option value="">Désigner un référent…</option>
                    @foreach ($membres as $membre)
                        <option value="{{ $membre->user_id }}">{{ $membre->user?->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-200 p-8 text-center">
            <p class="font-semibold text-slate-900">Aucun site desservi</p>
            <p class="mt-1 text-sm text-slate-500">
                Les sites apparaîtront dès votre première mission ou votre premier contrat-cadre.
            </p>
        </div>
    @endforelse
</div>
