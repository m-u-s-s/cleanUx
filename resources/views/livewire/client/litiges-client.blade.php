<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Litiges & réclamations</h1>
        <p class="text-sm text-gray-500">Déclare un litige, joins des preuves et suis la réponse admin avec un SLA indicatif.</p>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-1 rounded-2xl border bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">Nouveau dossier</h2>
            <select wire:model="rendezVousId" class="w-full rounded-xl border-gray-300 text-sm">
                <option value="">Rendez-vous lié (optionnel)</option>
                @foreach($this->rendezVousOptions as $rdv)
                    <option value="{{ $rdv->id }}">{{ $rdv->booking_reference }} — {{ $rdv->date?->format('d/m/Y') }}</option>
                @endforeach
            </select>
            <select wire:model="category" class="w-full rounded-xl border-gray-300 text-sm">
                <option value="reclamation">Réclamation</option>
                <option value="qualite">Qualité</option>
                <option value="facturation">Facturation</option>
                <option value="retard">Retard</option>
            </select>
            <select wire:model="priority" class="w-full rounded-xl border-gray-300 text-sm">
                <option value="faible">Faible</option>
                <option value="normale">Normale</option>
                <option value="haute">Haute</option>
                <option value="critique">Critique</option>
            </select>
            <input wire:model="subject" type="text" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Sujet">
            <textarea wire:model="description" rows="5" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Décris ton dossier"></textarea>
            <textarea wire:model="attachmentInput" rows="3" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Pièces jointes (1 lien/chemin par ligne)"></textarea>
            <button wire:click="save" class="w-full rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Envoyer</button>
        </div>

        <div class="lg:col-span-2 rounded-2xl border bg-white p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-gray-900">Mes dossiers</h2>
                <select wire:model.live="status" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="ouvert">Ouvert</option>
                    <option value="en_cours">En cours</option>
                    <option value="en_attente_client">En attente client</option>
                    <option value="resolu">Résolu</option>
                    <option value="ferme">Fermé</option>
                </select>
            </div>

            <div class="space-y-3">
                @forelse($cases as $case)
                    <div class="rounded-2xl border p-4 {{ $case->is_overdue ? 'border-red-200 bg-red-50/40' : '' }}">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="font-semibold text-gray-900">{{ $case->subject }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $case->category }} • {{ $case->created_at?->format('d/m/Y H:i') }}
                                    @if($case->rendezVous)
                                        • {{ $case->rendezVous->booking_reference }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold">{{ $case->status }}</span>
                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">{{ $case->priority }}</span>
                            </div>
                        </div>
                        <div class="mt-3 text-sm text-gray-700">{{ $case->description }}</div>
                        <div class="mt-3 grid gap-3 md:grid-cols-2 text-xs text-gray-600">
                            <div>
                                <span class="font-semibold">SLA :</span> {{ $case->sla_policy ?: '—' }}
                            </div>
                            <div>
                                <span class="font-semibold">Échéance :</span> {{ $case->due_at?->format('d/m/Y H:i') ?: '—' }}
                            </div>
                        </div>
                        @if(!empty($case->attachments))
                            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <div class="text-xs font-semibold text-slate-700">Pièces jointes</div>
                                <ul class="mt-2 space-y-1 text-xs text-slate-600">
                                    @foreach($case->attachments as $attachment)
                                        <li>• {{ data_get($attachment, 'path') }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if($case->admin_response)
                            <div class="mt-3 rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm text-blue-900">
                                <div class="font-semibold">Réponse admin</div>
                                <div class="mt-1">{{ $case->admin_response }}</div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed p-8 text-center text-sm text-gray-500">Aucun dossier.</div>
                @endforelse
            </div>

            {{ $cases->links() }}
        </div>
    </div>
</div>
