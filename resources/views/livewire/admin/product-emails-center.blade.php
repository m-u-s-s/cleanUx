{{-- L EMPILEMENT DE PILOTAGE NE S OUVRE PLUS ICI : bandeau chiffre en dur, checklist sans etat
     et raccourcis dont les quatre liens figurent tous au catalogue des modules. --}}
<div class="space-y-6" data-phase2t-root="true">
    @includeIf('livewire.shared.communication.layout-stack')

<div class="space-y-6">
    <div class="bg-white rounded-xl shadow border p-4 space-y-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Emails produit</h3>
            <p class="text-sm text-slate-500">Prévisualise les principaux emails transactionnels et consulte une journalisation minimale.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="text-sm text-slate-600" for="templateKey">Template</label>
                <select id="templateKey" wire:model.live="templateKey" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                    @foreach($templates as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-600" for="recipientName">Nom destinataire</label>
                <input id="recipientName" type="text" wire:model.live="recipientName" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm text-slate-600" for="recipientEmail">Email destinataire</label>
                <input id="recipientEmail" type="email" wire:model.live="recipientEmail" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
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
            {{--
                L'APERCU VIT DANS UN CADRE, PAS DANS LA PAGE.

                `{!! $previewHtml !!}` injectait un DOCUMENT COMPLET — `<html>`, `<head>`,
                `<body>` — dans le corps de la page d'administration. Le navigateur ne cree pas
                un second document : il fusionne les attributs du `<body>` de l'e-mail avec
                celui de la page. Le gabarit portant `style="background:#f8fafc"`, un style EN
                LIGNE se posait sur le `<body>` reel — et un style en ligne bat toutes les
                regles CSS.

                Consequence mesuree en mode sombre : `/admin/outils` rendait un fond
                `rgb(248,250,252)` la ou les autres pages d'administration rendent
                `rgb(10,14,26)`. Une page entiere en clair, avec du texte clair dessus.

                `srcdoc` donne a l'e-mail son propre document. `sandbox` sans `allow-scripts` :
                un gabarit ne doit rien pouvoir executer dans la console.
            --}}
            <div class="p-4 bg-slate-100">
                <iframe
                    title="{{ __('Aperçu de l’email') }}"
                    sandbox
                    srcdoc="{{ $previewHtml }}"
                    class="h-[32rem] w-full rounded-lg border-0 bg-white"
                    loading="lazy"></iframe>
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