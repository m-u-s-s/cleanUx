<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Finance client</p>
                <h1 class="text-2xl font-black text-slate-900">Crédits, avoirs et gestes commerciaux</h1>
                <p class="text-sm text-slate-500">
                    Ajoutez un crédit client après un litige, un retard ou un geste commercial.
                </p>
            </div>

            <a href="{{ route('admin.dashboard') }}"
               class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                ← Dashboard
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 rounded-2xl border bg-white p-6 shadow-sm space-y-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Ajouter un crédit</h2>
                    <p class="text-sm text-slate-500">Le client verra ce crédit dans son portefeuille.</p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Client</label>
                    <select wire:model.live="client_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                        <option value="">Choisir un client</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">
                                {{ $client->name }} — {{ $client->email }}
                            </option>
                        @endforeach
                    </select>
                    @error('client_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Rendez-vous lié</label>
                    <select wire:model="rendez_vous_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                        <option value="">Aucun</option>
                        @foreach($rendezVous as $rdv)
                            <option value="{{ $rdv->id }}">
                                #{{ $rdv->id }} — {{ $rdv->date?->format('d/m/Y') }} — {{ $rdv->service_display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Type</label>
                    <select wire:model="type" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                        <option value="commercial_gesture">Geste commercial</option>
                        <option value="claim_compensation">Compensation litige</option>
                        <option value="refund_credit">Avoir remboursement</option>
                        <option value="loyalty_reward">Fidélité</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Montant</label>
                    <input type="number" step="0.01" min="1" wire:model="amount"
                           class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                           placeholder="Ex : 15">
                    @error('amount') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Raison</label>
                    <input type="text" wire:model="reason"
                           class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                           placeholder="Ex : Retard intervention">
                    @error('reason') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Expiration</label>
                    <input type="date" wire:model="expires_at"
                           class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Note interne</label>
                    <textarea wire:model="notes" rows="3"
                              class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                              placeholder="Note visible côté admin uniquement..."></textarea>
                </div>

                <button wire:click="createCredit"
                        wire:loading.attr="disabled"
                        class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-60">
                    <span wire:loading.remove>Ajouter le crédit</span>
                    <span wire:loading>Ajout en cours...</span>
                </button>
            </div>

            <div class="lg:col-span-2 space-y-4">
                <div class="rounded-2xl border bg-white p-4 shadow-sm">
                    <label class="text-sm font-semibold text-slate-700">Recherche</label>
                    <input type="text" wire:model.live.debounce.350ms="search"
                           class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                           placeholder="Nom ou email client...">
                </div>

                <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
                    <div class="p-5 border-b">
                        <h2 class="font-bold text-slate-900">Historique des crédits</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-4 py-3 text-left">Client</th>
                                    <th class="px-4 py-3 text-left">Type</th>
                                    <th class="px-4 py-3 text-left">Raison</th>
                                    <th class="px-4 py-3 text-left">Montant</th>
                                    <th class="px-4 py-3 text-left">Restant</th>
                                    <th class="px-4 py-3 text-left">Statut</th>
                                    <th class="px-4 py-3 text-left">Action</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y">
                                @forelse($credits as $credit)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-slate-900">{{ $credit->client?->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $credit->client?->email }}</p>
                                        </td>

                                        <td class="px-4 py-3">
                                            {{ str_replace('_', ' ', ucfirst($credit->type)) }}
                                        </td>

                                        <td class="px-4 py-3">
                                            {{ $credit->reason ?? '—' }}
                                        </td>

                                        <td class="px-4 py-3 font-semibold">
                                            {{ number_format($credit->amount, 2, ',', ' ') }} €
                                        </td>

                                        <td class="px-4 py-3 font-semibold text-indigo-700">
                                            {{ number_format($credit->remaining_amount, 2, ',', ' ') }} €
                                        </td>

                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-3 py-1 text-xs font-bold
                                                {{ $credit->status === 'active'
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-600' }}">
                                                {{ ucfirst($credit->status) }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-3">
                                            @if($credit->status === 'active')
                                                <button wire:click="cancelCredit({{ $credit->id }})"
                                                        class="text-xs font-semibold text-red-600 hover:underline">
                                                    Annuler
                                                </button>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                            Aucun crédit client.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4">
                        {{ $credits->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>