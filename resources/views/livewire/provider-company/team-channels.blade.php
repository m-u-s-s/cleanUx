<div class="flex h-[calc(100vh-9rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white">

    {{-- ══════════════════════════════════════════════
         SIDEBAR GAUCHE — liste des canaux (style Discord)
    ══════════════════════════════════════════════ --}}
    <aside class="flex w-60 flex-shrink-0 flex-col bg-white">

        {{-- Header org --}}
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 shadow">
            <div class="flex items-center gap-2 min-w-0">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-600 text-sm font-black">
                    {{ str(Auth::user()->currentOrganization?->name)->substr(0, 2)->upper() }}
                </div>
                <span class="truncate text-sm font-bold text-slate-900">
                    {{ Auth::user()->currentOrganization?->name }}
                </span>
            </div>
        </div>

        {{-- Canaux --}}
        <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-0.5">

            @php
            $grouped = $channels->groupBy('type');
            $typeLabels = [
            'announcement' => 'Annonces',
            'team' => 'Équipe',
            'mission' => 'Missions',
            'support' => 'Support',
            'private' => 'Privés',
            ];
            $typeIcons = [
            'announcement' => '📢',
            'team' => '👥',
            'mission' => '🗺️',
            'support' => '🛟',
            'private' => '🔒',
            ];
            @endphp

            @foreach ($typeLabels as $type => $label)
            @if ($grouped->has($type))
            <div class="mb-1">
                <div class="flex items-center justify-between px-2 py-1">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                        {{ $typeIcons[$type] ?? '' }} {{ $label }}
                    </span>
                </div>

                @foreach ($grouped[$type] as $channel)
                <button
                    wire:click="openChannel({{ $channel->id }})"
                    class="group flex w-full items-center justify-between rounded-md px-2 py-1.5 text-sm transition
                                    {{ $activeChannelId === $channel->id
                                        ? 'bg-slate-600 text-slate-900 font-medium'
                                        : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700' }}">
                    <span class="flex items-center gap-1.5 min-w-0">
                        <span class="text-slate-400">#</span>
                        <span class="truncate">{{ $channel->name }}</span>
                    </span>

                    @if ($channel->unread_count > 0)
                    <span class="flex-shrink-0 rounded-full bg-blue-600 px-1.5 py-0.5 text-[10px] font-bold text-white">
                        {{ $channel->unread_count > 99 ? '99+' : $channel->unread_count }}
                    </span>
                    @endif
                </button>
                @endforeach
            </div>
            @endif
            @endforeach
        </nav>

        {{-- Créer un canal --}}
        <div class="border-t border-slate-200 p-2">
            <button
                wire:click="$set('showNewChannel', true)"
                class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-sm text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                <span class="text-lg leading-none">+</span>
                <span>Nouveau canal</span>
            </button>
        </div>

        {{-- Profil utilisateur --}}
        <div class="flex items-center gap-2 border-t border-slate-200 bg-white px-3 py-2">
            <img src="{{ Auth::user()->profile_photo_url }}"
                alt="{{ Auth::user()->name }}"
                class="h-8 w-8 rounded-full object-cover">
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-semibold text-slate-900">{{ Auth::user()->name }}</p>
                <p class="truncate text-[10px] text-slate-500">
                    {{ Auth::user()->membershipIn()?->roleLabel() }}
                </p>
            </div>
        </div>
    </aside>

    {{-- ══════════════════════════════════════════════
         ZONE PRINCIPALE
    ══════════════════════════════════════════════ --}}
    <div class="flex flex-1 flex-col overflow-hidden">

        @if ($activeChannel)

        {{-- Header du canal --}}
        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="text-lg text-slate-500">#</span>
                <div>
                    <p class="font-bold text-slate-900">{{ $activeChannel->name }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $activeChannel->members_count ?? $activeChannel->members->count() }} membres
                        • {{ $activeChannel->type }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                {{-- Avatars des membres --}}
                <div class="flex -space-x-2">
                    @foreach ($activeChannel->members->take(5) as $member)
                    <img src="{{ $member->profile_photo_url }}"
                        alt="{{ $member->name }}"
                        title="{{ $member->name }}"
                        class="h-7 w-7 rounded-full border-2 border-slate-100 object-cover">
                    @endforeach
                    @if ($activeChannel->members->count() > 5)
                    <div class="flex h-7 w-7 items-center justify-center rounded-full border-2 border-slate-100 bg-slate-600 text-[10px] font-bold text-slate-900">
                        +{{ $activeChannel->members->count() - 5 }}
                    </div>
                    @endif
                </div>

                {{-- Porte d'entrée du panneau « Membres » : sans elle, ajouter quelqu'un resterait
                     une capacité sans accès, donc une capacité inexistante. --}}
                <button type="button"
                    wire:click="toggleMembersPanel"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                    aria-expanded="{{ $showMembersPanel ? 'true' : 'false' }}">
                    Membres
                </button>
            </div>
        </div>

        {{-- Panneau de gestion des membres du canal --}}
        @if ($showMembersPanel)
        <div class="border-b border-slate-200 bg-white px-4 py-3">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Dans ce canal</p>
                    <ul class="space-y-1">
                        @foreach ($activeChannel->members as $member)
                        <li class="flex items-center justify-between gap-2 rounded-lg bg-white/50 px-3 py-1.5">
                            <span class="truncate text-sm text-slate-700">{{ $member->name }}</span>
                            @can('kickMember', [$activeChannel, $member])
                            <button type="button"
                                wire:click="removeChannelMember({{ $activeChannel->id }}, {{ $member->id }})"
                                class="shrink-0 text-xs font-semibold text-slate-500 hover:text-slate-900">
                                Retirer
                            </button>
                            @endcan
                        </li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Ajouter un coéquipier</p>
                    @forelse ($coequipiersAjoutables as $coequipier)
                    <div class="mb-1 flex items-center justify-between gap-2 rounded-lg bg-white/50 px-3 py-1.5">
                        <span class="truncate text-sm text-slate-700">{{ $coequipier->name }}</span>
                        <div class="flex shrink-0 items-center gap-3">
                            {{--
                                LA CONVERSATION À DEUX. Le type `private` existait depuis le début
                                et rien ne permettait d'en ouvrir une : pour dire un mot à quelqu'un
                                il fallait créer un canal nommé, ce que personne ne fait. Les équipes
                                passaient par WhatsApp — hors de l'outil et hors de toute trace.
                            --}}
                            <button type="button"
                                wire:click="ouvrirConversationDirecte({{ $coequipier->id }})"
                                title="Conversation privée"
                                class="text-xs font-semibold text-slate-500 hover:text-slate-700">
                                Message
                            </button>
                            <button type="button"
                                wire:click="addChannelMember({{ $activeChannel->id }}, {{ $coequipier->id }})"
                                class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                                Ajouter
                            </button>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-slate-400">Toute l'équipe est déjà dans ce canal.</p>
                    @endforelse
                </div>
            </div>
        </div>
        @endif

        {{-- Messages --}}
        <div
            id="messages-container"
            class="flex flex-1 flex-col-reverse overflow-y-auto px-4 py-4"
            wire:poll.15s="loadMessages">
            <div class="space-y-1">
                @php $lastSenderId = null; $lastDate = null; @endphp

                @foreach ($messages as $msg)

                {{-- Séparateur de date --}}
                @if ($lastDate !== $msg['date'])
                <div class="my-4 flex items-center gap-3">
                    <div class="flex-1 border-t border-slate-200"></div>
                    <span class="text-[11px] text-slate-400">{{ $msg['date'] }}</span>
                    <div class="flex-1 border-t border-slate-200"></div>
                </div>
                @php $lastSenderId = null; @endphp
                @endif
                @php $lastDate = $msg['date']; @endphp

                {{-- Message système --}}
                @if ($msg['is_system'])
                <div class="flex items-center gap-2 py-1 px-2">
                    <span class="text-xs italic text-slate-400">
                        <div class="prose prose-sm max-w-none">
                            {!! $msg['content_html'] !!}
                        </div>

                        {{-- Indicateur épinglé --}}
                        @if ($msg['is_pinned'])
                        <div class="mt-1 inline-flex items-center gap-1 text-xs text-amber-700">
                            📌 Épinglé
                        </div>
                        @endif

                        {{-- Boutons modération (visibles aux mods+) --}}
                        @can('pinMessage', $msg['id'])
                        <button wire:click="{{ $msg['is_pinned'] ? 'unpinMessage' : 'pinMessage' }}({{ $msg['id'] }})">
                            {{ $msg['is_pinned'] ? 'Désépingler' : 'Épingler' }}
                        </button>
                        @endcan

                        {{-- Avertissement attachment infecté --}}
                        @foreach ($msg['attachments'] as $att)
                        @if ($att['is_infected'])
                        <div class="mt-1 rounded bg-red-100 p-2 text-xs text-red-800">
                            ⚠ Ce fichier a été identifié comme dangereux et n'est plus disponible.
                        </div>
                        @elseif (! $att['is_ready'])
                        <div class="mt-1 rounded bg-yellow-100 p-2 text-xs text-yellow-800">
                            ⏳ Analyse antivirus en cours…
                        </div>
                        @else
                        {{-- rendu normal de l'attachment --}}
                        @endif
                        @endforeach
                    </span>
                </div>
                @php $lastSenderId = null; @endphp
                @continue
                @endif

                {{-- Groupe ou message seul --}}
                @php $showHeader = $lastSenderId !== $msg['sender_id']; @endphp

                <div
                    class="group relative flex gap-3 rounded-lg px-2 py-0.5 hover:bg-slate-50
                                {{ $showHeader ? 'mt-3' : '' }}"
                    x-data="{ showActions: false }"
                    @mouseenter="showActions = true"
                    @mouseleave="showActions = false">
                    {{-- Avatar --}}
                    <div class="w-10 flex-shrink-0">
                        @if ($showHeader)
                        <img src="{{ $msg['avatar'] }}"
                            alt="{{ $msg['sender'] }}"
                            class="h-9 w-9 rounded-full object-cover">
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        {{-- Nom et heure --}}
                        @if ($showHeader)
                        <div class="flex items-baseline gap-2">
                            <span class="text-sm font-semibold text-slate-900">{{ $msg['sender'] }}</span>
                            <span class="text-[10px] text-slate-400">{{ $msg['time'] }}</span>
                        </div>
                        @endif

                        {{-- Réponse à --}}
                        @if ($msg['reply_to'])
                        <div class="mb-1 flex items-center gap-1 text-xs text-slate-400">
                            <span class="text-blue-600">↩ {{ $msg['reply_to']['sender'] }}</span>
                            <span class="truncate">{{ $msg['reply_to']['content'] }}</span>
                        </div>
                        @endif

                        {{-- Contenu ou édition --}}
                        @if ($editingMessageId === $msg['id'])
                        <div class="mt-1 flex gap-2">
                            <input
                                wire:model="editContent"
                                wire:keydown.enter="saveEdit"
                                wire:keydown.escape="cancelEdit"
                                class="flex-1 rounded-lg border border-blue-500 bg-slate-100 px-3 py-1.5 text-sm text-slate-900 outline-none focus:ring-2 focus:ring-blue-500">
                            <button wire:click="saveEdit"
                                class="rounded-lg bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">
                                Sauver
                            </button>
                            <button wire:click="cancelEdit"
                                class="rounded-lg bg-slate-600 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-500">
                                Annuler
                            </button>
                        </div>
                        @else
                        @if ($msg['is_voice'])
                            {{--
                                LE LECTEUR NATIF DU NAVIGATEUR plutôt qu'un lecteur maison : il gère
                                déjà la pause, le déplacement dans la piste et les raccourcis
                                clavier, et il est annoncé correctement par les lecteurs d'écran.
                                L'adresse est celle de la pièce jointe, signée et expirante.
                            --}}
                            @php $sonVocal = collect($msg['attachments'])->first(fn ($a) => str_starts_with((string) $a['mime'], 'audio/')); @endphp

                            @if ($sonVocal && $sonVocal['is_infected'])
                                <p class="text-sm text-red-700">⚠ Cette note vocale a été identifiée comme dangereuse.</p>
                            @elseif ($sonVocal && ! $sonVocal['is_ready'])
                                <p class="text-sm text-amber-700">⏳ Analyse antivirus en cours…</p>
                            @elseif ($sonVocal)
                                <div class="flex items-center gap-2">
                                    <audio controls preload="none" src="{{ $sonVocal['download_url'] }}" class="h-9 max-w-full">
                                        Votre navigateur ne sait pas lire l’audio.
                                    </audio>
                                    @if ($msg['duration'])
                                    <span class="text-[11px] text-slate-400">{{ $msg['duration'] }} s</span>
                                    @endif
                                </div>
                            @else
                                <p class="text-sm text-slate-500">🎙️ Note vocale indisponible.</p>
                            @endif
                        @else
                        <p class="text-sm leading-relaxed text-slate-700">
                            {{ $msg['content'] }}
                            @if ($msg['is_edited'])
                            <span class="text-[10px] text-slate-400">(modifié)</span>
                            @endif
                        </p>
                        @endif
                        @endif

                        {{-- Réactions --}}
                        @if (!empty($msg['reactions']))
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($msg['reactions'] as $reaction)
                            <button
                                wire:click="toggleReaction({{ $msg['id'] }}, '{{ $reaction['emoji'] }}')"
                                class="flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition
                                                    {{ $reaction['mine']
                                                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                                                        : 'border-slate-300 bg-white text-slate-600 hover:border-slate-400' }}">
                                {{ $reaction['emoji'] }}
                                <span>{{ $reaction['count'] }}</span>
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Actions au survol --}}
                    <div
                        x-show="showActions"
                        x-cloak
                        class="absolute -top-3 right-2 flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-1 py-0.5 shadow-lg">
                        @foreach (['👍', '✅', '🔥', '👀'] as $emoji)
                        <button
                            wire:click="toggleReaction({{ $msg['id'] }}, '{{ $emoji }}')"
                            class="rounded p-1 text-sm hover:bg-slate-100"
                            title="{{ $emoji }}">{{ $emoji }}</button>
                        @endforeach
                        <div class="mx-1 w-px bg-slate-600 self-stretch"></div>
                        <button
                            wire:click="setReplyTo({{ $msg['id'] }})"
                            class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                            title="Répondre">↩</button>
                        @if ($msg['is_mine'])
                        <button
                            wire:click="startEdit({{ $msg['id'] }})"
                            class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                            title="Modifier">✏️</button>
                        <button
                            wire:click="deleteMessage({{ $msg['id'] }})"
                            wire:confirm="Supprimer ce message ?"
                            class="rounded p-1 text-slate-500 hover:bg-red-50 hover:text-red-600"
                            title="Supprimer">🗑️</button>
                        @endif
                    </div>
                </div>

                @php $lastSenderId = $msg['sender_id']; @endphp
                @endforeach
            </div>
        </div>

        {{-- Barre de réponse --}}
        @if ($replyingToId)
        <div class="flex items-center gap-2 border-t border-slate-200 bg-white px-4 py-2">
            <span class="text-xs text-blue-600">↩ Réponse à un message</span>
            <button wire:click="setReplyTo(null)" class="ml-auto text-slate-500 hover:text-slate-900">✕</button>
        </div>
        @endif

        {{-- Zone de saisie --}}
        <div class="border-t border-slate-200 bg-white px-4 py-3">
            <div class="flex items-end gap-3 rounded-xl border border-slate-300 bg-slate-100 px-3 py-2 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500/50">
                <textarea
                    wire:model="messageInput"
                    wire:keydown.enter.prevent="sendMessage"
                    placeholder="Message dans #{{ $activeChannel->name }}"
                    rows="1"
                    class="flex-1 resize-none bg-transparent text-sm text-slate-900 placeholder-slate-400 outline-none"
                    style="max-height: 120px"
                    x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"></textarea>
                {{--
                    ENREGISTRER UNE NOTE DEPUIS LE NAVIGATEUR.

                    Le terrain enregistrait depuis son téléphone, le bureau ne pouvait ni enregistrer
                    ni écouter : la conversation était à sens unique, et une conversation à sens
                    unique se termine sur WhatsApp.

                    `MediaRecorder` est l'API native du navigateur — pas de bibliothèque à charger.
                    Elle produit du `webm` sur Chrome et Firefox, du `mp4` sur Safari ; les deux sont
                    dans la liste blanche du serveur, qui vérifie le type RÉEL du contenu.

                    Le micro n'est demandé qu'au premier appui : réclamer la permission au chargement
                    de la page ferait refuser la moitié des gens par réflexe.
                --}}
                <div
                    x-data="{
                        enregistre: false,
                        recorder: null,
                        morceaux: [],
                        debut: 0,
                        async basculer() {
                            if (this.enregistre) { this.recorder?.stop(); return; }

                            try {
                                const flux = await navigator.mediaDevices.getUserMedia({ audio: true });
                                this.morceaux = [];
                                this.recorder = new MediaRecorder(flux);
                                this.debut = Date.now();

                                this.recorder.ondataavailable = (e) => this.morceaux.push(e.data);

                                this.recorder.onstop = async () => {
                                    // Le micro reste ALLUMÉ tant qu'on ne coupe pas les pistes : la
                                    // pastille rouge du navigateur resterait affichée après l'envoi.
                                    flux.getTracks().forEach((piste) => piste.stop());

                                    const secondes = Math.max(1, Math.round((Date.now() - this.debut) / 1000));
                                    const type = this.recorder.mimeType || 'audio/webm';
                                    const blob = new Blob(this.morceaux, { type });
                                    const extension = type.includes('mp4') ? 'm4a' : 'webm';

                                    this.enregistre = false;
                                    @this.set('dureeNoteVocale', secondes);
                                    await @this.upload('noteVocale', new File([blob], 'note.' + extension, { type }));
                                    @this.call('envoyerNoteVocale');
                                };

                                this.recorder.start();
                                this.enregistre = true;

                                // Trente secondes : au-delà c'est un appel, et les appels existent.
                                setTimeout(() => { if (this.enregistre) this.recorder?.stop(); }, 30000);
                            } catch (e) {
                                this.enregistre = false;
                                window.brioToast({ message: 'Le micro n’est pas accessible depuis ce navigateur.', type: 'error' });
                            }
                        },
                    }"
                    x-show="typeof MediaRecorder !== 'undefined'">
                    <button type="button"
                        x-on:click="basculer()"
                        x-bind:aria-label="enregistre ? 'Arrêter l’enregistrement' : 'Enregistrer une note vocale'"
                        x-bind:class="enregistre ? 'bg-red-100 text-red-700' : 'text-slate-500 hover:bg-slate-200'"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition"
                        data-testid="bouton-note-vocale">
                        <span x-text="enregistre ? '⏹' : '🎙'"></span>
                    </button>
                </div>

                <button
                        aria-label="Envoyer le message"
                    wire:click="sendMessage"
                    class="flex-shrink-0 rounded-lg bg-blue-600 p-1.5 text-white transition hover:bg-blue-700 disabled:opacity-40"
                    :disabled="!$wire.messageInput.trim()">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </button>
            </div>
            <p class="mt-1 text-[10px] text-slate-400">Entrée pour envoyer · Maj+Entrée pour nouvelle ligne</p>
        </div>

        @else
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center text-slate-400">
                <p class="text-4xl mb-3">💬</p>
                <p class="text-sm">Sélectionnez un canal pour commencer</p>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Modal nouveau canal --}}
@if ($showNewChannel)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-2xl bg-white border border-slate-200 p-6 shadow-2xl">
        <h3 class="mb-4 text-lg font-bold text-slate-900">Nouveau canal</h3>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1" for="newChannelName">Nom du canal</label>
                <input id="newChannelName"
                    wire:model="newChannelName"
                    type="text"
                    placeholder="général, missions-bruxelles…"
                    class="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>

            <div>
                <span id="groupe-type-27883" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Type</span>
                <div class="grid grid-cols-2 gap-2" role="group" aria-labelledby="groupe-type-27883">
                    @foreach (['team' => '👥 Équipe', 'mission' => '🗺️ Mission', 'support' => '🛟 Support', 'announcement' => '📢 Annonces'] as $val => $label)
                    <label
                        class="cursor-pointer rounded-lg border px-3 py-2 text-sm transition
                                    {{ $newChannelType === $val
                                        ? 'border-blue-500 bg-blue-900/30 text-blue-700'
                                        : 'border-slate-300 bg-slate-100/50 text-slate-600 hover:border-slate-400' }}">
                        <input type="radio" wire:model="newChannelType" value="{{ $val }}" class="sr-only">
                        {{ $label }}
                    </label>
                    @endforeach
                </div>
            </div>

            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model="isPrivate" class="rounded">
                <span class="text-sm text-slate-600">🔒 Canal privé (sur invitation)</span>
            </label>

            <label class="mt-2 flex cursor-pointer items-center gap-2">
                <input type="checkbox" wire:model="inviteWholeTeam" class="rounded">
                <span class="text-sm text-slate-600">👥 Ajouter toute l'équipe au canal</span>
            </label>
        </div>

        <div class="mt-6 flex gap-3">
            <button wire:click="$set('showNewChannel', false)"
                class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-100">
                Annuler
            </button>
            <button wire:click="createChannel"
                class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                Créer le canal
            </button>
        </div>
    </div>
</div>
@endif