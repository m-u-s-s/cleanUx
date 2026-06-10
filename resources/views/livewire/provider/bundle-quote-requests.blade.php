<div class="mx-auto max-w-4xl px-4 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Demandes de devis — Chantiers groupés</h1>
        <p class="text-sm text-slate-600">Proposez votre prix pour les chantiers qui correspondent à votre métier. Le client compare et choisit.</p>
    </div>

    @if ($requests->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center">
            <div class="text-4xl">🏗️</div>
            <p class="mt-2 font-semibold text-slate-900">Aucune demande de devis en cours</p>
            <p class="text-sm text-slate-600">Vous serez notifié dès qu'un chantier correspond à votre métier.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($requests as $quote)
                <div class="rounded-2xl border px-5 py-4 transition {{ $selectedId === $quote->id ? 'border-emerald-500 bg-emerald-50/40' : 'border-slate-200 bg-white' }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900">{{ $quote->item?->label }}</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $quote->item?->trade?->name }}</span>
                                @if ($quote->status === 'submitted')
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Devis envoyé · {{ number_format(($quote->price_cents ?? 0) / 100, 2) }} €</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ $quote->item?->description }}</p>
                            <p class="mt-1 text-xs text-slate-400">
                                Chantier {{ $quote->item?->bundle?->code }} ·
                                Répondre avant {{ optional($quote->valid_until)->format('d/m/Y H:i') }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button type="button" wire:click="select({{ $quote->id }})"
                                class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                {{ $quote->status === 'submitted' ? 'Modifier' : 'Proposer un prix' }}
                            </button>
                            <button type="button" wire:click="withdraw({{ $quote->id }})"
                                wire:confirm="Retirer ce devis ?"
                                class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
                                Retirer
                            </button>
                        </div>
                    </div>

                    @if ($selectedId === $quote->id)
                        <div class="mt-4 border-t border-slate-200 pt-4">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600">Votre prix (€)</label>
                                    <input type="number" step="0.01" min="0" wire:model="price"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm" placeholder="0.00">
                                    @error('price') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-slate-600">Message (optionnel)</label>
                                    <input type="text" wire:model="message"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm" placeholder="Disponibilité, précisions…">
                                </div>
                            </div>
                            <div class="mt-3 flex justify-end">
                                <button type="button" wire:click="submit"
                                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                    Envoyer mon devis
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $requests->links() }}</div>
    @endif
</div>
