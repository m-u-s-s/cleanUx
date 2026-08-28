<div class="mx-auto max-w-5xl px-4 py-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Litiges</h1>
            <p class="text-sm text-slate-600">Suivez et ouvrez les litiges de vos sites.</p>
        </div>
        <button type="button" wire:click="$toggle('showForm')"
            class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
            {{ $showForm ? 'Fermer' : '＋ Ouvrir un litige' }}
        </button>
    </div>

    @if ($showForm)
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600" for="bookingId">Réservation concernée</label>
                    <select id="bookingId" wire:model="bookingId" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                        <option value="">— Choisir —</option>
                        @foreach ($orgBookings as $b)
                            <option value="{{ $b->id }}">{{ $b->booking_reference ?? ('#'.$b->id) }}</option>
                        @endforeach
                    </select>
                    @error('bookingId') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600" for="category">Catégorie</label>
                    <select id="category" wire:model="category" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                        <option value="quality">Qualité</option>
                        <option value="no_show">Absence prestataire</option>
                        <option value="damage">Dégâts</option>
                        <option value="payment">Paiement</option>
                        <option value="other">Autre</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <label class="block text-xs font-medium text-slate-600" for="subject">Sujet</label>
                <input id="subject" type="text" wire:model="subject" class="mt-1 w-full rounded-xl border-slate-200 text-sm" placeholder="Résumé du problème">
                @error('subject') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="mt-3">
                <label class="block text-xs font-medium text-slate-600" for="description">Description</label>
                <textarea id="description" wire:model="description" rows="3" class="mt-1 w-full rounded-xl border-slate-200 text-sm" placeholder="Détaillez le problème…"></textarea>
                @error('description') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            {{-- Les preuves partent sur le disque PRIVE et ne se relisent que par une URL signee. --}}
            <div class="mt-3">
                <label class="block text-xs font-medium text-slate-600" for="preuves">
                    Photos (facultatif, {{ \App\Support\Disputes\PreuvesDeLitige::NOMBRE_MAX }} maximum)
                </label>
                <input id="preuves" type="file" multiple accept="image/*" wire:model="preuves"
                       class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                @error('preuves.*') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                <div wire:loading wire:target="preuves" class="mt-1 text-xs text-slate-500">Envoi…</div>
            </div>
            <div class="mt-3 flex justify-end">
                <button type="button" wire:click="openDispute"
                    class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50">
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
                <thead class="bg-slate-50 text-left text-xs text-slate-400">
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
                        <tr wire:key="litige-{{ $dispute->id }}"
                            @class(['cursor-pointer hover:bg-slate-50', 'bg-sky-50' => $selectedId === $dispute->id])>
                            <td class="px-4 py-2 font-mono text-xs text-slate-400">
                                <button type="button" wire:click="select({{ $dispute->id }})" class="hover:underline">
                                    {{ $dispute->reference }}
                                </button>
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $dispute->booking?->booking_reference ?? '—' }}</td>
                            <td class="px-4 py-2 text-slate-800">
                                <button type="button" wire:click="select({{ $dispute->id }})" class="text-left hover:underline">
                                    {{ $dispute->subject }}
                                </button>
                            </td>
                            <td class="px-4 py-2">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ $dispute->status }}</span>
                            </td>
                            <td class="px-4 py-2 text-xs text-slate-400">{{ optional($dispute->due_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $disputes->links() }}</div>

        @if ($selected)
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white" wire:key="detail-{{ $selected->id }}">
                <div class="border-b border-slate-100 p-4">
                    <p class="font-mono text-xs text-slate-400">{{ $selected->reference }}</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-900">{{ $selected->subject }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $selected->description }}</p>

                    {{-- Les preuves que la societe a jointes en ouvrant : elle ne les revoyait nulle part. --}}
                    <x-preuves-jointes :fichiers="$selected->attachments ?? []" class="mt-4" />
                </div>

                <div class="max-h-96 space-y-3 overflow-y-auto border-b border-slate-100 p-4">
                    @forelse ($selected->events as $event)
                        <div wire:key="evenement-{{ $event->id }}" @class([
                            'rounded-xl border p-3 text-sm',
                            'border-slate-200 bg-slate-50' => $event->author_role === 'system',
                            'border-indigo-200 bg-indigo-50' => $event->author_role === 'admin',
                            'border-emerald-200 bg-emerald-50' => $event->author_role === 'client',
                            'border-amber-200 bg-amber-50' => $event->author_role === 'provider',
                        ])>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-700">
                                    @if ($event->author_role === 'admin') 🛡 Support
                                    @elseif ($event->author_role === 'client') Votre societe
                                    @elseif ($event->author_role === 'provider') Prestataire
                                    @else Systeme
                                    @endif
                                </span>
                                <span class="text-slate-400">{{ $event->created_at->format('d/m H:i') }}</span>
                            </div>
                            <p class="mt-1 text-slate-700">{{ $event->body }}</p>

                            <x-preuves-jointes :fichiers="$event->attachments ?? []"
                                               titre="Pieces jointes" class="mt-2" />
                        </div>
                    @empty
                        <p class="text-center text-sm text-slate-400">Aucun message visible.</p>
                    @endforelse
                </div>

                @if (! in_array($selected->status, ['resolved', 'closed'], true))
                    <div class="space-y-2 p-4">
                        <label class="block text-xs font-medium text-slate-600" for="responseBody">Votre reponse</label>
                        <textarea id="responseBody" wire:model="responseBody" rows="3" maxlength="2000"
                                  class="w-full rounded-xl border-slate-200 text-sm"
                                  placeholder="Precisez les faits, ajoutez une photo…"></textarea>
                        @error('responseBody') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                        <label class="block text-xs font-medium text-slate-600" for="reponsePreuves">
                            Photos (facultatif, {{ \App\Support\Disputes\PreuvesDeLitige::NOMBRE_MAX }} maximum)
                        </label>
                        <input id="reponsePreuves" type="file" multiple accept="image/*" wire:model="reponsePreuves"
                               class="w-full rounded-xl border-slate-200 text-sm">
                        @error('reponsePreuves.*') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        <div wire:loading wire:target="reponsePreuves" class="text-xs text-slate-500">Envoi…</div>

                        <button type="button" wire:click="postResponse"
                                class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                            Envoyer au support
                        </button>
                    </div>
                @else
                    <p class="p-4 text-sm text-slate-500">Ce litige est cloture : il n'accepte plus de reponse.</p>
                @endif
            </div>
        @endif
    @endif
</div>
