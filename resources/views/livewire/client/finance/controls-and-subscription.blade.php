<section class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_0.8fr]">
    <x-app-card padding="p-6" :title="__('Pilotage des documents')" :subtitle="__('Filtrez rapidement vos devis et factures.')">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Recherche
                </label>

                <input
                    type="text"
                    wire:model.live.debounce.350ms="search"
                    placeholder="Numéro, service, ville, adresse, référence…"
                    class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                >
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Tri
                </label>

                <select
                    wire:model.live="sort"
                    class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    @foreach($sortOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
            <div>
                <p class="text-sm font-semibold text-slate-700">Type de document</p>

                <div class="mt-2 flex flex-wrap gap-2">
                    <button wire:click="setDocumentType('all')" class="cu-chip {{ $documentType === 'all' ? '!border-slate-900 !bg-slate-900 !text-white' : '' }}">
                        Tous
                    </button>

                    <button wire:click="setDocumentType('quotes')" class="cu-chip {{ $documentType === 'quotes' ? '!border-slate-900 !bg-slate-900 !text-white' : '' }}">
                        Devis
                    </button>

                    <button wire:click="setDocumentType('invoices')" class="cu-chip {{ $documentType === 'invoices' ? '!border-slate-900 !bg-slate-900 !text-white' : '' }}">
                        Factures
                    </button>
                </div>
            </div>

            <div>
                <p class="text-sm font-semibold text-slate-700">Statut</p>

                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($statusOptions as $value => $label)
                        <button wire:click="setStatus('{{ $value }}')" class="cu-chip {{ $status === $value ? '!border-slate-900 !bg-slate-900 !text-white' : '' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Filtre actif</p>
                <p class="mt-1 text-sm font-bold text-slate-800">{{ $activeFilterLabel }}</p>
            </div>

            <button wire:click="resetFilters" class="cu-btn-secondary">
                Réinitialiser
            </button>
        </div>
    </x-app-card>

    <x-app-card padding="p-6" :title="__('Abonnement')" :subtitle="__('Votre plan actuel et ses avantages.')">
        <div class="space-y-3 text-sm">
            <div class="flex items-center justify-between">
                <span class="text-slate-500">Plan</span>
                <span class="font-semibold text-slate-800">
                    {{ ucfirst((string) $subscriptionSummary['plan_type']) }}
                </span>
            </div>

            <div class="flex items-center justify-between">
                <span class="text-slate-500">Statut</span>
                <span class="font-semibold {{ $subscriptionSummary['is_past_due'] ? 'text-rose-700' : ($subscriptionSummary['is_premium'] ? 'text-emerald-700' : 'text-slate-700') }}">
                    {{ $subscriptionSummary['is_premium'] ? 'Actif' : ucfirst((string) $subscriptionSummary['plan_status']) }}
                </span>
            </div>

            @if($subscriptionSummary['renewal_at'])
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Renouvellement</span>
                    <span class="font-semibold text-slate-800">
                        {{ optional($subscriptionSummary['renewal_at'])->format('d/m/Y') }}
                    </span>
                </div>
            @endif
        </div>

        <div class="mt-5 rounded-2xl border p-4 {{ $subscriptionSummary['is_premium'] ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50' }}">
            <p class="text-sm font-black {{ $subscriptionSummary['is_premium'] ? 'text-amber-800' : 'text-slate-800' }}">
                {{ $subscriptionSummary['is_premium'] ? 'Avantages Premium actifs' : 'Avantages Premium disponibles' }}
            </p>

            <ul class="mt-2 space-y-2 text-sm {{ $subscriptionSummary['is_premium'] ? 'text-amber-700' : 'text-slate-600' }}">
                <li>• Choix des employés favoris</li>
                <li>• Meilleure visibilité sur les disponibilités</li>
                <li>• Gestion plus simple des réservations</li>
            </ul>

            @if(! $subscriptionSummary['is_premium'])
                <a href="{{ route('premium.offer') }}" class="cu-btn-primary mt-4 !bg-amber-500 hover:!bg-amber-600">
                    Découvrir Premium
                </a>
            @endif
        </div>
    </x-app-card>
</section>
