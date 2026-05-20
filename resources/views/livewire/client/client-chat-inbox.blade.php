<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Messagerie</p>
            <h1 class="text-2xl font-black text-slate-900">Mes conversations</h1>
            <p class="text-sm text-slate-500">Communications client ↔ prestataire (PII auto-masquée, messages toxiques bloqués).</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 h-[600px]">
            {{-- Threads list --}}
            <div class="md:col-span-1 rounded-2xl border bg-white shadow-sm overflow-y-auto">
                <ul class="divide-y">
                    @forelse($this->threads as $t)
                        <li>
                            <button wire:click="selectThread({{ $t->id }})"
                                    @class([
                                        'w-full text-left p-4 hover:bg-slate-50',
                                        'bg-indigo-50' => $activeThreadId === $t->id,
                                    ])>
                                <div class="flex items-center justify-between">
                                    <p class="font-bold text-sm text-slate-900">
                                        {{ $t->title ?? ucfirst($t->context_type ?? 'Conversation') . ' #' . $t->context_id }}
                                    </p>
                                    @if($t->flagged_count > 0)
                                        <span class="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-xs font-bold">{{ $t->flagged_count }}</span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-500 mt-1 truncate">{{ $t->last_message_preview ?? '—' }}</p>
                                <p class="text-xs text-slate-400 mt-1">{{ optional($t->last_message_at)->diffForHumans() }}</p>
                            </button>
                        </li>
                    @empty
                        <li class="p-8 text-center text-slate-400">Aucune conversation.</li>
                    @endforelse
                </ul>
            </div>

            {{-- Messages panel --}}
            <div class="md:col-span-2 rounded-2xl border bg-white shadow-sm flex flex-col">
                @if($this->activeThread)
                    <div class="p-4 border-b">
                        <p class="font-bold text-slate-900">{{ $this->activeThread->title ?? 'Conversation' }}</p>
                        <p class="text-xs text-slate-500 font-mono">{{ $this->activeThread->code }}</p>
                    </div>

                    <div class="flex-1 overflow-y-auto p-4 space-y-3">
                        @forelse($this->activeMessages as $msg)
                            <div @class([
                                'rounded-2xl p-3 max-w-md',
                                'ml-auto bg-indigo-600 text-white' => $msg->sender_user_id === auth()->id(),
                                'mr-auto bg-slate-100 text-slate-900' => $msg->sender_user_id !== auth()->id(),
                            ])>
                                <p class="text-xs uppercase font-bold opacity-70 mb-1">{{ $msg->sender_role }}</p>
                                <p class="text-sm whitespace-pre-wrap">{{ $msg->displayBody() }}</p>
                                @if($msg->moderation_status === 'flagged')
                                    <p class="text-xs italic mt-1 opacity-70">⚠️ Données sensibles masquées</p>
                                @endif
                                <p class="text-xs opacity-60 mt-1">{{ optional($msg->created_at)->format('d/m H:i') }}</p>
                            </div>
                        @empty
                            <p class="text-center text-slate-400 py-12">Aucun message dans cette conversation.</p>
                        @endforelse
                    </div>

                    <div class="p-3 border-t">
                        <form wire:submit.prevent="send" class="flex gap-2">
                            <input type="text" wire:model="body" maxlength="4096"
                                   placeholder="Écrire un message…"
                                   class="flex-1 rounded-xl border-gray-300 text-sm" />
                            <button type="submit" class="rounded-xl bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-700">
                                Envoyer
                            </button>
                        </form>
                        <p class="text-xs text-slate-400 mt-1">Note : les emails, téléphones, IBAN sont automatiquement masqués (politique RGPD).</p>
                    </div>
                @else
                    <div class="flex-1 flex items-center justify-center">
                        <p class="text-slate-400">Sélectionnez une conversation pour commencer.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
