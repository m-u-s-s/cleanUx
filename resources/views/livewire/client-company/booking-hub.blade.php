<div>

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Reservations</h1>
            <p class="text-sm text-slate-500">Gerez toutes vos demandes de service</p>
        </div>
        <button wire:click="$set('view', 'create')"
            class="flex items-center gap-2 rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
            + Nouvelle demande
        </button>
    </div>

    @if (session('success'))
        <div role="alert" class="brio-alerte brio-alerte-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- ══ VUE LIST ══ --}}
    @if ($view === 'list')

        {{--
            LES TROIS FAÇONS DE COMMANDER, pour une entreprise cliente aussi.

            Son formulaire maison ne sert que le RENDEZ-VOUS : ni intervention immédiate, ni
            chantier multi-services. Une société qui a une fuite dans ses bureaux n'avait donc aucun
            moyen d'appeler quelqu'un tout de suite, alors qu'un particulier l'avait — la même
            plateforme, deux promesses différentes selon le type de compte.

            Les trois cartes ouvrent LE parcours de commande, celui du particulier, en indiquant
            pour quel local : les questionnaires, la tarification et la confirmation sont les mêmes.
            Recopier trois modes dans le formulaire maison aurait produit une deuxième
            implémentation à maintenir, et c'est ainsi que deux parcours finissent par ne plus dire
            le même prix.
        --}}
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5" data-test="company-order-modes">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Commander un service</h2>
                    <p class="text-xs text-slate-500">Pour l’un de vos locaux, dans le mode qui convient.</p>
                </div>

                @if ($sites->isNotEmpty())
                    <label class="text-xs font-semibold text-slate-600">
                        Local
                        <select wire:model.live="orderSiteId" data-test="company-order-site"
                            class="ms-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-900">
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            </div>

            @if ($sites->isEmpty())
                <p class="mt-3 text-sm text-slate-500">
                    Déclarez d’abord un local dans
                    <a href="{{ route('client-company.sites') }}" class="font-semibold text-sky-600 hover:underline">Vos
                        locaux</a> : sans adresse, aucune commande ne peut être située.
                </p>
            @else
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    @foreach ([
                        ['mode' => 'asap', 'titre' => 'Intervention immédiate', 'detail' => 'Un pro prend la route maintenant', 'icone' => '⚡'],
                        ['mode' => 'scheduled', 'titre' => 'Prendre rendez-vous', 'detail' => 'Vous choisissez le créneau', 'icone' => '📅'],
                        ['mode' => 'bundle', 'titre' => 'Plusieurs services', 'detail' => 'Un chantier, un paiement', 'icone' => '🧩'],
                    ] as $carte)
                        <a href="{{ route('order.journey', ['mode' => $carte['mode'], 'site' => $orderSiteId]) }}"
                            data-test="company-mode-{{ $carte['mode'] }}"
                            class="rounded-xl border border-slate-200 p-4 transition hover:border-sky-300 hover:bg-sky-50/50">
                            <span class="text-xl" aria-hidden="true">{{ $carte['icone'] }}</span>
                            <span class="mt-2 block text-sm font-semibold text-slate-900">{{ $carte['titre'] }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ $carte['detail'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Filtres --}}
        <div class="mb-4 flex flex-wrap gap-3">
            <select wire:model.live="filterSiteId"
                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm">
                <option value="">Tous les locaux</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterStatus"
                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm">
                <option value="">Tous statuts</option>
                <option value="pending">En attente</option>
                <option value="pending_approval">Approbation requise</option>
                <option value="confirmed">Confirmee</option>
                <option value="in_progress">En cours</option>
                <option value="completed">Completee</option>
                <option value="cancelled">Annulee</option>
            </select>
        </div>

        {{-- Liste reservations --}}
        @if ($bookings->isEmpty())
            <div class="flex flex-col items-center rounded-2xl border-2 border-dashed border-slate-200 bg-white py-16 text-center">
                <p class="text-lg font-bold text-slate-700">Aucune reservation</p>
                <p class="mt-1 text-sm text-slate-400">Creez votre premiere demande de service</p>
                <button wire:click="$set('view', 'create')"
                    class="mt-4 rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
                    Nouvelle demande
                </button>
            </div>
        @else
            <div class="space-y-2">
                @foreach ($bookings as $booking)
                    @php
                        $statusConfig = [
                            'pending'          => ['label' => 'En attente',  'bg' => 'bg-slate-100 text-slate-600'],
                            'pending_approval' => ['label' => 'A approuver', 'bg' => 'bg-amber-100 text-amber-700'],
                            'confirmed'        => ['label' => 'Confirmee',   'bg' => 'bg-blue-100 text-blue-700'],
                            'in_progress'      => ['label' => 'En cours',    'bg' => 'bg-green-100 text-green-700'],
                            'completed'        => ['label' => 'Terminee',    'bg' => 'bg-emerald-100 text-emerald-700'],
                            'cancelled'        => ['label' => 'Annulee',     'bg' => 'bg-red-100 text-red-600'],
                        ];
                        $sc = $statusConfig[$booking->status] ?? ['label' => $booking->status, 'bg' => 'bg-slate-100 text-slate-600'];
                    @endphp
                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900">
                                {{ $booking->organizationSite?->name ?? 'Site inconnu' }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ $booking->scheduled_at ? \Carbon\Carbon::parse($booking->scheduled_at)->format('d/m/Y H:i') : '—' }}
                                @if ($booking->organizationSite?->city)
                                    · {{ $booking->organizationSite->city }}
                                @endif
                            </p>
                        </div>
                        @if ($booking->providerUser)
                            <div class="flex items-center gap-1.5">
                                <img alt="{{ $booking->providerUser->name }}" src="{{ $booking->providerUser->profile_photo_url }}"
                                     class="h-6 w-6 rounded-full object-cover"
                                     title="{{ $booking->providerUser->name }}">
                                <span class="hidden text-xs text-slate-500 sm:block">{{ $booking->providerUser->name }}</span>
                            </div>
                        @endif
                        <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $sc['bg'] }}">
                            {{ $sc['label'] }}
                        </span>
                        @if ($booking->mission && \Illuminate\Support\Facades\Route::has('client-company.missions.photos'))
                            <a href="{{ route('client-company.missions.photos', $booking->mission) }}"
                                class="inline-flex min-h-[44px] items-center rounded-lg bg-slate-100 px-3 py-1 text-[10px] font-semibold text-slate-700 hover:bg-slate-200"
                                title="Photos avant/après">
                                📷 Photos
                            </a>
                        @endif
                        @if ($booking->status === 'pending_approval' && $needsApproval)
                            <button wire:click="approveBooking({{ $booking->id }})"
                                class="inline-flex min-h-[44px] items-center rounded-lg bg-sky-600 px-3 py-1 text-[10px] font-bold text-white hover:bg-sky-700">
                                Approuver
                            </button>
                        @endif
                        <button wire:click="cancelBooking({{ $booking->id }})"
                            wire:confirm="Annuler cette reservation ?"
                            class="inline-flex min-h-[44px] items-center rounded-lg bg-red-50 px-3 py-1 text-[10px] font-semibold text-red-600 hover:bg-red-100">
                            Annuler
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

    @else
        {{-- ══ VUE CREATE — Formulaire multi-etapes ══ --}}

        {{-- Stepper --}}
        <div class="mb-6 flex items-center gap-2">
            @foreach ([1 => 'Local', 2 => 'Metier', 3 => 'Planification', 4 => 'Confirmation'] as $n => $label)
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold
                        {{ $step >= $n ? 'bg-sky-600 text-white' : 'bg-slate-200 text-slate-500' }}">
                        {{ $n }}
                    </div>
                    <span class="hidden text-xs font-medium sm:block {{ $step >= $n ? 'text-sky-700' : 'text-slate-400' }}">
                        {{ $label }}
                    </span>
                </div>
                @if ($n < 4)
                    <div class="h-px flex-1 {{ $step > $n ? 'bg-sky-300' : 'bg-slate-200' }}"></div>
                @endif
            @endforeach
        </div>

        <div class="mx-auto max-w-2xl rounded-2xl bg-white shadow-sm border border-slate-200">

            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="text-lg font-black text-slate-900">
                    @if ($step === 1) Choisir un local
                    @elseif ($step === 2) Choisir le metier
                    @elseif ($step === 3) Planification et details
                    @else Confirmation
                    @endif
                </h3>
                <button wire:click="$set('view', 'list')" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 text-sm">
                    Annuler
                </button>
            </div>

            <div class="p-6 space-y-5">

                {{-- ── Etape 1 : Sélection local ── --}}
                @if ($step === 1)
                    @if ($sites->isEmpty())
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                            Aucun local enregistre.
                            <a href="{{ route('client-company.sites') }}" class="font-semibold underline">Ajouter un local</a>
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 max-h-64 overflow-y-auto">
                            @foreach ($sites as $site)
                                <button wire:click="selectSite({{ $site->id }})"
                                    class="cursor-pointer rounded-xl border p-3 text-left transition
                                        {{ $selectedSiteId == $site->id
                                            ? 'border-sky-500 bg-sky-50 ring-2 ring-sky-100'
                                            : 'border-slate-200 hover:border-slate-300' }}">
                                    <p class="text-sm font-semibold text-slate-900">{{ $site->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $site->city }}
                                        @if ($site->surface_m2) · {{ $site->surface_m2 }} m² @endif
                                    </p>
                                </button>
                            @endforeach
                        </div>
                        @error('selectedSiteId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    @endif
                @endif

                {{-- ── Etape 2 : Sélection métier ── --}}
                @if ($step === 2)
                    @if ($selectedSite)
                        <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-2 text-xs text-slate-500 mb-3">
                            Local : <span class="font-semibold text-slate-700">{{ $selectedSite->name }}</span>
                            @if ($selectedSite->city) — {{ $selectedSite->city }} @endif
                        </div>
                    @endif

                    @if ($trades->isEmpty())
                        <p class="text-sm text-slate-500">Aucun metier disponible pour le moment.</p>
                    @else
                        <p class="text-sm text-slate-500 mb-3">Quel type de service souhaitez-vous ?</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($trades as $trade)
                                <button wire:click="selectTrade({{ $trade->id }})"
                                    class="flex flex-col items-center gap-2 rounded-xl border p-4 text-center transition cursor-pointer
                                        {{ $selectedTradeId == $trade->id
                                            ? 'border-sky-500 bg-sky-50 ring-2 ring-sky-100'
                                            : 'border-slate-200 bg-white hover:border-sky-300 hover:bg-sky-50/40' }}">
                                    {{-- Un NOM d'icône, pas un émoji : imprimé tel quel, ce bouton
                                         affichait « wrench » ou « paint-roller » sous le métier. --}}
                                    @if ($trade->icon)
                                        <x-ui.icon :name="$trade->icon" class="h-6 w-6 text-slate-600" />
                                    @endif
                                    <span class="text-xs font-semibold text-slate-800">{{ $trade->name }}</span>
                                    @if ($trade->short_description)
                                        <span class="text-[10px] text-slate-400 leading-tight">{{ Str::limit($trade->short_description, 40) }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @error('selectedTradeId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                @endif

                {{-- ── Etape 3 : Planification + champs métier ── --}}
                @if ($step === 3)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1" for="scheduledDate">Date *</label>
                            <input id="scheduledDate" wire:model.live="scheduledDate" type="date"
                                min="{{ now()->format('Y-m-d') }}"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                            @error('scheduledDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1" for="scheduledTime">Heure *</label>
                            <input id="scheduledTime" wire:model.live="scheduledTime" type="time"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1" for="estimatedHours">Duree estimee</label>
                            <select id="estimatedHours" wire:model="estimatedHours"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-sky-500">
                                <option value="">Non precisee</option>
                                <option value="1">1 heure</option>
                                <option value="2">2 heures</option>
                                <option value="3">3 heures</option>
                                <option value="4">4 heures</option>
                                <option value="8">Journee complete</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1" for="purchaseOrderRef">N° bon de commande</label>
                            <input id="purchaseOrderRef" wire:model="purchaseOrderRef" type="text" placeholder="PO-2026-001"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-sky-500">
                        </div>
                    </div>

                    {{-- Champs specifiques au metier --}}
                    @if ($this->hasTradeFormSchema())
                        <div class="rounded-xl border border-blue-200 bg-blue-50/40 p-4 space-y-3">
                            <h4 class="text-sm font-semibold text-blue-900">Informations specifiques au metier</h4>
                            <x-trade-form-fields :schema="$tradeFormSchema" wire-model-prefix="tradeFormAnswers" />
                        </div>
                    @endif

                    {{-- Notes --}}
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1" for="notes">Notes / Instructions</label>
                        <textarea id="notes" wire:model="notes" rows="3"
                            placeholder="Instructions d'acces, code d'entree, contact sur place..."
                            class="w-full resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"></textarea>
                    </div>

                    {{-- Prestataire preferé --}}
                    @if ($providers->isNotEmpty() && ! $selectedSite?->preferred_provider_id)
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1" for="selectedProviderId">Prestataire prefere (optionnel)</label>
                            <select id="selectedProviderId" wire:model="selectedProviderId"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-sky-500">
                                <option value="">Attribution automatique</option>
                                @foreach ($providers as $provider)
                                    <option value="{{ $provider->user_id }}">{{ $provider->user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                @endif

                {{-- ── Etape 4 : Recapitulatif ── --}}
                @if ($step === 4)
                    <div class="space-y-3 text-sm">
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-2">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Local</span>
                                <span class="font-semibold text-slate-800">{{ $selectedSite?->name ?? '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Metier</span>
                                <span class="font-semibold text-slate-800">
                                    {{ $trades->firstWhere('id', $selectedTradeId)?->name ?? '—' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Date</span>
                                <span class="font-semibold text-slate-800">
                                    {{ $scheduledDate ? \Carbon\Carbon::parse($scheduledDate)->format('d/m/Y') : '—' }}
                                    @if ($scheduledTime) a {{ $scheduledTime }} @endif
                                </span>
                            </div>
                            @if ($estimatedHours)
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Duree</span>
                                    <span class="font-semibold text-slate-800">{{ $estimatedHours }}h</span>
                                </div>
                            @endif
                            @if ($purchaseOrderRef)
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Bon de commande</span>
                                    <span class="font-semibold text-slate-800">{{ $purchaseOrderRef }}</span>
                                </div>
                            @endif
                        </div>

                        @if ($needsApproval)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                                Cette demande sera soumise a l'approbation d'un responsable avant traitement.
                            </div>
                        @endif
                    </div>
                @endif

            </div>

            {{-- Footer navigation --}}
            <div class="flex gap-3 border-t border-slate-100 p-4">
                @if ($step > 1)
                    <button wire:click="prevStep"
                        class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Retour
                    </button>
                @endif

                @if ($step < 4)
                    <button wire:click="nextStep"
                        class="flex-1 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-700 disabled:opacity-50"
                        @if (($step === 1 && ! $selectedSiteId) || ($step === 2 && ! $selectedTradeId)) disabled @endif>
                        Suivant
                    </button>
                @else
                    <button wire:click="submitBooking"
                        wire:loading.attr="disabled"
                        class="flex-1 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="submitBooking">Envoyer la demande</span>
                        <span wire:loading wire:target="submitBooking">Envoi en cours...</span>
                    </button>
                @endif
            </div>
        </div>
    @endif
</div>
