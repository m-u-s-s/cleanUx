<div class="rounded-3xl border bg-white p-4 space-y-4">
    <h3 class="font-bold text-slate-900">Messagerie</h3>

    <div class="space-y-3 max-h-96 overflow-y-auto">
        @foreach($messages as $msg)
            <div class="{{ $msg->sender_id === auth()->id() ? 'text-right' : 'text-left' }}">
                <div class="inline-block rounded-2xl px-4 py-2 {{ $msg->sender_id === auth()->id() ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-800' }}">
                    <p class="text-sm">{{ $msg->message }}</p>
                    <p class="text-xs opacity-70 mt-1">{{ $msg->sender->name }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <form wire:submit.prevent="send" class="flex gap-2">
        <input
            type="text"
            wire:model.defer="message"
            class="flex-1 rounded-xl border-slate-300"
            placeholder="Écrire un message..."
        >

        <button class="rounded-xl bg-blue-600 px-4 py-2 text-white">
            Envoyer
        </button>
    </form>
</div>