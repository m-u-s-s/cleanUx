{{--
    Le catalogue : secteurs et métiers.

    L'écran ne se contente pas de lister. Il montre, pour chaque métier, ce qui empêche sa
    publication et ce qui attend d'être mis en ligne — un catalogue où il faut ouvrir dix écrans
    pour savoir lequel est prêt ne sera pas tenu à jour.
--}}
<div class="space-y-6">

    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Catalogue</h1>
            <p class="mt-1 text-sm text-slate-500">
                Secteurs, métiers et parcours de commande. L’ordre ci-dessous est celui du carrousel client.
            </p>
        </div>

        <button type="button" wire:click="startNewSector"
            class="min-h-[44px] rounded-xl bg-slate-900 px-5 text-sm font-medium text-white transition hover:bg-slate-800">
            Ajouter un secteur
        </button>
    </header>

    @if ($flash)
        <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $flash }}</p>
    @endif

    {{-- ─── Secteurs ────────────────────────────────────────────────────────────────────── --}}
    <div class="space-y-4">
        @forelse ($this->sectors() as $index => $sector)
            <section @class([
                'rounded-2xl border bg-white p-5',
                'border-slate-200' => $sector->is_active,
                'border-dashed border-slate-300 opacity-70' => ! $sector->is_active,
            ]) wire:key="sector-{{ $sector->id }}">

                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        {{-- La pastille de couleur : le seul endroit saturé, et il faut le voir ici. --}}
                        <span class="mt-1 h-4 w-4 shrink-0 rounded-full ring-1 ring-slate-200"
                            style="background-color: {{ $sector->accent_color ?? '#e2e8f0' }}"
                            aria-hidden="true"></span>

                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-slate-900">{{ $sector->name }}</h2>
                            @if ($sector->tagline)
                                <p class="mt-0.5 text-sm text-slate-500">{{ $sector->tagline }}</p>
                            @endif
                            <p class="mt-1 font-mono text-xs text-slate-400">{{ $sector->slug }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <button type="button" wire:click="moveSector({{ $sector->id }}, -1)" aria-label="Monter"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 disabled:opacity-30"
                            @disabled($index === 0)>↑</button>
                        <button type="button" wire:click="moveSector({{ $sector->id }}, 1)" aria-label="Descendre"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 disabled:opacity-30"
                            @disabled($index === $this->sectors()->count() - 1)>↓</button>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-3 text-sm">
                    <button type="button" wire:click="editSector({{ $sector->id }})"
                        class="font-medium text-slate-700 underline underline-offset-4 hover:text-slate-900">Modifier</button>
                    <button type="button" wire:click="toggleSector({{ $sector->id }})"
                        class="text-slate-500 underline underline-offset-4 hover:text-slate-800">
                        {{ $sector->is_active ? 'Retirer du carrousel' : 'Remettre en ligne' }}
                    </button>
                    <button type="button" wire:click="confirmArchiveSector({{ $sector->id }})"
                        class="text-rose-700 underline underline-offset-4 hover:text-rose-900">Archiver</button>
                </div>

                {{-- ─── Métiers du secteur ──────────────────────────────────────────────── --}}
                @if ($sector->trades->isEmpty())
                    <p class="mt-4 rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">
                        Aucun métier. Un secteur vide n’apparaît pas dans le carrousel.
                    </p>
                @else
                    <ul class="mt-4 divide-y divide-slate-100 border-t border-slate-100">
                        @foreach ($sector->trades as $trade)
                            @php $status = $this->tradeStatuses()[$trade->id] ?? null; @endphp
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="trade-{{ $trade->id }}">
                                <div class="min-w-0">
                                    <p @class(['truncate text-[15px] font-medium text-slate-900', 'line-through opacity-50' => ! $trade->is_active])>
                                        {{ $trade->name }}
                                    </p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs">
                                        <span class="font-mono text-slate-400">{{ $trade->slug }}</span>

                                        @if ($status && $status['version'])
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-600">
                                                version {{ $status['version'] }}
                                            </span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-500">jamais publié</span>
                                        @endif

                                        @if ($status && $status['blocking'] > 0)
                                            <span class="rounded-full bg-rose-50 px-2 py-0.5 text-rose-700">
                                                publication bloquée
                                            </span>
                                        @elseif ($status && $status['pending'])
                                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-800">
                                                modifications non publiées
                                            </span>
                                        @endif
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center gap-3 text-sm">
                                    <a href="{{ route('admin.order-engine.builder', $trade) }}"
                                        class="font-medium text-slate-700 underline underline-offset-4 hover:text-slate-900">
                                        Parcours
                                    </a>
                                    <button type="button" wire:click="toggleTrade({{ $trade->id }})"
                                        class="text-slate-500 underline underline-offset-4 hover:text-slate-800">
                                        {{ $trade->is_active ? 'Désactiver' : 'Activer' }}
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @empty
            <p class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                Aucun secteur. Commencez par celui qui pèse le plus dans vos commandes.
            </p>
        @endforelse
    </div>

    {{-- ─── Métiers orphelins ───────────────────────────────────────────────────────────── --}}
    @if ($this->orphanTrades()->isNotEmpty())
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="text-[15px] font-semibold text-amber-900">
                {{ $this->orphanTrades()->count() }} métier(s) rattaché(s) à aucun secteur
            </h2>
            <p class="mt-1 text-sm text-amber-800">
                Ils restent utilisables par le reste de la plateforme, mais n’apparaissent pas dans
                le parcours de commande. Les taire ferait chercher longtemps pourquoi ils sont
                introuvables côté client.
            </p>

            <ul class="mt-3 space-y-2">
                @foreach ($this->orphanTrades() as $trade)
                    <li class="flex flex-wrap items-center justify-between gap-3" wire:key="orphan-{{ $trade->id }}">
                        <span class="text-sm text-amber-900">{{ $trade->name }}</span>

                        @if ($this->sectors()->isNotEmpty())
                            <div class="flex items-center gap-2">
                                <label class="sr-only" for="attach-{{ $trade->id }}">Rattacher {{ $trade->name }}</label>
                                <select id="attach-{{ $trade->id }}"
                                    wire:change="attachTrade({{ $trade->id }}, $event.target.value)"
                                    class="rounded-lg border-amber-300 bg-white py-1.5 text-sm text-amber-900">
                                    <option value="">Rattacher à…</option>
                                    @foreach ($this->sectors() as $sector)
                                        <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ─── Formulaire secteur ──────────────────────────────────────────────────────────── --}}
    @if ($editingSectorId !== null)
        <div class="rounded-2xl border border-slate-300 bg-white p-5" role="region" aria-label="Édition d’un secteur">
            <h2 class="text-lg font-semibold text-slate-900">
                {{ $editingSectorId ? 'Modifier le secteur' : 'Nouveau secteur' }}
            </h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="mb-1 block text-sm font-medium text-slate-700">Nom</span>
                    <input type="text" wire:model.live.debounce.400ms="sectorForm.name"
                        class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-0">
                    @error('sectorForm.name') <span class="mt-1 block text-sm text-rose-700">{{ $message }}</span> @enderror
                </label>

                <label>
                    <span class="mb-1 block text-sm font-medium text-slate-700">Slug</span>
                    <input type="text" wire:model="sectorForm.slug"
                        class="w-full rounded-xl border-slate-300 font-mono text-sm focus:border-slate-900 focus:ring-0">
                    @error('sectorForm.slug') <span class="mt-1 block text-sm text-rose-700">{{ $message }}</span> @enderror
                </label>

                <label class="sm:col-span-2">
                    <span class="mb-1 block text-sm font-medium text-slate-700">Accroche</span>
                    <input type="text" wire:model="sectorForm.tagline"
                        placeholder="Du petit dépannage au chantier complet"
                        class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-0">
                </label>

                <label>
                    <span class="mb-1 block text-sm font-medium text-slate-700">Couleur d’accent</span>
                    <input type="text" wire:model="sectorForm.accent_color" placeholder="#0E7490"
                        class="w-full rounded-xl border-slate-300 font-mono text-sm focus:border-slate-900 focus:ring-0">
                    @error('sectorForm.accent_color') <span class="mt-1 block text-sm text-rose-700">{{ $message }}</span> @enderror
                </label>

                <label>
                    <span class="mb-1 block text-sm font-medium text-slate-700">Icône</span>
                    <input type="text" wire:model="sectorForm.icon" placeholder="hammer"
                        class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-0">
                </label>
            </div>

            <div class="mt-5 flex gap-3">
                <button type="button" wire:click="saveSector"
                    class="min-h-[44px] rounded-xl bg-slate-900 px-5 text-sm font-medium text-white hover:bg-slate-800">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancelSector"
                    class="min-h-[44px] rounded-xl px-5 text-sm font-medium text-slate-600 hover:text-slate-900">
                    Annuler
                </button>
            </div>
        </div>
    @endif

    {{-- ─── Confirmation d'archivage ────────────────────────────────────────────────────── --}}
    @if ($archiveImpact)
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5" role="alertdialog" aria-label="Confirmer l’archivage">
            <h2 class="text-lg font-semibold text-rose-900">Archiver ce secteur ?</h2>
            <p class="mt-2 text-sm leading-relaxed text-rose-900">{{ $archiveImpact['summary'] }}</p>

            <div class="mt-4 flex gap-3">
                <button type="button" wire:click="archiveSector"
                    class="min-h-[44px] rounded-xl bg-rose-700 px-5 text-sm font-medium text-white hover:bg-rose-800">
                    Archiver
                </button>
                <button type="button" wire:click="cancelArchive"
                    class="min-h-[44px] rounded-xl px-5 text-sm font-medium text-rose-900 hover:underline">
                    Annuler
                </button>
            </div>
        </div>
    @endif
</div>
