<div class="py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Réclamations</p>
            <h1 class="text-3xl font-black text-slate-900">Mon SAV</h1>
            <p class="text-sm text-slate-500 mt-2">Ouvrez une réclamation et suivez son traitement.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Form nouvelle réclamation --}}
            <div class="lg:col-span-1 rounded-2xl border bg-white p-6 shadow-sm space-y-3">
                <h2 class="text-lg font-bold">Nouvelle réclamation</h2>

                <div>
                    <label class="text-xs font-bold uppercase text-slate-500">Booking lié (optionnel)</label>
                    <select wire:model="booking_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                        <option value="">— Aucun</option>
                        @foreach($rendezVous as $b)
                            <option value="{{ $b->id }}">#{{ $b->booking_reference }} — {{ $b->date?->format('d/m/Y') }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-xs font-bold uppercase text-slate-500">Catégorie</label>
                    <select wire:model="category" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                        <option value="quality">Qualité de service</option>
                        <option value="no_show">Provider absent (no-show)</option>
                        <option value="payment">Paiement / facturation</option>
                        <option value="damage">Dommage matériel</option>
                        <option value="safety">Sécurité</option>
                        <option value="communication">Communication</option>
                        <option value="other">Autre</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Priorité</label>
                        <select wire:model="priority" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                            <option value="low">Basse</option>
                            <option value="normal">Normale</option>
                            <option value="high">Haute</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Gravité</label>
                        <select wire:model="severity" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                            <option value="low">Faible</option>
                            <option value="medium">Modérée</option>
                            <option value="high">Élevée</option>
                            <option value="critical">Critique</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold uppercase text-slate-500">Sujet</label>
                    <input type="text" wire:model="subject" class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    @error('subject') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-xs font-bold uppercase text-slate-500">Description</label>
                    <textarea wire:model="description" rows="4" maxlength="2000"
                              class="mt-1 w-full rounded-xl border-gray-300 text-sm"></textarea>
                    @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-xs font-bold uppercase text-slate-500">Photos (optionnel)</label>
                    <input type="file" wire:model="photos" multiple accept="image/*"
                           class="mt-1 w-full text-xs" />
                </div>

                <button wire:click="openClaim"
                        class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    Envoyer la réclamation
                </button>
            </div>

            {{-- Liste + détail --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
                    <div class="p-3 border-b">
                        <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-xs">
                            <option value="">Toutes mes réclamations</option>
                            <option value="open">Ouvertes</option>
                            <option value="awaiting_client">Attente de ma réponse</option>
                            <option value="investigating">En cours</option>
                            <option value="resolved">Résolues</option>
                            <option value="closed">Closes</option>
                        </select>
                    </div>
                    <div class="divide-y">
                        @forelse($claims as $c)
                            <button wire:click="select({{ $c->id }})"
                                    class="w-full text-left p-4 hover:bg-slate-50 {{ $selectedId === $c->id ? 'bg-indigo-50' : '' }}">
                                <div class="flex items-center justify-between">
                                    <p class="font-bold text-slate-900">{{ $c->subject }}</p>
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $c->status === 'resolved',
                                        'bg-slate-100 text-slate-700' => in_array($c->status, ['closed','open']),
                                        'bg-indigo-100 text-indigo-800' => in_array($c->status, ['assigned','investigating']),
                                        'bg-amber-100 text-amber-800' => in_array($c->status, ['awaiting_client','awaiting_provider']),
                                        'bg-red-100 text-red-800' => $c->status === 'escalated',
                                    ])>{{ $c->status }}</span>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">
                                    {{ $c->reference }} · {{ $c->category }}
                                    @if($c->booking) · #{{ $c->booking->booking_reference }} @endif
                                </p>
                            </button>
                        @empty
                            <div class="p-10 text-center text-slate-400">Aucune réclamation.</div>
                        @endforelse
                    </div>
                    <div class="p-3">{{ $claims->links() }}</div>
                </div>

                @if($selected)
                    <div class="rounded-2xl border bg-white shadow-sm">
                        <div class="p-4 border-b">
                            <p class="font-mono text-xs text-slate-500">{{ $selected->reference }}</p>
                            <h2 class="text-xl font-black text-slate-900 mt-1">{{ $selected->subject }}</h2>
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
                                            @if($event->author_role === 'admin') 🛡 Support CleanUx
                                            @elseif($event->author_role === 'client') Vous
                                            @elseif($event->author_role === 'provider') Prestataire
                                            @else Système
                                            @endif
                                        </span>
                                        <span class="text-slate-500">{{ $event->created_at->format('d/m H:i') }}</span>
                                    </div>
                                    <p class="text-slate-700 mt-1">{{ $event->body }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-slate-400 text-center">Aucun message.</p>
                            @endforelse
                        </div>

                        @foreach($selected->resolutions as $r)
                            <div class="p-4 border-b">
                                <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4">
                                    <p class="text-xs uppercase font-bold text-emerald-800">Résolution : {{ $r->resolution_type }}</p>
                                    @if($r->amount)
                                        <p class="text-2xl font-black text-emerald-700 mt-1">
                                            {{ number_format((float)$r->amount, 2, ',', ' ') }} {{ $r->currency }}
                                        </p>
                                    @endif
                                    @if($r->explanation)
                                        <p class="text-sm text-slate-700 mt-2">{{ $r->explanation }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        @if(! in_array($selected->status, ['resolved','closed']))
                            <div class="p-4 space-y-2">
                                <textarea wire:model="replyBody" rows="2" maxlength="2000"
                                          class="w-full rounded-xl border-gray-300 text-sm"
                                          placeholder="Votre réponse..."></textarea>
                                @error('replyBody') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                <button wire:click="postReply"
                                        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                    Envoyer
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
