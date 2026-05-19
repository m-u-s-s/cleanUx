<div class="py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Litiges</p>
            <h1 class="text-3xl font-black text-slate-900">Litiges vous concernant</h1>
            <p class="text-sm text-slate-500 mt-2">Répondez aux réclamations clients pour résoudre rapidement.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 rounded-2xl border bg-white shadow-sm overflow-hidden">
                <div class="p-3 border-b">
                    <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-xs">
                        <option value="">Tous statuts</option>
                        <option value="open">Ouverts</option>
                        <option value="awaiting_provider">Attente de ma réponse</option>
                        <option value="investigating">En cours</option>
                        <option value="resolved">Résolus</option>
                    </select>
                </div>
                <div class="divide-y">
                    @forelse($list as $c)
                        <button wire:click="select({{ $c->id }})"
                                class="w-full text-left p-4 hover:bg-slate-50 {{ $selectedId === $c->id ? 'bg-indigo-50' : '' }}">
                            <div class="flex items-center justify-between">
                                <p class="font-bold text-slate-900 text-sm">{{ $c->subject }}</p>
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $c->status === 'resolved',
                                    'bg-slate-100 text-slate-700' => in_array($c->status, ['closed','open']),
                                    'bg-amber-100 text-amber-800' => $c->status === 'awaiting_provider',
                                    'bg-red-100 text-red-800' => $c->status === 'escalated',
                                ])>{{ $c->status }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                {{ $c->client?->name ?? '—' }} · {{ $c->category }}
                                @if($c->booking) · #{{ $c->booking->booking_reference }} @endif
                            </p>
                        </button>
                    @empty
                        <div class="p-10 text-center text-slate-400">Aucun litige.</div>
                    @endforelse
                </div>
                <div class="p-3">{{ $list->links() }}</div>
            </div>

            <div class="lg:col-span-2">
                @if($selected)
                    <div class="rounded-2xl border bg-white shadow-sm">
                        <div class="p-4 border-b">
                            <p class="font-mono text-xs text-slate-500">{{ $selected->reference }}</p>
                            <h2 class="text-xl font-black mt-1">{{ $selected->subject }}</h2>
                            <p class="text-sm text-slate-600 mt-2">{{ $selected->description }}</p>
                        </div>

                        <div class="p-4 space-y-3 max-h-96 overflow-y-auto border-b">
                            @forelse($selected->events as $event)
                                <div @class([
                                    'rounded-xl p-3 border text-sm',
                                    'bg-slate-50 border-slate-200' => $event->author_role === 'system',
                                    'bg-indigo-50 border-indigo-200' => $event->author_role === 'admin',
                                    'bg-emerald-50 border-emerald-200' => $event->author_role === 'client',
                                    'bg-amber-50 border-amber-200' => $event->author_role === 'provider',
                                ])>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-semibold">
                                            @if($event->author_role === 'admin') 🛡 Support
                                            @elseif($event->author_role === 'provider') Vous
                                            @elseif($event->author_role === 'client') Client
                                            @else Système
                                            @endif
                                        </span>
                                        <span class="text-slate-500">{{ $event->created_at->format('d/m H:i') }}</span>
                                    </div>
                                    <p class="text-slate-700 mt-1">{{ $event->body }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-slate-400 text-center">Aucun message visible.</p>
                            @endforelse
                        </div>

                        @if(! in_array($selected->status, ['resolved','closed']))
                            <div class="p-4 space-y-2">
                                <textarea wire:model="responseBody" rows="3" maxlength="2000"
                                          class="w-full rounded-xl border-gray-300 text-sm"
                                          placeholder="Votre version des faits..."></textarea>
                                @error('responseBody') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                <button wire:click="postResponse"
                                        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">
                                    Envoyer ma réponse au support
                                </button>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rounded-2xl border-2 border-dashed border-slate-200 p-10 text-center text-slate-400">
                        Sélectionnez un litige pour voir le détail.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
