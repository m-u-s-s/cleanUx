<div class="flex h-[calc(100vh-4rem)] overflow-hidden bg-slate-900 text-slate-100">

    {{-- ══════════════════════════════════════════════
         SIDEBAR GAUCHE — liste des canaux (style Discord)
    ══════════════════════════════════════════════ --}}
    <aside class="flex w-60 flex-shrink-0 flex-col bg-slate-800">

        {{-- Header org --}}
        <div class="flex items-center justify-between border-b border-slate-700 px-4 py-3 shadow">
            <div class="flex items-center gap-2 min-w-0">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-600 text-sm font-black">
                    {{ str(Auth::user()->currentOrganization?->name)->substr(0, 2)->upper() }}
                </div>
                <span class="truncate text-sm font-bold text-white">
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
                    'team'         => 'Équipe',
                    'mission'      => 'Missions',
                    'support'      => 'Support',
                    'private'      => 'Privés',
                ];
                $typeIcons = [
                    'announcement' => '📢',
                    'team'         => '👥',
                    'mission'      => '🗺️',
                    'support'      => '🛟',
                    'private'      => '🔒',
                ];
            @endphp

            @foreach ($typeLabels as $type => $label)
                @if ($grouped->has($type))
                    <div class="mb-1">
                        <div class="flex items-center justify-between px-2 py-1">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                {{ $typeIcons[$type] ?? '' }} {{ $label }}
                            </span>
                        </div>

                        @foreach ($grouped[$type] as $channel)
                            <button
                                wire:click="openChannel({{ $channel->id }})"
                                class="group flex w-full items-center justify-between rounded-md px-2 py-1.5 text-sm transition
                                    {{ $activeChannelId === $channel->id
                                        ? 'bg-slate-600 text-white font-medium'
                                        : 'text-slate-400 hover:bg-slate-700 hover:text-slate-200' }}"
                            >
                                <span class="flex items-center gap-1.5 min-w-0">
                                    <span class="text-slate-500">#</span>
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
        <div class="border-t border-slate-700 p-2">
            <button
                wire:click="$set('showNewChannel', true)"
                class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-sm text-slate-400 transition hover:bg-slate-700 hover:text-slate-200"
            >
                <span class="text-lg leading-none">+</span>
                <span>Nouveau canal</span>
            </button>
        </div>

        {{-- Profil utilisateur --}}
        <div class="flex items-center gap-2 border-t border-slate-700 bg-slate-900 px-3 py-2">
            <img src="{{ Auth::user()->profile_photo_url }}"
                 alt="{{ Auth::user()->name }}"
                 class="h-8 w-8 rounded-full object-cover">
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-semibold text-white">{{ Auth::user()->name }}</p>
                <p class="truncate text-[10px] text-slate-400">
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
            <div class="flex items-center justify-between border-b border-slate-700 bg-slate-800 px-4 py-3">
                <div class="flex items-center gap-3">
                    <span class="text-lg text-slate-400">#</span>
                    <div>
                        <p class="font-bold text-white">{{ $activeChannel->name }}</p>
                        <p class="text-xs text-slate-400">
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
                                 class="h-7 w-7 rounded-full border-2 border-slate-800 object-cover">
                        @endforeach
                        @if ($activeChannel->members->count() > 5)
                            <div class="flex h-7 w-7 items-center justify-center rounded-full border-2 border-slate-800 bg-slate-600 text-[10px] font-bold text-white">
                                +{{ $activeChannel->members->count() - 5 }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Messages --}}
            <div
                id="messages-container"
                class="flex flex-1 flex-col-reverse overflow-y-auto px-4 py-4"
                wire:poll.15s="loadMessages"
            >
                <div class="space-y-1">
                    @php $lastSenderId = null; $lastDate = null; @endphp

                    @foreach ($messages as $msg)

                        {{-- Séparateur de date --}}
                        @if ($lastDate !== $msg['date'])
                            <div class="my-4 flex items-center gap-3">
                                <div class="flex-1 border-t border-slate-700"></div>
                                <span class="text-[11px] text-slate-500">{{ $msg['date'] }}</span>
                                <div class="flex-1 border-t border-slate-700"></div>
                            </div>
                            @php $lastSenderId = null; @endphp
                        @endif
                        @php $lastDate = $msg['date']; @endphp

                        {{-- Message système --}}
                        @if ($msg['is_system'])
                            <div class="flex items-center gap-2 py-1 px-2">
                                <span class="text-xs italic text-slate-500">{{ $msg['content'] }}</span>
                            </div>
                            @php $lastSenderId = null; @endphp
                            @continue
                        @endif

                        {{-- Groupe ou message seul --}}
                        @php $showHeader = $lastSenderId !== $msg['sender_id']; @endphp

                        <div
                            class="group relative flex gap-3 rounded-lg px-2 py-0.5 hover:bg-slate-800/60
                                {{ $showHeader ? 'mt-3' : '' }}"
                            x-data="{ showActions: false }"
                            @mouseenter="showActions = true"
                            @mouseleave="showActions = false"
                        >
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
                                        <span class="text-sm font-semibold text-white">{{ $msg['sender'] }}</span>
                                        <span class="text-[10px] text-slate-500">{{ $msg['time'] }}</span>
                                    </div>
                                @endif

                                {{-- Réponse à --}}
                                @if ($msg['reply_to'])
                                    <div class="mb-1 flex items-center gap-1 text-xs text-slate-500">
                                        <span class="text-blue-400">↩ {{ $msg['reply_to']['sender'] }}</span>
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
                                            class="flex-1 rounded-lg border border-blue-500 bg-slate-700 px-3 py-1.5 text-sm text-white outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                        <button wire:click="saveEdit"
                                            class="rounded-lg bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">
                                            Sauver
                                        </button>
                                        <button wire:click="cancelEdit"
                                            class="rounded-lg bg-slate-600 px-3 py-1 text-xs font-medium text-slate-200 hover:bg-slate-500">
                                            Annuler
                                        </button>
                                    </div>
                                @else
                                    <p class="text-sm leading-relaxed text-slate-200">
                                        {{ $msg['content'] }}
                                        @if ($msg['is_edited'])
                                            <span class="text-[10px] text-slate-500">(modifié)</span>
                                        @endif
                                    </p>
                                @endif

                                {{-- Réactions --}}
                                @if (!empty($msg['reactions']))
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach ($msg['reactions'] as $reaction)
                                            <button
                                                wire:click="toggleReaction({{ $msg['id'] }}, '{{ $reaction['emoji'] }}')"
                                                class="flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition
                                                    {{ $reaction['mine']
                                                        ? 'border-blue-500 bg-blue-900/40 text-blue-300'
                                                        : 'border-slate-600 bg-slate-800 text-slate-300 hover:border-slate-500' }}"
                                            >
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
                                class="absolute -top-3 right-2 flex items-center gap-1 rounded-lg border border-slate-600 bg-slate-800 px-1 py-0.5 shadow-lg"
                            >
                                @foreach (['👍', '✅', '🔥', '👀'] as $emoji)
                                    <button
                                        wire:click="toggleReaction({{ $msg['id'] }}, '{{ $emoji }}')"
                                        class="rounded p-1 text-sm hover:bg-slate-700"
                                        title="{{ $emoji }}"
                                    >{{ $emoji }}</button>
                                @endforeach
                                <div class="mx-1 w-px bg-slate-600 self-stretch"></div>
                                <button
                                    wire:click="setReplyTo({{ $msg['id'] }})"
                                    class="rounded p-1 text-slate-400 hover:bg-slate-700 hover:text-white"
                                    title="Répondre"
                                >↩</button>
                                @if ($msg['is_mine'])
                                    <button
                                        wire:click="startEdit({{ $msg['id'] }})"
                                        class="rounded p-1 text-slate-400 hover:bg-slate-700 hover:text-white"
                                        title="Modifier"
                                    >✏️</button>
                                    <button
                                        wire:click="deleteMessage({{ $msg['id'] }})"
                                        wire:confirm="Supprimer ce message ?"
                                        class="rounded p-1 text-slate-400 hover:bg-red-900/40 hover:text-red-400"
                                        title="Supprimer"
                                    >🗑️</button>
                                @endif
                            </div>
                        </div>

                        @php $lastSenderId = $msg['sender_id']; @endphp
                    @endforeach
                </div>
            </div>

            {{-- Barre de réponse --}}
            @if ($replyingToId)
                <div class="flex items-center gap-2 border-t border-slate-700 bg-slate-800/60 px-4 py-2">
                    <span class="text-xs text-blue-400">↩ Réponse à un message</span>
                    <button wire:click="setReplyTo(null)" class="ml-auto text-slate-400 hover:text-white">✕</button>
                </div>
            @endif

            {{-- Zone de saisie --}}
            <div class="border-t border-slate-700 bg-slate-800 px-4 py-3">
                <div class="flex items-end gap-3 rounded-xl border border-slate-600 bg-slate-700 px-3 py-2 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500/50">
                    <textarea
                        wire:model="messageInput"
                        wire:keydown.enter.prevent="sendMessage"
                        placeholder="Message dans #{{ $activeChannel->name }}"
                        rows="1"
                        class="flex-1 resize-none bg-transparent text-sm text-white placeholder-slate-400 outline-none"
                        style="max-height: 120px"
                        x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"
                    ></textarea>
                    <button
                        wire:click="sendMessage"
                        class="flex-shrink-0 rounded-lg bg-blue-600 p-1.5 text-white transition hover:bg-blue-700 disabled:opacity-40"
                        :disabled="!$wire.messageInput.trim()"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </div>
                <p class="mt-1 text-[10px] text-slate-500">Entrée pour envoyer · Maj+Entrée pour nouvelle ligne</p>
            </div>

        @else
            <div class="flex flex-1 items-center justify-center">
                <div class="text-center text-slate-500">
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
        <div class="w-full max-w-md rounded-2xl bg-slate-800 border border-slate-700 p-6 shadow-2xl">
            <h3 class="mb-4 text-lg font-bold text-white">Nouveau canal</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Nom du canal</label>
                    <input
                        wire:model="newChannelName"
                        type="text"
                        placeholder="général, missions-bruxelles…"
                        class="w-full rounded-lg border border-slate-600 bg-slate-700 px-3 py-2 text-sm text-white outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Type</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['team' => '👥 Équipe', 'mission' => '🗺️ Mission', 'support' => '🛟 Support', 'announcement' => '📢 Annonces'] as $val => $label)
                            <label
                                class="cursor-pointer rounded-lg border px-3 py-2 text-sm transition
                                    {{ $newChannelType === $val
                                        ? 'border-blue-500 bg-blue-900/30 text-blue-300'
                                        : 'border-slate-600 bg-slate-700/50 text-slate-300 hover:border-slate-500' }}"
                            >
                                <input type="radio" wire:model="newChannelType" value="{{ $val }}" class="sr-only">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="isPrivate" class="rounded">
                    <span class="text-sm text-slate-300">🔒 Canal privé (sur invitation)</span>
                </label>
            </div>

            <div class="mt-6 flex gap-3">
                <button wire:click="$set('showNewChannel', false)"
                    class="flex-1 rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-300 transition hover:bg-slate-700">
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
