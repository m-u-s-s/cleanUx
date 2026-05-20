<div class="py-6">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Onboarding entreprise</p>
            <h1 class="text-2xl font-black text-slate-900">Vérification KYB</h1>
            <p class="text-sm text-slate-500">Identification légale, documents officiels, sanctions screening (3 étapes).</p>
        </div>

        {{-- Stepper --}}
        <div class="flex items-center gap-2">
            @foreach([1 => 'Identité légale', 2 => 'Documents', 3 => 'Vérifications'] as $idx => $label)
                <button type="button" wire:click="goToStep({{ $idx }})" @class([
                    'flex-1 rounded-xl border p-3 text-left',
                    'bg-indigo-50 border-indigo-300' => $step === $idx,
                    'bg-white border-slate-200 opacity-60' => $step !== $idx,
                ])>
                    <p class="text-xs font-bold uppercase text-slate-500">Étape {{ $idx }}</p>
                    <p class="text-sm font-bold text-slate-900">{{ $label }}</p>
                </button>
            @endforeach
        </div>

        @if($step === 1)
            <div class="rounded-2xl border bg-white shadow-sm p-5 space-y-3">
                <h2 class="text-lg font-bold text-slate-900">Identité légale de votre entreprise</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Raison sociale *</label>
                        <input type="text" wire:model="legalName" maxlength="255" class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                        @error('legalName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Nom commercial</label>
                        <input type="text" wire:model="tradeName" maxlength="255" class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Pays *</label>
                        <select wire:model="countryCode" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                            <option value="BE">Belgique</option>
                            <option value="FR">France</option>
                            <option value="NL">Pays-Bas</option>
                            <option value="LU">Luxembourg</option>
                            <option value="DE">Allemagne</option>
                            <option value="IT">Italie</option>
                            <option value="ES">Espagne</option>
                            <option value="GB">Royaume-Uni</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Type d'identifiant *</label>
                        <select wire:model="identifierType" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                            <option value="kbo">KBO/BCE (BE)</option>
                            <option value="siret">SIRET (FR)</option>
                            <option value="siren">SIREN (FR)</option>
                            <option value="kvk">KvK (NL)</option>
                            <option value="companies_house">Companies House (UK)</option>
                            <option value="handelsregister">Handelsregister (DE)</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Numéro d'identifiant *</label>
                        <input type="text" wire:model="identifierValue" maxlength="64" class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                        @error('identifierValue') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">N° TVA intracom</label>
                        <input type="text" wire:model="vatId" maxlength="32" placeholder="ex: BE0123456789" class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Forme juridique</label>
                        <input type="text" wire:model="legalForm" maxlength="64" placeholder="ex: SARL, BVBA, SA" class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                    </div>
                </div>

                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-semibold text-indigo-600">Adresse du siège social (optionnel)</summary>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                        <input type="text" wire:model="addressStreet" placeholder="Rue + n°" class="rounded-xl border-gray-300 text-sm md:col-span-3" />
                        <input type="text" wire:model="addressPostal" placeholder="Code postal" class="rounded-xl border-gray-300 text-sm" />
                        <input type="text" wire:model="addressCity" placeholder="Ville" class="rounded-xl border-gray-300 text-sm md:col-span-2" />
                    </div>
                </details>

                <button wire:click="nextFromStep1" class="rounded-xl bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-700">
                    Continuer →
                </button>
            </div>
        @elseif($step === 2)
            <div class="rounded-2xl border bg-white shadow-sm p-5 space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Documents officiels</h2>
                <p class="text-sm text-slate-600">Téléchargez vos documents légaux (Kbis, statuts, RIB, etc.).</p>

                <form wire:submit.prevent="uploadDocument" class="space-y-3 rounded-xl border bg-slate-50 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold uppercase text-slate-500">Type de document</label>
                            <select wire:model="documentType" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                                <option value="kbis">Kbis / Extrait registre</option>
                                <option value="certificate_incorp">Certificat d'incorporation</option>
                                <option value="articles">Statuts</option>
                                <option value="bank_statement">Relevé bancaire</option>
                                <option value="id_card_director">Pièce d'identité dirigeant</option>
                                <option value="tax_certificate">Attestation fiscale</option>
                                <option value="proof_address">Justificatif d'adresse</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase text-slate-500">Fichier (PDF/JPG/PNG, max 10MB)</label>
                            <input type="file" wire:model="documentFile" accept="application/pdf,image/jpeg,image/png" class="mt-1 w-full text-sm" />
                            @error('documentFile') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <button type="submit" class="rounded-xl bg-indigo-600 text-white px-3 py-1.5 text-xs font-semibold hover:bg-indigo-700">
                        Uploader
                    </button>
                </form>

                <div>
                    <p class="text-xs font-bold uppercase text-slate-500 mb-2">Documents fournis</p>
                    <ul class="space-y-1">
                        @forelse($this->documents as $doc)
                            <li class="rounded-lg bg-white border p-2 flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-mono">{{ $doc->document_type }}</span>
                                    <span class="text-xs text-slate-500 ml-2">{{ number_format($doc->size_bytes / 1024, 1) }} KB</span>
                                </div>
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-amber-100 text-amber-800' => $doc->status === 'pending',
                                    'bg-emerald-100 text-emerald-800' => $doc->status === 'approved',
                                    'bg-red-100 text-red-800' => $doc->status === 'rejected',
                                ])>{{ $doc->status }}</span>
                            </li>
                        @empty
                            <li class="text-sm text-slate-400">Aucun document uploadé.</li>
                        @endforelse
                    </ul>
                </div>

                <button wire:click="triggerVerifications" class="rounded-xl bg-emerald-600 text-white px-4 py-2 text-sm font-semibold hover:bg-emerald-700">
                    Lancer les vérifications →
                </button>
            </div>
        @else
            <div class="rounded-2xl border bg-white shadow-sm p-5 space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Statut de vérification</h2>
                @if($this->entity)
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs uppercase font-bold text-slate-500">Statut global</dt>
                            <dd>
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-sm font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $this->entity->status === 'verified',
                                    'bg-amber-100 text-amber-800' => in_array($this->entity->status, ['pending', 'needs_review']),
                                    'bg-red-100 text-red-800' => $this->entity->status === 'rejected',
                                ])>{{ $this->entity->status }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase font-bold text-slate-500">Score de risque</dt>
                            <dd>
                                @if($this->entity->risk_level)
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-sm font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $this->entity->risk_level === 'low',
                                        'bg-amber-100 text-amber-800' => $this->entity->risk_level === 'medium',
                                        'bg-orange-100 text-orange-800' => $this->entity->risk_level === 'high',
                                        'bg-red-100 text-red-800' => $this->entity->risk_level === 'critical',
                                    ])>{{ $this->entity->risk_level }} ({{ number_format($this->entity->risk_score, 1) }})</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if($this->entity->status === 'rejected' && $this->entity->rejection_reason)
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3">
                            <p class="text-xs font-bold uppercase text-red-700">Motif de rejet</p>
                            <p class="text-sm text-red-900 mt-1">{{ $this->entity->rejection_reason }}</p>
                        </div>
                    @endif

                    @if($this->entity->status === 'verified')
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                            <p class="text-sm font-semibold text-emerald-900">✓ Votre entreprise est vérifiée</p>
                            <p class="text-xs text-emerald-700 mt-1">Vérifiée le {{ optional($this->entity->verified_at)->format('d/m/Y') }}.</p>
                        </div>
                    @endif

                    <div class="text-xs text-slate-500">
                        Vous pouvez retourner aux étapes précédentes pour uploader d'autres documents si nécessaire.
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
