<div class="mx-auto max-w-4xl">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Devis</h1>
        <p class="mt-1 text-sm text-slate-500">
            Chiffrez vous-même, envoyez au client. Accepté, le devis crée les missions.
        </p>
    </header>

    @if ($refus)
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        {{ $refus }}
    </div>
    @endif

    @if ($peutGerer)
    {{-- Nouveau devis --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500">Nouveau devis</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Objet</span>
                <input type="text" wire:model="titre" placeholder="Remise en état des communs"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('titre')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Client</span>
                <select wire:model="clientId"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    <option value="">À choisir</option>
                    @foreach ($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs text-slate-500">
                    Vos clients — ceux pour qui vous avez déjà travaillé.
                </span>
            </label>
        </div>

        <button type="button" wire:click="creerLeDevis"
            class="mt-4 w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto">
            Ouvrir le brouillon
        </button>
    </div>
    @endif

    {{-- Le devis ouvert --}}
    @if ($devisOuvert)
    <div class="mb-8 rounded-2xl border border-blue-200 bg-white p-5">
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <p class="text-base font-bold text-slate-900">{{ $devisOuvert->title }}</p>
                <p class="text-xs text-slate-500">
                    {{ $devisOuvert->reference }}
                    @if ($devisOuvert->client) — {{ $devisOuvert->client->name }} @endif
                </p>
            </div>
            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                {{ number_format($devisOuvert->total_cents / 100, 2, ',', ' ') }} €
            </span>
        </div>

        {{-- Les lignes --}}
        <div class="mb-4 divide-y divide-slate-100 rounded-xl border border-slate-100">
            @forelse ($devisOuvert->lines as $ligne)
            <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $ligne->label }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $ligne->trade?->name ?? 'Métier inconnu' }}
                        · {{ rtrim(rtrim(number_format($ligne->quantity, 2, ',', ' '), '0'), ',') }} {{ $ligne->unit }}
                        @if ($ligne->suggested_price_cents && $ligne->suggested_price_cents !== $ligne->unit_price_cents)
                        {{-- L'écart avec la suggestion rend la remise lisible. --}}
                        <span class="ml-1 text-amber-700">
                            (tarif suggéré {{ number_format($ligne->suggested_price_cents / 100, 2, ',', ' ') }} €)
                        </span>
                        @endif
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <span class="text-sm font-semibold tabular-nums text-slate-900">
                        {{ number_format($ligne->total_cents / 100, 2, ',', ' ') }} €
                    </span>
                    @if ($peutGerer && $devisOuvert->status === \App\Models\ProviderQuote::STATUS_DRAFT)
                    <button type="button" wire:click="retirerUneLigne({{ $ligne->id }})"
                        class="text-xs font-semibold text-rose-600 hover:underline">
                        Retirer
                    </button>
                    @endif
                </div>
            </div>
            @empty
            <p class="px-4 py-6 text-center text-sm text-slate-500">Aucune ligne.</p>
            @endforelse
        </div>

        @if ($peutGerer && $devisOuvert->status === \App\Models\ProviderQuote::STATUS_DRAFT)
        {{-- Ajouter une ligne --}}
        <div class="grid gap-3 sm:grid-cols-4">
            <label class="block sm:col-span-2">
                <span class="mb-1 block text-xs font-semibold text-slate-600">Prestation</span>
                <input type="text" wire:model="ligneLibelle"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('ligneLibelle')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-slate-600">Métier</span>
                <select wire:model="ligneTradeId"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    <option value="">Choisir…</option>
                    @foreach ($metiers as $metier)
                    <option value="{{ $metier->id }}">{{ $metier->name }}</option>
                    @endforeach
                </select>
                @error('ligneTradeId')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-slate-600">Quantité</span>
                <input type="text" wire:model="ligneQuantite"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-slate-600">Prix unitaire (€)</span>
                <input type="text" wire:model="lignePrix" placeholder="tarif suggéré"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" wire:click="ajouterUneLigne"
                class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Ajouter la ligne
            </button>

            <button type="button" wire:click="envoyer({{ $devisOuvert->id }})"
                class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                Envoyer au client
            </button>
        </div>

        <p class="mt-3 text-xs text-slate-500">
            Le montant est figé à l'envoi. Un devis envoyé ne se modifie plus : le client accepte
            exactement ce qu'il a reçu.
        </p>
        @endif
    </div>
    @endif

    {{-- Le pipeline --}}
    <div class="rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Vos devis
        </h2>

        @forelse ($devis as $document)
        <div class="flex items-center justify-between gap-3 border-b border-slate-50 px-5 py-3 last:border-0">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">{{ $document->title }}</p>
                <p class="text-xs text-slate-500">
                    {{ $document->reference }}
                    @if ($document->client) — {{ $document->client->name }} @endif
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <span class="text-sm font-semibold tabular-nums text-slate-900">
                    {{ number_format($document->total_cents / 100, 2, ',', ' ') }} €
                </span>

                @php
                $couleurs = [
                    'draft' => 'bg-slate-100 text-slate-700',
                    'sent' => 'bg-blue-50 text-blue-700',
                    'accepted' => 'bg-emerald-50 text-emerald-700',
                    'declined' => 'bg-rose-50 text-rose-700',
                    'expired' => 'bg-amber-50 text-amber-800',
                ];
                $libelles = [
                    'draft' => 'Brouillon',
                    'sent' => 'Envoyé',
                    'accepted' => 'Accepté',
                    'declined' => 'Refusé',
                    'expired' => 'Périmé',
                ];
                @endphp
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $couleurs[$document->status] ?? 'bg-slate-100 text-slate-700' }}">
                    {{ $libelles[$document->status] ?? $document->status }}
                </span>

                <button type="button" wire:click="ouvrirLeDevis({{ $document->id }})"
                    class="text-xs font-semibold text-blue-600 hover:underline">
                    Ouvrir
                </button>
            </div>
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">
            Aucun devis. Jusqu'ici, seul un administrateur pouvait en saisir un pour vous.
        </p>
        @endforelse
    </div>
</div>
