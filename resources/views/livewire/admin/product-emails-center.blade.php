<div class="space-y-6" data-phase2t-root="true">
    @includeIf('livewire.shared.communication.layout-stack')

<div class="space-y-6" data-phase2s-root="true">
    @includeIf('livewire.admin.pilotage.layout-stack')

<div class="space-y-6">
    <div class="bg-white rounded-xl shadow border p-4 space-y-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Emails produit</h3>
            <p class="text-sm text-slate-500">Prévisualise les principaux emails transactionnels et consulte une journalisation minimale.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="text-sm text-slate-600">Template</label>
                <select wire:model.live="templateKey" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                    @foreach($templates as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-600">Nom destinataire</label>
                <input type="text" wire:model.live="recipientName" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm text-slate-600">Email destinataire</label>
                <input type="email" wire:model.live="recipientEmail" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button wire:click="generatePreview" class="bg-sky-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-sky-700">Générer un aperçu</button>
            <span class="text-sm text-slate-500">Sujet : <span class="font-semibold text-slate-800">{{ $subject }}</span></span>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white rounded-xl shadow border overflow-hidden">
            <div class="px-4 py-3 border-b bg-slate-50">
                <h4 class="font-semibold text-slate-900">Aperçu email</h4>
            </div>
            <div class="p-4 bg-slate-100 overflow-auto">
                {!! $previewHtml !!}
            </div>
        </div>

        <div class="bg-white rounded-xl shadow border p-4 space-y-3">
            <div>
                <h4 class="font-semibold text-slate-900">Journal récent</h4>
                <p class="text-sm text-slate-500">Aperçus et envois mail les plus récents.</p>
            </div>

            <div class="space-y-3">
                @forelse($recentLogs as $log)
                    <div class="border rounded-lg p-3 bg-slate-50">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-slate-800">{{ $log->subject ?: $log->template_key }}</span>
                            <span class="text-[11px] uppercase tracking-wide px-2 py-1 rounded-full border {{ $log->status === 'failed' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200' }}">{{ $log->status }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $log->recipient_email ?: '—' }} • {{ strtoupper($log->channel) }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ optional($log->created_at)->format('d/m/Y H:i') }}</p>
                    </div>
                @empty
                    <div class="text-sm text-slate-500 italic">Aucun log email disponible pour le moment.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

</div>
</div>