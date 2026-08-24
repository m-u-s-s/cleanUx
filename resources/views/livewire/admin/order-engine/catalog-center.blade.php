{{--
    Le catalogue : secteurs et métiers.

    L'écran ne se contente pas de lister. Il montre, pour chaque métier, ce qui empêche sa
    publication et ce qui attend d'être mis en ligne — un catalogue où il faut ouvrir dix écrans
    pour savoir lequel est prêt ne sera pas tenu à jour.
--}}
<div class="space-y-6">

    {{-- ─── Fil d'Ariane ────────────────────────────────────────────────────────────────── --}}
    <nav class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
        <a href="{{ route('admin.order-engine.catalog') }}" class="hover:text-slate-900">Catalogue</a>
        <span aria-hidden="true">›</span>
        <a href="{{ route('admin.order-engine.zones', $country) }}" class="hover:text-slate-900">{{ $country->name }}</a>
        <span aria-hidden="true">›</span>
        <span class="font-medium text-slate-900">{{ $zone->name }}</span>
    </nav>

    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Catalogue — {{ $zone->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                Secteurs, métiers et parcours de commande. L’ordre ci-dessous est celui du carrousel client.
                L’ouverture de chaque métier se règle <strong>pour cette zone</strong>.
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

    {{--
        LE BANDEAU D'AVERTISSEMENT A DISPARU PARCE QUE LA VÉRITÉ A CHANGÉ.

        Il disait que l'ouverture réglée ici n'avait aucun effet client : le parcours de commande ne
        déterminait pas la zone d'une adresse et ne lisait pas `trade_zone_pricing`. Les deux
        branchements sont faits — la zone est résolue depuis le code postal pendant le parcours, et
        c'est cette ligne-ci qui fixe le prix ET la disponibilité du mode immédiat.

        Ce qui remplace l'avertissement est ci-dessous : chaque métier affiche son état RÉEL dans
        cette zone, ouverture et immédiat compris.
    --}}
    <div class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        <strong>Ce que vous réglez ici est ce que voit le client.</strong>
        L’ouverture d’un métier dans cette zone décide de sa présence au parcours de commande et de
        son prix ; l’<strong>intervention immédiate</strong> se règle métier par métier, ici même.
    </div>

    {{-- ─── Secteurs ────────────────────────────────────────────────────────────────────── --}}
    {{--
        L'ordre du catalogue est celui du CARROUSEL et du DOCK : le premier secteur est ce que
        voit tout visiteur, le premier métier ce qu'on lui propose. Il se réglait aux flèches sur
        les secteurs, et pas du tout sur les métiers — alors que ce sont eux qui se vendent.

        Les flèches RESTENT partout : le glisser-déposer ne fonctionne ni au clavier ni avec un
        lecteur d'écran, et ceci est un écran de travail quotidien.
    --}}
    <div class="space-y-4" x-data="catalogSorter('reorderSectors')" x-init="boot()" data-sector-root>
        @forelse ($this->sectors() as $index => $sector)
            <section draggable="true" data-sort-id="{{ $sector->id }}" @class([
                'rounded-2xl border bg-white p-5',
                'border-slate-200' => $sector->is_active,
                'border-dashed border-slate-300 opacity-70' => ! $sector->is_active,
            ]) wire:key="sector-{{ $sector->id }}">

                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        {{-- La pastille de couleur : le seul endroit saturé, et il faut le voir ici. --}}
                        <span class="mt-1 h-4 w-4 shrink-0 rounded-full ring-1 ring-slate-200"
                            style="background-color: {{ $sector->accent_color ?? 'var(--brio-border)' }}"
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

                {{--
                    Zone du pouce : ces liens-boutons faisaient 20 px de haut, ce que le balayage
                    de QA à 390 px a signalé comme hostile. `min-h-[44px]` les rend atteignables
                    sans les alourdir — ils restent des liens, avec une cible utilisable.
                --}}
                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                    <button type="button" wire:click="editSector({{ $sector->id }})"
                        class="inline-flex min-h-[44px] items-center font-medium text-slate-700 underline underline-offset-4 hover:text-slate-900">Modifier</button>
                    <button type="button" wire:click="toggleSector({{ $sector->id }})"
                        class="inline-flex min-h-[44px] items-center text-slate-500 underline underline-offset-4 hover:text-slate-800">
                        {{ $sector->is_active ? 'Retirer du carrousel' : 'Remettre en ligne' }}
                    </button>
                    <button type="button" wire:click="confirmArchiveSector({{ $sector->id }})"
                        class="inline-flex min-h-[44px] items-center text-rose-700 underline underline-offset-4 hover:text-rose-900">Archiver</button>

                    {{--
                        Le bouton vit DANS le secteur : cliquer ici dit déjà où le métier va, il
                        n'y a donc aucun secteur à choisir ensuite dans le formulaire.
                    --}}
                    <button type="button" wire:click="ouvrirCreationMetier({{ $sector->id }})"
                        class="inline-flex min-h-[44px] items-center rounded-xl bg-slate-900 px-4 text-sm font-medium text-white transition hover:bg-slate-800">
                        Ajouter un métier
                    </button>
                </div>


                @include('livewire.admin.order-engine.partials.traductions', [
                    'objet' => $sector,
                    'type' => 'sector',
                    'champs' => ['name' => 'Nom du secteur', 'tagline' => 'Accroche'],
                ])
                {{-- ─── Métiers du secteur ──────────────────────────────────────────────── --}}
                @if ($sector->trades->isEmpty())
                    <p class="mt-4 rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">
                        Aucun métier. Un secteur vide n’apparaît pas dans le carrousel.
                    </p>
                @else
                    <ul class="mt-4 divide-y divide-slate-100 border-t border-slate-100"
                        x-data="catalogSorter('reorderTrades', {{ $sector->id }})" x-init="boot()"
                        data-sector-root>
                        @foreach ($sector->trades as $trade)
                            @php $status = $this->tradeStatuses()[$trade->id] ?? null; @endphp
                            <li draggable="true" data-sort-id="{{ $trade->id }}"
                                class="flex flex-wrap items-center justify-between gap-3 py-3"
                                wire:key="trade-{{ $trade->id }}">
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

                                        {{-- Le signal qui rend les statistiques utiles : sans lui,
                                             il faudrait ouvrir les douze métiers un par un pour
                                             trouver celui qui perd ses clients. --}}
                                        @if ($status && ($status['losing'] ?? false))
                                            <span class="rounded-full bg-rose-50 px-2 py-0.5 text-rose-700">
                                                une question fait décrocher
                                            </span>
                                        @endif

                                        {{--
                                            L'état PROPRE À CETTE ZONE, distinct de l'état du
                                            métier lui-même. Un métier peut être publié et prêt
                                            partout, et fermé ici.
                                        --}}
                                        @if ($this->metiersActifsDansLaZone[$trade->id] ?? false)
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">
                                                ouvert à {{ $zone->name }}
                                            </span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-500">
                                                fermé à {{ $zone->name }}
                                            </span>
                                        @endif

                                        {{--
                                            L'IMMÉDIAT, décidé zone par zone. Le drapeau global du
                                            métier dit qu'un dépannage EST possible ; cette ligne-ci
                                            dit qu'on l'a ouvert ICI. Promettre l'immédiat là où
                                            personne n'est jamais en ligne fait attendre le client
                                            devant sa porte.
                                        --}}
                                        @if ($this->metiersEnImmediatDansLaZone[$trade->id] ?? false)
                                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-700">
                                                intervention immédiate
                                            </span>
                                        @endif
                                    </p>

                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <button type="button"
                                            wire:click="basculerMetierDansLaZone({{ $trade->id }})"
                                            class="min-h-[36px] rounded-lg border border-slate-300 px-3 text-xs text-slate-700 transition hover:bg-slate-50">
                                            {{ ($this->metiersActifsDansLaZone[$trade->id] ?? false) ? 'Fermer dans cette zone' : 'Ouvrir dans cette zone' }}
                                        </button>

                                        @if (($this->metiersActifsDansLaZone[$trade->id] ?? false) && $trade->allows_asap)
                                            <button type="button"
                                                wire:click="basculerImmediatDansLaZone({{ $trade->id }})"
                                                class="min-h-[36px] rounded-lg border border-amber-300 px-3 text-xs text-amber-700 transition hover:bg-amber-50">
                                                {{ ($this->metiersEnImmediatDansLaZone[$trade->id] ?? false) ? 'Retirer l’immédiat ici' : 'Ouvrir l’immédiat ici' }}
                                            </button>
                                        @endif

                                        {{--
                                            LA MAJORATION SE RÈGLE ICI, sur la même ligne que l'ouverture
                                            et l'immédiat : c'est la même décision commerciale. Le
                                            multiplicateur existait sur `trade_zone_pricing` et le moteur
                                            de surge le lisait déjà — aucun écran ne permettait de
                                            l'écrire.
                                        --}}
                                        @if ($this->metiersActifsDansLaZone[$trade->id] ?? false)
                                            <form wire:submit="reglerMajorationDansLaZone({{ $trade->id }}, $event.target.multiplicateur.value)"
                                                class="flex items-center gap-1">
                                                <label for="majoration-{{ $trade->id }}" class="text-xs text-slate-500">
                                                    Majoration
                                                </label>
                                                <input id="majoration-{{ $trade->id }}"
                                                    name="multiplicateur"
                                                    type="number" step="0.05" min="1"
                                                    max="{{ config('surge.max_multiplier', 3.0) }}"
                                                    value="{{ $this->majorationsDansLaZone[$trade->id] ?? '1.00' }}"
                                                    class="min-h-[36px] w-20 rounded-lg border border-slate-300 px-2 text-xs text-slate-700" />
                                                <button type="submit"
                                                    class="min-h-[36px] rounded-lg border border-slate-300 px-3 text-xs text-slate-700 transition hover:bg-slate-50">
                                                    Appliquer
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-3 text-sm">
                                    {{-- Les flèches : seul chemin au clavier et au lecteur d'écran. --}}
                                    <div class="flex items-center gap-1">
                                        <button type="button" wire:click="moveTrade({{ $trade->id }}, -1)"
                                            aria-label="Monter"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 disabled:opacity-30"
                                            @disabled($loop->first)>↑</button>
                                        <button type="button" wire:click="moveTrade({{ $trade->id }}, 1)"
                                            aria-label="Descendre"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 disabled:opacity-30"
                                            @disabled($loop->last)>↓</button>
                                    </div>

                                    <a href="{{ route('admin.order-engine.builder', $trade) }}"
                                        class="inline-flex min-h-[44px] items-center font-medium text-slate-700 underline underline-offset-4 hover:text-slate-900">
                                        Parcours
                                    </a>
                                    <button type="button" wire:click="toggleTrade({{ $trade->id }})"
                                        class="inline-flex min-h-[44px] items-center text-slate-500 underline underline-offset-4 hover:text-slate-800">
                                        {{ $trade->is_active ? 'Désactiver' : 'Activer' }}
                                    </button>
                                </div>

                                @include('livewire.admin.order-engine.partials.traductions', [
                                    'objet' => $trade,
                                    'type' => 'trade',
                                    'champs' => ['name' => 'Nom du métier', 'short_description' => 'Résumé', 'description' => 'Description'],
                                ])
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
    {{-- ─── Création d'un métier ────────────────────────────────────────────────────────── --}}
    {{--
        Le formulaire est le MÊME que sur /admin/trades : même partial, mêmes champs, mêmes règles.
        Deux copies auraient divergé au premier champ ajouté, et l'écran oublié aurait continué
        d'enregistrer des métiers incomplets sans lever d'erreur.
    --}}
    @if ($creationMetierOuverte)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
            wire:click.self="fermerCreationMetier">
            <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Nouveau métier</h2>
                        {{--
                            La phrase la plus importante de cet écran. On crée depuis une zone, et
                            rien ne dirait autrement que le métier est un objet GLOBAL : seule son
                            ouverture est locale. Sans cela, on croirait créer « le ramonage de
                            Bruxelles » et on le recréerait pour chaque ville.
                        --}}
                        <p class="mt-1 text-sm text-gray-500">
                            Ce métier <strong>existera dans tous les pays et toutes les zones</strong>.
                            Il ne sera <strong>ouvert</strong> que dans {{ $zone->name }} ; les autres zones
                            resteront libres de l’ouvrir ou non.
                        </p>
                    </div>
                    <button type="button" wire:click="fermerCreationMetier"
                        class="text-gray-400 hover:text-gray-600" aria-label="Fermer">✕</button>
                </div>

                <form wire:submit.prevent="enregistrerMetier" class="space-y-4 px-6 py-5">
                    @include('livewire.admin.partials.trade-form-fields')

                    <div class="flex justify-end gap-2 border-t border-gray-200 pt-4">
                        <button type="button" wire:click="fermerCreationMetier"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            Annuler
                        </button>
                        <button type="submit"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
                            Créer le métier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
    {{--
        LA CARTE DES MAJORATIONS (E28).

        Le multiplicateur se règle zone par zone, métier par métier : personne ne pouvait voir ce que la
        plateforme facture en plus, PARTOUT, en une fois. Une majoration oubliée à 2,5 dans une zone
        tourne indéfiniment et se découvre par une plainte.
    --}}
    <div class="mx-auto mt-8 max-w-7xl px-4 sm:px-6 lg:px-8" data-test="carte-majorations">
        <div class="overflow-hidden rounded-2xl border bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">
                    Majorations en vigueur
                </h2>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    @unless ($carteDesMajorations['surge_enabled'])
                    {{-- Le drapeau compte autant que les chiffres : coupé, tout ceci ne s'applique
                         nulle part, et un écran qui ne le dirait pas ferait régler dans le vide. --}}
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">
                        Majorations désactivées globalement
                    </span>
                    @endunless

                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">
                        Plafond {{ number_format($carteDesMajorations['cap'], 2, ',', ' ') }}
                    </span>

                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">
                        {{ $carteDesMajorations['surged_count'] }} / {{ $carteDesMajorations['rows_count'] }} majorées
                    </span>

                    @if ($carteDesMajorations['exceeding_cap_count'] > 0)
                    <span class="rounded-full bg-rose-100 px-2.5 py-1 font-semibold text-rose-700">
                        {{ $carteDesMajorations['exceeding_cap_count'] }} au-dessus du plafond
                    </span>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-2 font-semibold">Métier</th>
                            <th class="px-5 py-2 font-semibold">Zone</th>
                            <th class="px-5 py-2 text-right font-semibold tabular-nums">Réglée</th>
                            <th class="px-5 py-2 text-right font-semibold tabular-nums">Appliquée</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (array_slice($carteDesMajorations['rows'], 0, 40) as $ligne)
                        <tr class="border-b last:border-0 {{ $ligne['exceeds_cap'] ? 'bg-rose-50/50' : '' }}">
                            <td class="px-5 py-3 font-semibold text-slate-900">{{ $ligne['trade_name'] }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $ligne['zone_name'] }}</td>
                            <td class="px-5 py-3 text-right tabular-nums {{ $ligne['multiplier'] > 1 ? 'font-semibold text-slate-900' : 'text-slate-400' }}">
                                × {{ number_format($ligne['multiplier'], 2, ',', ' ') }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-600">
                                × {{ number_format($ligne['effective_multiplier'], 2, ',', ' ') }}
                                @if ($ligne['exceeds_cap'])
                                {{-- Le dépassement est SIGNALÉ, pas corrigé : sans ce rappel, l'écran
                                     afficherait 3,50 et le client paierait 3,00. --}}
                                <span class="ml-1 text-xs font-semibold text-rose-700">ramenée au plafond</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-sm text-slate-500">
                                Aucune grille métier × zone n'est encore ouverte.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="border-t px-5 py-3 text-xs text-slate-500">
                Les grilles à × 1,00 sont affichées volontairement : ce qu'il faut voir est la
                proportion de lignes majorées, pas seulement leur liste.
            </p>
        </div>
    </div>
</div>


@push('scripts')
<script>
    /**
     * Reordonnancement a la souris, pour les secteurs comme pour les metiers d'un secteur.
     *
     * Un seul composant sert les deux : la difference tient a l'action Livewire appelee et, pour
     * les metiers, a l'identifiant du secteur passe en premier argument.
     *
     * L'ordre part au SERVEUR, qui le revalide : il refuse une liste partielle ou contenant un
     * intrus plutot que de reordonner a moitie. Ce qui vient du navigateur n'est pas cru sur parole.
     */
    window.catalogSorter = (action, sectorId = null) => ({
        dragged: null,

        boot() {
            const root = this.$el;

            root.addEventListener('dragstart', (e) => {
                this.dragged = e.target.closest('[data-sort-id]');

                // Une carte de metier vit DANS une carte de secteur : sans ce test, saisir un
                // metier ferait aussi glisser son secteur, et les deux listes bougeraient.
                if (this.dragged && this.dragged.closest('[data-sector-root]') !== root) {
                    this.dragged = null;
                    return;
                }

                if (this.dragged) {
                    e.stopPropagation();
                    e.dataTransfer.effectAllowed = 'move';
                    this.dragged.style.opacity = '0.4';
                }
            });

            root.addEventListener('dragend', () => {
                if (this.dragged) {
                    this.dragged.style.opacity = '';
                }
                this.dragged = null;
            });

            root.addEventListener('dragover', (e) => {
                if (! this.dragged) {
                    return;
                }

                e.preventDefault();
                const over = e.target.closest('[data-sort-id]');

                if (! over || over === this.dragged || over.parentNode !== this.dragged.parentNode) {
                    return;
                }

                // Insertion avant ou apres selon le cote survole : sans ce test, deposer sur la
                // moitie basse d'une carte la placerait quand meme au-dessus.
                const box = over.getBoundingClientRect();
                const after = (e.clientY - box.top) > (box.height / 2);
                over.parentNode.insertBefore(this.dragged, after ? over.nextSibling : over);
            });

            root.addEventListener('drop', (e) => {
                if (! this.dragged) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                this.commit();
            });
        },

        commit() {
            const ids = Array.from(this.$el.children)
                .map((el) => el.dataset.sortId)
                .filter(Boolean);

            if (! ids.length) {
                return;
            }

            if (sectorId === null) {
                this.$wire.reorderSectors(ids);
            } else {
                this.$wire.reorderTrades(sectorId, ids);
            }
        },
    });
</script>
@endpush
