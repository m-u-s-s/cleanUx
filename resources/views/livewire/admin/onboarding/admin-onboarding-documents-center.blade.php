<div class="p-4 md:p-6 space-y-6">

    {{-- Header --}}
    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">Administration</p>
            <h1 class="text-2xl md:text-3xl font-bold text-slate-900">Validation des documents prestataires</h1>
            <p class="text-sm text-slate-500 mt-1">
                Approuve ou rejette les documents KYC uploadés par les prestataires en cours d'onboarding.
            </p>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session()->has('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-5 py-4">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 px-5 py-4">
            {{ session('error') }}
        </div>
    @endif

    {{-- Counts cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <button wire:click="$set('filterStatus', 'pending_review')"
                class="text-left bg-white rounded-2xl border-2 transition
                       {{ $filterStatus === 'pending_review' ? 'border-amber-400' : 'border-slate-200 hover:border-amber-200' }} p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">À valider</div>
            <div class="mt-1 text-3xl font-bold text-amber-700">{{ $counts['pending'] }}</div>
        </button>
        <button wire:click="$set('filterStatus', 'approved')"
                class="text-left bg-white rounded-2xl border-2 transition
                       {{ $filterStatus === 'approved' ? 'border-emerald-400' : 'border-slate-200 hover:border-emerald-200' }} p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Approuvés</div>
            <div class="mt-1 text-3xl font-bold text-emerald-700">{{ $counts['approved'] }}</div>
        </button>
        <button wire:click="$set('filterStatus', 'rejected')"
                class="text-left bg-white rounded-2xl border-2 transition
                       {{ $filterStatus === 'rejected' ? 'border-red-400' : 'border-slate-200 hover:border-red-200' }} p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Rejetés</div>
            <div class="mt-1 text-3xl font-bold text-red-700">{{ $counts['rejected'] }}</div>
        </button>
    </div>

    {{-- Filtres --}}
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Recherche prestataire</label>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Nom ou email..."
                       class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Type de document</label>
                <select wire:model.live="filterType" class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                    <option value="all">— Tous —</option>
                    <option value="identity_card">Carte d'identité</option>
                    <option value="passport">Passeport</option>
                    <option value="residence_permit">Titre de séjour</option>
                    <option value="tax_id">Numéro fiscal</option>
                    <option value="insurance">Assurance pro</option>
                    <option value="diploma">Diplôme</option>
                    <option value="criminal_record">Casier judiciaire</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            <div class="flex items-end">
                <button wire:click="clearFilters"
                        class="rounded-2xl bg-slate-100 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200 w-full">
                    Réinitialiser
                </button>
            </div>
        </div>
    </div>

    {{-- Tableau documents --}}
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Prestataire</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Fichier</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Uploadé le</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Statut</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($documents as $doc)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $doc->user?->name ?? '—' }}</div>
                            <div class="text-xs text-slate-500">{{ $doc->user?->email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $typeLabels = [
                                    'identity_card' => 'Carte d\'identité',
                                    'passport' => 'Passeport',
                                    'residence_permit' => 'Titre de séjour',
                                    'tax_id' => 'Numéro fiscal',
                                    'insurance' => 'Assurance',
                                    'diploma' => 'Diplôme',
                                    'criminal_record' => 'Casier judiciaire',
                                    'other' => 'Autre',
                                ];
                            @endphp
                            <span class="text-slate-700">{{ $typeLabels[$doc->document_type] ?? $doc->document_type }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs text-slate-600 font-mono truncate max-w-[180px] inline-block">
                                {{ $doc->file_name ?: basename($doc->file_path) }}
                            </span>
                            @if ($doc->file_size)
                                <div class="text-xs text-slate-400">
                                    {{ number_format($doc->file_size / 1024, 0) }} Ko
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $doc->created_at?->locale('fr')->isoFormat('D MMM YYYY HH:mm') }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $badge = match($doc->status) {
                                    'approved'        => 'bg-emerald-100 text-emerald-700',
                                    'pending_review'  => 'bg-amber-100 text-amber-700',
                                    'rejected'        => 'bg-red-100 text-red-700',
                                    default           => 'bg-slate-100 text-slate-700',
                                };
                                $statusLabels = [
                                    'approved' => '✓ Approuvé',
                                    'pending_review' => 'À valider',
                                    'rejected' => '✕ Rejeté',
                                ];
                            @endphp
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $badge }}">
                                {{ $statusLabels[$doc->status] ?? $doc->status }}
                            </span>
                            @if ($doc->status === 'rejected' && $doc->rejection_reason)
                                <div class="mt-1 text-xs text-red-600 italic max-w-[200px]">
                                    {{ Str::limit($doc->rejection_reason, 60) }}
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button wire:click="preview({{ $doc->id }})"
                                        class="rounded-lg bg-sky-50 px-2.5 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-100"
                                        title="Voir le fichier">
                                    👁
                                </button>
                                @if ($doc->status === 'pending_review')
                                    <button wire:click="approve({{ $doc->id }})"
                                            wire:confirm="Approuver ce document ?"
                                            class="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100">
                                        ✓ Approuver
                                    </button>
                                    <button wire:click="openRejectModal({{ $doc->id }})"
                                            class="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">
                                        ✕ Rejeter
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-slate-500">
                            Aucun document à afficher avec ces filtres.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($documents->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $documents->links() }}
            </div>
        @endif
    </div>

    {{-- Modal de rejet --}}
    @if ($reviewingDocumentId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
             wire:click.self="closeRejectModal">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-bold text-slate-900">Rejeter le document</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Le prestataire verra ce motif et pourra ré-uploader.
                </p>

                <div class="mt-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Motif du rejet</label>
                    <textarea wire:model="rejectionReason"
                              rows="4"
                              class="w-full rounded-xl border-slate-300 focus:border-red-500 focus:ring-red-500"
                              placeholder="Ex : Le document est flou, illisible. Re-scanne avec une meilleure résolution.">{{-- --}}</textarea>
                    @error('rejectionReason')
                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button wire:click="closeRejectModal"
                            class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                        Annuler
                    </button>
                    <button wire:click="reject"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        Rejeter
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de preview --}}
    @if ($previewingDocumentId && $this->previewDocument)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
             wire:click.self="closePreview">
            <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">{{ $this->previewDocument->file_name }}</h3>
                        <p class="text-xs text-slate-500">
                            {{ $this->previewDocument->user?->name }} — {{ $this->previewDocument->user?->email }}
                        </p>
                    </div>
                    <button wire:click="closePreview" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-auto p-4 bg-slate-100 min-h-[400px]">
                    @if (Str::startsWith($this->previewDocument->mime_type ?? '', 'image/'))
                        <img src="{{ $this->previewUrl }}" class="max-w-full mx-auto rounded shadow" alt="Document">
                    @elseif (($this->previewDocument->mime_type ?? '') === 'application/pdf')
                        <iframe src="{{ $this->previewUrl }}" class="w-full h-[600px] rounded"></iframe>
                    @else
                        <div class="text-center py-8">
                            <p class="text-slate-600">Aperçu non disponible pour ce type de fichier.</p>
                            <a href="{{ $this->previewUrl }}" target="_blank"
                               class="inline-block mt-3 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                                Télécharger
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
