<div class="mx-auto max-w-4xl">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Consommables</h1>
        <p class="mt-1 text-sm text-slate-500">
            Ce qui reste, dans quelle implantation, et pourquoi le stock a bougé.
        </p>
    </header>

    @if ($refus)
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        {{ $refus }}
    </div>
    @endif

    @if ($aReappro->isNotEmpty())
    {{-- La rupture se découvre le matin du départ, sinon. --}}
    <div class="mb-8 rounded-2xl border border-rose-200 bg-rose-50 p-5">
        <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-rose-800">À réapprovisionner</h2>
        <ul class="space-y-1 text-sm text-rose-900">
            @foreach ($aReappro as $bas)
            <li>
                <span class="font-semibold">{{ $bas->name }}</span>
                — il reste {{ $bas->quantity }} {{ $bas->unit }} (seuil {{ $bas->reorder_threshold }})
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if ($peutGerer)
    {{-- Créer un article --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500">Nouvel article</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Nom</span>
                <input type="text" wire:model="nom" placeholder="Sacs poubelle 100 L"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('nom')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Unité</span>
                <input type="text" wire:model="unite" placeholder="carton"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('unite')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Seuil de réappro</span>
                <input type="number" min="0" wire:model="seuil"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('seuil')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Coût unitaire (€)</span>
                <input type="text" wire:model="coutUnitaire" placeholder="4,50"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>

            <label class="block sm:col-span-2">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Implantation</span>
                <select wire:model="agenceId"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    <option value="">Toutes</option>
                    @foreach ($agences as $agence)
                    <option value="{{ $agence->id }}">{{ $agence->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <button type="button" wire:click="creerLArticle"
            class="mt-4 w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto">
            Créer l'article
        </button>

        <p class="mt-3 text-xs text-slate-500">
            Le stock part de zéro et monte par une réception. On ne saisit jamais un compteur : il est
            le résultat des mouvements.
        </p>
    </div>
    @endif

    {{-- Les articles --}}
    <div class="rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Stock
        </h2>

        @forelse ($articles as $article)
        <div class="border-b border-slate-50 px-5 py-4 last:border-0">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ $article->name }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $article->quantity }} {{ $article->unit }}
                        @if ($article->agency) — {{ $article->agency->name }} @endif
                        @if ($article->quantity <= $article->reorder_threshold)
                        <span class="ml-1 rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700">Stock bas</span>
                        @endif
                    </p>
                </div>

                <button type="button" wire:click="ouvrirLArticle({{ $article->id }})"
                    class="shrink-0 text-xs font-semibold text-blue-600 hover:underline">
                    Historique
                </button>
            </div>

            @if ($peutGerer)
            <div class="mt-3 flex flex-wrap items-end gap-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold text-slate-600">Quantité</span>
                    <input type="number" wire:model="quantite"
                        class="w-24 rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                </label>

                <label class="block flex-1 min-w-[10rem]">
                    <span class="mb-1 block text-xs font-semibold text-slate-600">Motif</span>
                    <input type="text" wire:model="motif"
                        class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                </label>

                <button type="button" wire:click="receptionner({{ $article->id }})"
                    class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                    Réceptionner
                </button>
                <button type="button" wire:click="consommer({{ $article->id }})"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Prélever
                </button>
                <button type="button" wire:click="ajuster({{ $article->id }})"
                    class="rounded-lg border border-amber-400 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50">
                    Ajuster
                </button>
            </div>
            @endif

            @if ($articleOuvertId === $article->id)
            <div class="mt-4 rounded-xl bg-slate-50 p-4">
                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Derniers mouvements</p>

                @forelse ($mouvements as $mouvement)
                <div class="flex items-center justify-between border-b border-slate-200/60 py-1.5 last:border-0 text-xs">
                    <span class="text-slate-600">
                        {{ $mouvement->created_at?->format('d/m/Y H:i') }}
                        — {{ $mouvement->user?->name ?? 'Système' }}
                        @if ($mouvement->reason) — {{ $mouvement->reason }} @endif
                    </span>
                    <span class="font-semibold tabular-nums {{ $mouvement->quantity >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $mouvement->quantity > 0 ? '+' : '' }}{{ $mouvement->quantity }}
                    </span>
                </div>
                @empty
                <p class="text-xs text-slate-500">Aucun mouvement.</p>
                @endforelse
            </div>
            @endif
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">
            Aucun consommable déclaré.
        </p>
        @endforelse
    </div>
</div>
