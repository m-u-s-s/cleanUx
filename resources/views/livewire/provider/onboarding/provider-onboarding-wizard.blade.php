<div class="p-4 md:p-6 max-w-3xl mx-auto space-y-6">

    {{-- Header --}}
    <div>
        <p class="text-sm font-medium text-slate-500">Inscription prestataire</p>
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900">Bienvenue sur CleanUx</h1>
        <p class="text-sm text-slate-500 mt-1">
            Complète les étapes ci-dessous pour pouvoir recevoir des missions.
        </p>
    </div>

    {{-- Progress bar --}}
    @php
        $stepLabels = [
            0 => 'Profil',
            1 => 'Identité',
            2 => 'Fiscal',
            3 => 'Assurance',
            4 => 'Compétences',
            5 => 'Stripe',
            6 => 'Validation',
        ];
        $maxReached = max($currentStep, $progress['current_step']);
    @endphp
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5">
        <div class="flex items-center gap-2 overflow-x-auto pb-2">
            @foreach ($stepLabels as $idx => $label)
                @php
                    $isCurrent = $currentStep === $idx;
                    $isDone = $idx < $progress['current_step'];
                    $isClickable = $idx <= $maxReached;
                @endphp
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @if ($isClickable) wire:click="goToStep({{ $idx }})" @endif
                            @disabled(! $isClickable)
                            class="flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition
                                   {{ $isCurrent ? 'bg-sky-600 text-white' : ($isDone ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500') }}
                                   {{ $isClickable && ! $isCurrent ? 'hover:bg-slate-200' : '' }}">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]
                              {{ $isCurrent ? 'bg-white/20' : ($isDone ? 'bg-emerald-200' : 'bg-slate-200') }}">
                            {{ $isDone ? '✓' : $idx + 1 }}
                        </span>
                        <span>{{ $label }}</span>
                    </button>
                    @if (! $loop->last)
                        <span class="text-slate-300">→</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Flash message --}}
    @if ($message)
        <div class="flex items-start justify-between rounded-2xl border px-5 py-4 text-sm
                {{ $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                : ($messageType === 'error' ? 'border-red-200 bg-red-50 text-red-700'
                : 'border-sky-200 bg-sky-50 text-sky-700') }}">
            <span>{{ $message }}</span>
            <button wire:click="clearMessage" class="ml-3 text-slate-500 hover:text-slate-700">✕</button>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────── --}}
    {{-- ÉTAPE 0 — Profil --}}
    {{-- ─────────────────────────────────────────────── --}}
    @if ($currentStep === 0)
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Ton profil</h2>
                <p class="text-sm text-slate-500 mt-1">Infos visibles par les clients lors d'une mission.</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Nom complet *</label>
                <input type="text"
                       wire:model="name"
                       class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Téléphone</label>
                <input type="text"
                       wire:model="phone"
                       placeholder="+32 4XX XX XX XX"
                       class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Bio courte</label>
                <textarea wire:model="bio"
                          rows="3"
                          placeholder="Ex : Plombier indépendant depuis 10 ans, spécialisé en réparations urgentes."
                          class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500"></textarea>
                @error('bio') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Photo de profil</label>
                <input type="file"
                       wire:model="photo"
                       accept="image/*"
                       class="block w-full text-sm text-slate-500
                              file:mr-3 file:py-2 file:px-4 file:rounded-full
                              file:border-0 file:text-sm file:font-semibold
                              file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
                @error('photo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <div wire:loading wire:target="photo" class="mt-1 text-xs text-slate-500">Upload en cours...</div>
            </div>

            <div class="pt-3 flex justify-end">
                <button wire:click="saveStep0"
                        wire:loading.attr="disabled"
                        class="rounded-2xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">
                    Continuer →
                </button>
            </div>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────── --}}
    {{-- ÉTAPE 1 — Identité --}}
    {{-- ─────────────────────────────────────────────── --}}
    @if ($currentStep === 1)
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Pièce d'identité</h2>
                <p class="text-sm text-slate-500 mt-1">
                    Document obligatoire pour vérification. Visible uniquement par les admins CleanUx.
                </p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Type de document *</label>
                <select wire:model="identityType"
                        class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                    <option value="identity_card">Carte d'identité</option>
                    <option value="passport">Passeport</option>
                    <option value="residence_permit">Titre de séjour</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Fichier (PDF, JPG, PNG, max 10 Mo) *</label>
                <input type="file"
                       wire:model="identityFile"
                       accept="application/pdf,image/*"
                       class="block w-full text-sm text-slate-500
                              file:mr-3 file:py-2 file:px-4 file:rounded-full
                              file:border-0 file:text-sm file:font-semibold
                              file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
                @error('identityFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <div wire:loading wire:target="identityFile" class="mt-1 text-xs text-slate-500">Upload en cours...</div>
            </div>

            {{-- Affiche le statut du dernier doc identité s'il existe --}}
            @php
                $latestIdDoc = collect(['identity_card','passport','residence_permit'])
                    ->map(fn ($t) => ($documents[$t] ?? collect())->first())
                    ->filter()
                    ->sortByDesc('created_at')
                    ->first();
            @endphp
            @if ($latestIdDoc)
                <div class="rounded-2xl border p-4 text-sm
                            {{ $latestIdDoc->status === 'approved' ? 'border-emerald-200 bg-emerald-50'
                            : ($latestIdDoc->status === 'rejected' ? 'border-red-200 bg-red-50'
                            : 'border-amber-200 bg-amber-50') }}">
                    <div class="font-semibold">
                        Dernier document envoyé : {{ $latestIdDoc->file_name }}
                    </div>
                    <div class="text-xs mt-1">
                        Statut :
                        @if ($latestIdDoc->status === 'approved') ✓ Approuvé
                        @elseif ($latestIdDoc->status === 'rejected') ✕ Rejeté
                        @else ⏳ En attente de validation
                        @endif
                    </div>
                    @if ($latestIdDoc->status === 'rejected' && $latestIdDoc->rejection_reason)
                        <div class="mt-2 text-xs italic text-red-700">
                            Motif : {{ $latestIdDoc->rejection_reason }}
                        </div>
                    @endif
                </div>
            @endif

            <div class="pt-3 flex justify-between">
                <button wire:click="goToStep(0)"
                        class="rounded-2xl bg-slate-100 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200">
                    ← Retour
                </button>
                <button wire:click="saveStep1"
                        wire:loading.attr="disabled"
                        class="rounded-2xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">
                    Continuer →
                </button>
            </div>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────── --}}
    {{-- ÉTAPE 2 — Fiscal --}}
    {{-- ─────────────────────────────────────────────── --}}
    @if ($currentStep === 2)
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Numéro fiscal</h2>
                <p class="text-sm text-slate-500 mt-1">
                    Pour la facturation. TVA (BE), SIREN (FR), KBO (BE), ou équivalent.
                </p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Numéro *</label>
                <input type="text"
                       wire:model="taxId"
                       placeholder="BE0123456789"
                       class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                @error('taxId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="pt-3 flex justify-between">
                <button wire:click="goToStep(1)"
                        class="rounded-2xl bg-slate-100 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200">
                    ← Retour
                </button>
                <button wire:click="saveStep2"
                        class="rounded-2xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
                    Continuer →
                </button>
            </div>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────── --}}
    {{-- ÉTAPE 3 — Assurance --}}
    {{-- ─────────────────────────────────────────────── --}}
    @if ($currentStep === 3)
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Attestation d'assurance</h2>
                <p class="text-sm text-slate-500 mt-1">
                    Responsabilité civile professionnelle. Document obligatoire.
                </p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Fichier (PDF, JPG, PNG, max 10 Mo) *</label>
                <input type="file"
                       wire:model="insuranceFile"
                       accept="application/pdf,image/*"
                       class="block w-full text-sm text-slate-500
                              file:mr-3 file:py-2 file:px-4 file:rounded-full
                              file:border-0 file:text-sm file:font-semibold
                              file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
                @error('insuranceFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <div wire:loading wire:target="insuranceFile" class="mt-1 text-xs text-slate-500">Upload en cours...</div>
            </div>

            @php $insuranceDoc = ($documents['insurance'] ?? collect())->first(); @endphp
            @if ($insuranceDoc)
                <div class="rounded-2xl border p-4 text-sm
                            {{ $insuranceDoc->status === 'approved' ? 'border-emerald-200 bg-emerald-50'
                            : ($insuranceDoc->status === 'rejected' ? 'border-red-200 bg-red-50'
                            : 'border-amber-200 bg-amber-50') }}">
                    <div class="font-semibold">{{ $insuranceDoc->file_name }}</div>
                    <div class="text-xs mt-1">
                        @if ($insuranceDoc->status === 'approved') ✓ Approuvé
                        @elseif ($insuranceDoc->status === 'rejected') ✕ Rejeté — {{ $insuranceDoc->rejection_reason }}
                        @else ⏳ En attente de validation
                        @endif
                    </div>
                </div>
            @endif

            <div class="pt-3 flex justify-between">
                <button wire:click="goToStep(2)"
                        class="rounded-2xl bg-slate-100 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200">
                    ← Retour
                </button>
                <button wire:click="saveStep3"
                        wire:loading.attr="disabled"
                        class="rounded-2xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">
                    Continuer →
                </button>
            </div>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────── --}}
    {{-- ÉTAPE 4 — Compétences + zones --}}
    {{-- ─────────────────────────────────────────────── --}}
    @if ($currentStep === 4)
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Compétences et zones</h2>
                <p class="text-sm text-slate-500 mt-1">Tu recevras des missions correspondant à tes choix.</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-3">Tes compétences *</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach ($availableSkills as $key => $label)
                        <label class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox"
                                   wire:model="selectedSkills"
                                   value="{{ $key }}"
                                   class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            <span class="text-sm text-slate-700">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('selectedSkills') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            @if ($zones->count() > 0)
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-3">Zones d'intervention</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto p-1">
                        @foreach ($zones as $zone)
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox"
                                       wire:model="selectedZones"
                                       value="{{ $zone->id }}"
                                       class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                                <span class="text-sm text-slate-700">{{ $zone->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="pt-3 flex justify-between">
                <button wire:click="goToStep(3)"
                        class="rounded-2xl bg-slate-100 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200">
                    ← Retour
                </button>
                <button wire:click="saveStep4"
                        class="rounded-2xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
                    Continuer →
                </button>
            </div>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────── --}}
    {{-- ÉTAPE 5 — Stripe Connect --}}
    {{-- ─────────────────────────────────────────────── --}}
    @if ($currentStep === 5)
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Compte Stripe</h2>
                <p class="text-sm text-slate-500 mt-1">
                    Pour recevoir tes paiements après chaque mission. Stripe gère tout.
                </p>
            </div>

            @if ($progress['stripe_active'])
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
                    <div class="font-semibold text-emerald-700">✓ Compte Stripe actif</div>
                    <div class="text-xs text-emerald-600 mt-1">Tu peux recevoir des paiements.</div>
                </div>
            @else
                <div class="space-y-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        <ol class="list-decimal list-inside space-y-1">
                            <li>Clique "Configurer mon compte Stripe"</li>
                            <li>Stripe ouvre dans un nouvel onglet pour valider ton identité bancaire</li>
                            <li>Reviens ici et clique "J'ai terminé sur Stripe"</li>
                        </ol>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2">
                        <button wire:click="startStripeOnboarding"
                                class="rounded-2xl bg-purple-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-purple-700">
                            Configurer mon compte Stripe
                        </button>
                        <button wire:click="refreshStripeStatus"
                                class="rounded-2xl bg-slate-100 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200">
                            J'ai terminé sur Stripe
                        </button>
                    </div>
                </div>
            @endif

            <div class="pt-3 flex justify-between">
                <button wire:click="goToStep(4)"
                        class="rounded-2xl bg-slate-100 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200">
                    ← Retour
                </button>
                @if ($progress['stripe_active'])
                    <button wire:click="goToStep(6)"
                            class="rounded-2xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
                        Continuer →
                    </button>
                @endif
            </div>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────── --}}
    {{-- ÉTAPE 6 — Validation finale --}}
    {{-- ─────────────────────────────────────────────── --}}
    @if ($currentStep === 6)
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-5">
            @if ($progress['completed'])
                <div class="text-center py-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 mb-4">
                        <span class="text-3xl">🎉</span>
                    </div>
                    <h2 class="text-xl font-bold text-slate-900">Bienvenue dans CleanUx !</h2>
                    <p class="text-sm text-slate-600 mt-2">
                        Ton inscription est validée. Tu peux maintenant passer en ligne et recevoir des missions.
                    </p>
                    <a href="/dashboard"
                       class="inline-block mt-5 rounded-2xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
                        Accéder au dashboard
                    </a>
                </div>
            @else
                <div class="text-center py-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-amber-100 mb-4">
                        <span class="text-3xl">⏳</span>
                    </div>
                    <h2 class="text-xl font-bold text-slate-900">En attente de validation admin</h2>
                    <p class="text-sm text-slate-600 mt-2 max-w-md mx-auto">
                        Tu as terminé toutes les étapes. Notre équipe va vérifier tes documents
                        et te confirmer par email sous 24-48h.
                    </p>
                </div>

                {{-- Récap statuts documents --}}
                <div class="border-t border-slate-200 pt-5">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">Statut de tes documents</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach ($documents as $type => $docs)
                            @php
                                $latest = $docs->first();
                                $typeLabel = match ($type) {
                                    'identity_card' => 'Carte d\'identité',
                                    'passport' => 'Passeport',
                                    'residence_permit' => 'Titre de séjour',
                                    'insurance' => 'Assurance',
                                    default => $type,
                                };
                            @endphp
                            <li class="flex items-center justify-between p-2 rounded-lg bg-slate-50">
                                <span class="text-slate-700">{{ $typeLabel }}</span>
                                @if ($latest->status === 'approved')
                                    <span class="text-xs font-semibold text-emerald-700">✓ Approuvé</span>
                                @elseif ($latest->status === 'rejected')
                                    <span class="text-xs font-semibold text-red-700">✕ Rejeté</span>
                                @else
                                    <span class="text-xs font-semibold text-amber-700">⏳ En attente</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('open-stripe-link', (e) => {
            const url = Array.isArray(e) ? e[0]?.url : e?.url;
            if (url) {
                window.open(url, '_blank', 'noopener');
            }
        });
    });
</script>
@endpush
