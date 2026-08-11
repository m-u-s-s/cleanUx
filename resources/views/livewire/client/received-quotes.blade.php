<div class="mx-auto max-w-3xl px-4 py-8">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Devis reçus</h1>
        <p class="mt-1 text-sm text-slate-500">
            Accepter crée les rendez-vous correspondants.
        </p>
    </header>

    @if ($refus)
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        {{ $refus }}
    </div>
    @endif

    @if ($devisOuvert)
    <div class="mb-8 rounded-2xl border border-blue-200 bg-white p-5">
        <div class="mb-4">
            <p class="text-base font-bold text-slate-900">{{ $devisOuvert->title }}</p>
            <p class="text-xs text-slate-500">
                {{ $devisOuvert->organizationAccount?->name }} · {{ $devisOuvert->reference }}
                @if ($devisOuvert->valid_until)
                · valable jusqu'au {{ $devisOuvert->valid_until->format('d/m/Y') }}
                @endif
            </p>
        </div>

        <div class="mb-4 divide-y divide-slate-100 rounded-xl border border-slate-100">
            @foreach ($devisOuvert->lines as $ligne)
            <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $ligne->label }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $ligne->trade?->name }}
                        · {{ rtrim(rtrim(number_format($ligne->quantity, 2, ',', ' '), '0'), ',') }} {{ $ligne->unit }}
                    </p>
                </div>
                <span class="shrink-0 text-sm font-semibold tabular-nums text-slate-900">
                    {{ number_format($ligne->total_cents / 100, 2, ',', ' ') }} €
                </span>
            </div>
            @endforeach
        </div>

        <div class="mb-4 flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
            <span class="text-sm font-semibold text-slate-700">Total</span>
            <span class="text-lg font-bold tabular-nums text-slate-900">
                {{ number_format($devisOuvert->total_cents / 100, 2, ',', ' ') }} €
            </span>
        </div>

        @if ($devisOuvert->estOuvert())
        <div class="flex flex-wrap gap-3">
            <button type="button" wire:click="accepter({{ $devisOuvert->id }})"
                class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                Accepter le devis
            </button>

            <button type="button" wire:click="refuser({{ $devisOuvert->id }})"
                class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Refuser
            </button>
        </div>

        <label class="mt-3 block">
            <span class="mb-1 block text-xs font-semibold text-slate-600">Motif du refus (facultatif)</span>
            <input type="text" wire:model="motifRefus"
                class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
        </label>

        <p class="mt-3 text-xs text-slate-500">
            En acceptant, chaque ligne devient un rendez-vous à planifier avec le prestataire.
        </p>
        @else
        <p class="text-sm text-slate-500">
            Ce devis n'attend plus de réponse.
        </p>
        @endif
    </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white">
        @forelse ($devis as $document)
        <div class="flex items-center justify-between gap-3 border-b border-slate-50 px-5 py-3 last:border-0">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">{{ $document->title }}</p>
                <p class="truncate text-xs text-slate-500">
                    {{ $document->organizationAccount?->name }} · {{ $document->reference }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <span class="text-sm font-semibold tabular-nums text-slate-900">
                    {{ number_format($document->total_cents / 100, 2, ',', ' ') }} €
                </span>

                @if ($document->estOuvert())
                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">À décider</span>
                @elseif ($document->status === \App\Models\ProviderQuote::STATUS_ACCEPTED)
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Accepté</span>
                @elseif ($document->status === \App\Models\ProviderQuote::STATUS_DECLINED)
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Refusé</span>
                @else
                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Périmé</span>
                @endif

                <button type="button" wire:click="ouvrirLeDevis({{ $document->id }})"
                    class="text-xs font-semibold text-blue-600 hover:underline">
                    Ouvrir
                </button>
            </div>
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">
            Aucun devis reçu.
        </p>
        @endforelse
    </div>
</div>
