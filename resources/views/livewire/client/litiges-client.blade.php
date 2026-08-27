<div class="py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div>
            <p class="ui-page-eyebrow !mt-0">Réclamations</p>
            <h1 class="ui-page-title">Mon SAV</h1>
            <p class="ui-page-subtitle">Ouvrez une réclamation et suivez son traitement.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Form nouvelle réclamation --}}
            <div class="lg:col-span-1 rounded-2xl border border-slate-200/80 bg-white shadow-soft p-5 space-y-3">
                <h2 class="inline-flex items-center gap-2 text-base font-bold text-slate-900">
                    <x-ui.icon name="plus" class="w-4 h-4 text-brand-600" />
                    Nouvelle réclamation
                </h2>

                <div>
                    <label class="ui-label" for="booking_id">Booking lié <span class="text-slate-400 normal-case">(optionnel)</span></label>
                    <select id="booking_id" wire:model="booking_id" class="ui-input">
                        <option value="">— Aucun</option>
                        @foreach($rendezVous as $b)
                            <option value="{{ $b->id }}">#{{ $b->booking_reference }} — {{ $b->date?->format('d/m/Y') }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="ui-label" for="category">Catégorie</label>
                    <select id="category" wire:model="category" class="ui-input">
                        <option value="quality">Qualité de service</option>
                        <option value="no_show">Provider absent</option>
                        <option value="payment">Paiement / facturation</option>
                        <option value="damage">Dommage matériel</option>
                        <option value="safety">Sécurité</option>
                        <option value="communication">Communication</option>
                        <option value="other">Autre</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="ui-label" for="priority">Priorité</label>
                        <select id="priority" wire:model="priority" class="ui-input">
                            <option value="low">Basse</option>
                            <option value="normal">Normale</option>
                            <option value="high">Haute</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label class="ui-label" for="severity">Gravité</label>
                        <select id="severity" wire:model="severity" class="ui-input">
                            <option value="low">Faible</option>
                            <option value="medium">Modérée</option>
                            <option value="high">Élevée</option>
                            <option value="critical">Critique</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="ui-label" for="subject">Sujet</label>
                    <input id="subject" type="text" wire:model="subject" class="ui-input" />
                    @error('subject') <p class="ui-error-msg">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="ui-label" for="description">Description</label>
                    <textarea id="description" wire:model="description" rows="4" maxlength="2000" class="ui-input"></textarea>
                    @error('description') <p class="ui-error-msg">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="ui-label inline-flex items-center gap-1" for="photos">
                        <x-ui.icon name="photo" class="w-3.5 h-3.5 text-slate-400" />
                        Photos <span class="text-slate-400 normal-case">(optionnel)</span>
                    </label>
                    <input id="photos" type="file" wire:model="photos" multiple accept="image/*"
                           class="w-full text-xs mt-1.5 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:px-3 file:py-1.5 file:font-semibold file:hover:bg-brand-100 file:transition file:mr-2" />
                </div>

                {{--
                    `createClaim` ET NON `openClaim` : la seconde n'existe pas sur le composant.
                    Le bouton paraissait normal et rendait 500 au clic — « Unable to call
                    component method » — si bien qu'aucun client ne pouvait déposer de
                    réclamation depuis cet écran. La méthode appelée ici valide, range les
                    photos et crée la réclamation ; c'est bien ce que le bouton promet.
                --}}
                <button wire:click="createClaim" class="brio-btn-primary w-full inline-flex items-center justify-center gap-2">
                    <x-ui.icon name="envelope" class="w-4 h-4" />
                    Envoyer la réclamation
                </button>
            </div>

            {{-- Liste + détail --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="rounded-2xl border border-slate-200/80 bg-white shadow-soft overflow-hidden">
                    <div class="p-3 border-b border-slate-100 flex items-center justify-between gap-2">
                        <select wire:model.live="filterStatus" class="ui-input !text-xs !py-1.5 !w-auto">
                            <option value="">Toutes mes réclamations</option>
                            <option value="open">Ouvertes</option>
                            <option value="awaiting_client">Attente de ma réponse</option>
                            <option value="investigating">En cours</option>
                            <option value="resolved">Résolues</option>
                            <option value="closed">Closes</option>
                        </select>
                        <span class="text-xs text-slate-500">
                            {{ $claims->total() }} réclamation{{ $claims->total() > 1 ? 's' : '' }}
                        </span>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @forelse($claims as $c)
                            @php
                                $statusTone = match(true) {
                                    $c->status === 'resolved'                              => ['emerald', 'check-circle'],
                                    in_array($c->status, ['closed', 'open'])               => ['slate', 'circle'],
                                    in_array($c->status, ['assigned', 'investigating'])    => ['brand', 'magnifying-glass'],
                                    in_array($c->status, ['awaiting_client', 'awaiting_provider']) => ['amber', 'clock'],
                                    $c->status === 'escalated'                             => ['rose', 'exclamation-triangle'],
                                    default                                                => ['slate', 'circle'],
                                };
                                [$tone, $icon] = $statusTone;
                            @endphp
                            <button wire:click="select({{ $c->id }})"
                                    class="w-full text-left px-4 py-3 hover:bg-slate-50 transition {{ $selectedId === $c->id ? 'bg-brand-50/50' : '' }}">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ $c->subject }}</p>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-{{ $tone }}-50 border border-{{ $tone }}-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-{{ $tone }}-700 shrink-0">
                                        <x-ui.icon :name="$icon" class="w-3 h-3" />
                                        {{ $c->status }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    <span class="font-mono">{{ $c->reference }}</span>
                                    <span class="text-slate-300">·</span> {{ $c->category }}
                                    @if($c->booking)
                                        <span class="text-slate-300">·</span> #{{ $c->booking->booking_reference }}
                                    @endif
                                </p>
                            </button>
                        @empty
                            <div class="p-10 text-center">
                                <div class="grid h-10 w-10 mx-auto place-items-center rounded-xl bg-slate-100 text-slate-400 mb-2">
                                    <x-ui.icon name="chat-bubble" class="w-5 h-5" />
                                </div>
                                <p class="text-sm text-slate-500">Aucune réclamation.</p>
                            </div>
                        @endforelse
                    </div>

                    @if($claims->hasPages())
                        <div class="p-3 border-t border-slate-100">{{ $claims->links() }}</div>
                    @endif
                </div>

                @if($selected)
                    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-soft overflow-hidden">
                        <div class="p-5 border-b border-slate-100">
                            <p class="font-mono text-xs text-slate-500">{{ $selected->reference }}</p>
                            <h2 class="mt-1 text-lg font-bold tracking-tight text-slate-900">{{ $selected->subject }}</h2>
                            <p class="mt-2 text-sm text-slate-600">{{ $selected->description }}</p>

                            {{--
                              LES PREUVES QUE LE CLIENT A JOINTES. `createClaim()` les stocke sur le
                              disque PRIVE depuis toujours ; aucun ecran ne les rendait.
                            --}}
                            <div class="mt-4">
                                @include('livewire.client.litiges.claim-attachments', ['claim' => $selected])
                            </div>
                        </div>

                        <div class="p-4 space-y-2.5 max-h-96 overflow-y-auto border-b border-slate-100">
                            @forelse($selected->events as $event)
                                @php
                                    $eventConfig = match($event->author_role) {
                                        'system'   => ['slate',   'cog',         'Système'],
                                        'admin'    => ['brand',   'shield-check', 'Support Brio'],
                                        'client'   => ['emerald', 'user',         'Vous'],
                                        'provider' => ['amber',   'wrench',       'Prestataire'],
                                        default    => ['slate',   'user',         'Inconnu'],
                                    };
                                    [$eTone, $eIcon, $eLabel] = $eventConfig;
                                @endphp
                                <div class="rounded-xl bg-{{ $eTone }}-50 border border-{{ $eTone }}-200 p-3">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="inline-flex items-center gap-1.5 font-semibold text-{{ $eTone }}-800">
                                            <x-ui.icon :name="$eIcon" class="w-3 h-3" />
                                            {{ $eLabel }}
                                        </span>
                                        <span class="text-slate-500">{{ $event->created_at->format('d/m H:i') }}</span>
                                    </div>
                                    <p class="mt-1.5 text-sm text-slate-700">{{ $event->body }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-slate-400 text-center py-4">Aucun message.</p>
                            @endforelse
                        </div>

                        @foreach($selected->resolutions as $r)
                            <div class="p-4 border-b border-slate-100">
                                <div class="rounded-xl bg-emerald-50/60 border border-emerald-200 p-4">
                                    <p class="inline-flex items-center gap-1.5 text-xs uppercase font-semibold text-emerald-800">
                                        <x-ui.icon name="check-circle" class="w-3.5 h-3.5" />
                                        Résolution : {{ $r->resolution_type }}
                                    </p>
                                    @if($r->amount)
                                        <p class="mt-2 text-2xl font-bold text-emerald-700">
                                            {{ number_format((float)$r->amount, 2, ',', ' ') }} {{ $r->currency }}
                                        </p>
                                    @endif
                                    @if($r->explanation)
                                        <p class="mt-2 text-sm text-slate-700">{{ $r->explanation }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        @if(! in_array($selected->status, ['resolved','closed']))
                            <div class="p-4 space-y-2">
                                <textarea wire:model="replyBody" rows="2" maxlength="2000"
                                          class="ui-input"
                                          placeholder="Votre réponse…"></textarea>
                                @error('replyBody') <p class="ui-error-msg">{{ $message }}</p> @enderror
                                <button wire:click="postReply" class="brio-btn-primary inline-flex items-center gap-2">
                                    <x-ui.icon name="envelope" class="w-4 h-4" />
                                    Envoyer la réponse
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
