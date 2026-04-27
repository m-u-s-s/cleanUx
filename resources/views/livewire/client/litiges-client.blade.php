<x-page-shell
    title="⚠️ Centre de litiges"
    subtitle="Signalez un problème, ajoutez des preuves et suivez le traitement de votre demande.">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 rounded-2xl border bg-white p-6 shadow-sm space-y-4">
            <div>
                <h3 class="font-semibold text-slate-900">Nouveau litige</h3>
                <p class="text-sm text-slate-500">
                    Décrivez clairement le problème rencontré.
                </p>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Rendez-vous concerné</label>
                <select wire:model="rendez_vous_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="">— Aucun / général —</option>
                    @foreach($rendezVous as $rdv)
                        <option value="{{ $rdv->id }}">
                            {{ $rdv->date?->format('d/m/Y') }} — {{ $rdv->service_display_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Catégorie</label>
                <select wire:model="category" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="quality">Qualité du nettoyage</option>
                    <option value="delay">Retard</option>
                    <option value="damage">Dégât / dommage</option>
                    <option value="billing">Facturation</option>
                    <option value="employee_behavior">Comportement employé</option>
                    <option value="missing_service">Service non réalisé</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Priorité</label>
                <select wire:model="priority" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="low">Basse</option>
                    <option value="normal">Normale</option>
                    <option value="high">Haute</option>
                    <option value="urgent">Urgente</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Titre</label>
                <input
                    type="text"
                    wire:model="title"
                    class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                    placeholder="Ex : Nettoyage incomplet">
                @error('title') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Description</label>
                <textarea
                    wire:model="description"
                    rows="5"
                    class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                    placeholder="Expliquez le problème avec le plus de détails possible..."></textarea>
                @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Preuves photo</label>
                <input
                    type="file"
                    wire:model="photos"
                    multiple
                    accept="image/*"
                    class="mt-1 w-full text-sm">
                @error('photos.*') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <button
                wire:click="createClaim"
                class="w-full rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                Envoyer le litige
            </button>
        </div>

        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-slate-900">Mes litiges</h3>
                    <p class="text-sm text-slate-500">Suivi du statut et du délai de réponse.</p>
                </div>

                <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous les statuts</option>
                    <option value="open">Ouvert</option>
                    <option value="in_review">En traitement</option>
                    <option value="waiting_client">En attente client</option>
                    <option value="resolved">Résolu</option>
                    <option value="closed">Clôturé</option>
                </select>
            </div>

            @forelse($claims as $claim)
                <div class="rounded-2xl border bg-white p-5 shadow-sm space-y-4">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                        <div>
                            <h4 class="font-semibold text-slate-900">
                                {{ $claim->title }}
                            </h4>

                            <p class="text-sm text-slate-500">
                                {{ $claim->category_label }}
                                @if($claim->rendezVous)
                                    — RDV du {{ $claim->rendezVous->date?->format('d/m/Y') }}
                                @endif
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full px-3 py-1 text-xs font-medium
                                {{ $claim->status === 'resolved'
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-amber-100 text-amber-700' }}">
                                {{ $claim->status_label }}
                            </span>

                            <span class="rounded-full px-3 py-1 text-xs font-medium
                                {{ in_array($claim->priority, ['high', 'urgent'])
                                    ? 'bg-red-100 text-red-700'
                                    : 'bg-slate-100 text-slate-700' }}">
                                {{ ucfirst($claim->priority) }}
                            </span>
                        </div>
                    </div>

                    <p class="text-sm text-slate-700">
                        {{ $claim->description }}
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                        <div class="rounded-xl bg-slate-50 border p-3">
                            <p class="text-slate-500">Créé le</p>
                            <p class="font-medium text-slate-900">
                                {{ $claim->created_at?->format('d/m/Y H:i') }}
                            </p>
                        </div>

                        <div class="rounded-xl bg-slate-50 border p-3">
                            <p class="text-slate-500">SLA réponse avant</p>
                            <p class="font-medium text-slate-900">
                                {{ $claim->sla_due_at?->format('d/m/Y H:i') ?? '—' }}
                            </p>
                        </div>

                        <div class="rounded-xl bg-slate-50 border p-3">
                            <p class="text-slate-500">Résolu le</p>
                            <p class="font-medium text-slate-900">
                                {{ $claim->resolved_at?->format('d/m/Y H:i') ?? '—' }}
                            </p>
                        </div>
                    </div>

                    @if(is_array($claim->attachments) && count($claim->attachments))
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-slate-800">Preuves ajoutées</p>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                @foreach($claim->attachments as $attachment)
                                    <a
                                        href="{{ asset('storage/'.$attachment['path']) }}"
                                        target="_blank"
                                        class="block rounded-xl overflow-hidden border bg-slate-50">
                                        <img
                                            src="{{ asset('storage/'.$attachment['path']) }}"
                                            class="h-24 w-full object-cover"
                                            alt="Preuve litige">
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <x-empty-state
                    title="Aucun litige"
                    message="Vous n’avez pas encore signalé de problème." />
            @endforelse

            <div>
                {{ $claims->links() }}
            </div>
        </div>
    </div>
</x-page-shell>