<div
    x-data="{ open: @entangle('isOpen') }"
    class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3"
>
    {{-- ── Fenêtre de chat ── --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 translate-y-4"
        x-cloak
        class="flex w-[360px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
        style="height: 520px"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-3">
            <div class="flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-lg">🤖</div>
                <div>
                    <p class="text-sm font-bold text-white">Assistant CleanUx</p>
                    <p class="text-xs text-blue-100">Toujours disponible</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button
                    wire:click="clearConversation"
                    title="Nouvelle conversation"
                    class="rounded-lg p-1 text-blue-200 transition hover:bg-white/20 hover:text-white"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </button>
                <button
                    wire:click="toggle"
                    class="rounded-lg p-1 text-blue-200 transition hover:bg-white/20 hover:text-white"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Messages --}}
        <div
            id="chat-messages"
            class="flex flex-1 flex-col gap-3 overflow-y-auto p-4"
            x-ref="messages"
            wire:updated="$nextTick(() => { $refs.messages.scrollTop = $refs.messages.scrollHeight })"
        >
            @foreach ($messages as $message)
                @if ($message['sender'] === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-[80%]">
                            <div class="rounded-2xl rounded-br-sm bg-blue-600 px-3 py-2 text-sm text-white">
                                {{ $message['content'] }}
                            </div>
                            <p class="mt-1 text-right text-[10px] text-slate-400">{{ $message['time'] }}</p>
                        </div>
                    </div>
                @else
                    <div class="flex justify-start gap-2">
                        <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm">🤖</div>
                        <div class="max-w-[80%]">
                            <div class="rounded-2xl rounded-bl-sm bg-slate-100 px-3 py-2 text-sm text-slate-800">
                                {!! nl2br(e($message['content'])) !!}
                            </div>
                            <p class="mt-1 text-[10px] text-slate-400">{{ $message['time'] }}</p>
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- Indicateur de chargement --}}
            @if ($isLoading)
                <div class="flex justify-start gap-2">
                    <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm">🤖</div>
                    <div class="flex items-center gap-1 rounded-2xl rounded-bl-sm bg-slate-100 px-4 py-3">
                        <div class="h-2 w-2 animate-bounce rounded-full bg-slate-400" style="animation-delay: 0ms"></div>
                        <div class="h-2 w-2 animate-bounce rounded-full bg-slate-400" style="animation-delay: 150ms"></div>
                        <div class="h-2 w-2 animate-bounce rounded-full bg-slate-400" style="animation-delay: 300ms"></div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ──────────────────────────────────────────────── --}}
        {{-- Phase 5 — Bandeau de confirmation d'action LLM    --}}
        {{-- ──────────────────────────────────────────────── --}}
        @if ($pendingActionId ?? null)
            <div class="border-t border-amber-200 bg-amber-50 px-3 py-2">
                <div class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-amber-800">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>Confirmer l'action ?</span>
                </div>
                <div class="flex gap-2">
                    <button
                        wire:click="confirmAction({{ $pendingActionId }})"
                        wire:loading.attr="disabled"
                        wire:target="confirmAction,cancelAction"
                        class="flex-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50"
                    >
                        ✅ Confirmer
                    </button>
                    <button
                        wire:click="cancelAction({{ $pendingActionId }})"
                        wire:loading.attr="disabled"
                        wire:target="confirmAction,cancelAction"
                        class="flex-1 rounded-lg bg-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-300 disabled:opacity-50"
                    >
                        ❌ Annuler
                    </button>
                </div>
            </div>
        @endif

        {{-- Input --}}
        <div class="border-t border-slate-100 p-3">
            <form wire:submit.prevent="send" class="flex items-end gap-2">
                <textarea
                    wire:model="input"
                    wire:keydown.enter.prevent="send"
                    placeholder="Posez votre question..."
                    rows="1"
                    class="flex-1 resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:bg-white focus:ring-2 focus:ring-blue-100"
                    style="max-height: 96px"
                    x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 96) + 'px'"
                ></textarea>
                <button
                    type="submit"
                    :disabled="{{ $isLoading ? 'true' : 'false' }}"
                    class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white transition hover:bg-blue-700 disabled:opacity-50"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>

    {{-- ── Bouton flottant ── --}}
    <button
        wire:click="toggle"
        class="relative flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-2xl shadow-lg shadow-blue-200 transition hover:scale-105 hover:bg-blue-700 active:scale-95"
    >
        <span x-show="!open">🤖</span>
        <span x-show="open" x-cloak>✕</span>

        {{-- Badge notifications (à connecter avec unread count) --}}
        {{-- <span class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">3</span> --}}
    </button>
</div>
<script>
    function startStreaming(conversationId, userMessage) {
        const url = new URL('/assistant/stream', window.location.origin);
        url.searchParams.set('conversation_id', conversationId);
        url.searchParams.set('message', userMessage);
    
        const es = new EventSource(url);
        let currentText = '';
    
        es.addEventListener('text_delta', (e) => {
            const data = JSON.parse(e.data);
            currentText += data.text;
            // mettre à jour l'UI avec currentText
            Livewire.dispatch('streamUpdate', { text: currentText });
        });
    
        es.addEventListener('tool_use_start', (e) => {
            const data = JSON.parse(e.data);
            Livewire.dispatch('streamToolUse', { name: data.tool_name });
        });
    
        es.addEventListener('stop', () => {
            es.close();
            Livewire.dispatch('streamComplete');
        });
    
        es.addEventListener('error', (e) => {
            console.error('Stream error', e);
            es.close();
        });
    }

</script>
