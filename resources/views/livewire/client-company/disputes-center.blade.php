<div class="mx-auto max-w-5xl px-4 py-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Litiges</h1>
            <p class="text-sm text-slate-600">Suivez et ouvrez les litiges de vos sites.</p>
        </div>
        <button type="button" wire:click="$toggle('showForm')"
            class="rounded-xl bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700">
            {{ $showForm ? 'Fermer' : '＋ Ouvrir un litige' }}
        </button>
    </div>

    @if ($showForm)
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Réservation concernée</label>
                    <select wire:model="bookingId" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                        <option value="">— Choisir —</option>
                        @foreach ($orgBookings as $b)
                            <option value="{{ $b->id }}">{{ $b->booking_reference ?? ('#'.$b->id) }}</option>
                        @endforeach
                    </select>
                    @error('bookingId') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Catégorie</label>
                    <select wire:model="category" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                        <option value="quality">Qualité</option>
                        <option value="no_show">Absence prestataire</option>
                        <option value="damage">Dégâts</option>
                        <option value="payment">Paiement</option>
                        <option value="other">Autre</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <label class="block text-xs font-medium text-slate-600">Sujet</label>
                <input type="text" wire:model="subject" class="mt-1 w-full rounded-xl border-slate-200 text-sm" placeholder="Résumé du problème">
                @error('subject') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="mt-3">
                <label class="block text-xs font-medium text-slate-600">Description</label>
                <textarea wire:model="description" rows="3" class="mt-1 w-full rounded-xl border-slate-200 text-sm" placeholder="Détaillez le problème…"></textarea>
                @error('description') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="mt-3 flex justify-end">
                <button type="button" wire:click="openDispute"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Ouvrir le litige
                </button>
            </div>
        </div>
    @endif

    @if ($disputes->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center">
            <div class="text-4xl">✅</div>
            <p class="mt-2 font-semibold text-slate-900">Aucun litige en cours</p>
            <p class="text-sm text-slate-600">Les litiges ouverts pour vos sites apparaîtront ici.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Référence</th>
                        <th class="px-4 py-2">Réservation</th>
                        <th class="px-4 py-2">Sujet</th>
                        <th class="px-4 py-2">Statut</th>
                        <th class="px-4 py-2">SLA</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($disputes as $dispute)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $dispute->reference }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $dispute->booking?->booking_reference ?? '—' }}</td>
                            <td class="px-4 py-2 text-slate-800">{{ $dispute->subject }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ $dispute->status }}</span>
                            </td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ optional($dispute->due_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $disputes->links() }}</div>
    @endif
</div>
