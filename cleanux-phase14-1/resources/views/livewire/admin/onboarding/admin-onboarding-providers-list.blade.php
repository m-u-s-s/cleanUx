<div class="p-4 md:p-6 space-y-6">

    {{-- Header --}}
    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">Administration</p>
            <h1 class="text-2xl md:text-3xl font-bold text-slate-900">Onboarding prestataires</h1>
            <p class="text-sm text-slate-500 mt-1">
                Suivi des inscriptions de nouveaux prestataires.
            </p>
        </div>

        <a href="{{ route('admin.onboarding.documents') }}"
           class="rounded-2xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
            📄 Documents à valider
        </a>
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

    {{-- Counts cards (cliquables pour filtrer) --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <button wire:click="$set('filterStatus', 'in_progress')"
                class="text-left bg-white rounded-2xl border-2 transition
                       {{ $filterStatus === 'in_progress' ? 'border-amber-400' : 'border-slate-200 hover:border-amber-200' }} p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">En cours d'inscription</div>
            <div class="mt-1 text-3xl font-bold text-amber-700">{{ $counts['in_progress'] }}</div>
            <div class="mt-1 text-xs text-slate-500">N'ont pas fini les étapes</div>
        </button>
        <button wire:click="$set('filterStatus', 'ready')"
                class="text-left bg-white rounded-2xl border-2 transition
                       {{ $filterStatus === 'ready' ? 'border-sky-400' : 'border-slate-200 hover:border-sky-200' }} p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Prêts à valider</div>
            <div class="mt-1 text-3xl font-bold text-sky-700">{{ $counts['ready'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Étapes terminées, validation admin requise</div>
        </button>
        <button wire:click="$set('filterStatus', 'verified')"
                class="text-left bg-white rounded-2xl border-2 transition
                       {{ $filterStatus === 'verified' ? 'border-emerald-400' : 'border-slate-200 hover:border-emerald-200' }} p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Validés</div>
            <div class="mt-1 text-3xl font-bold text-emerald-700">{{ $counts['verified'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Actifs sur la plateforme</div>
        </button>
    </div>

    {{-- Filtres --}}
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Recherche</label>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Nom ou email..."
                       class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
            </div>
            <div class="flex items-end">
                <button wire:click="clearFilters"
                        class="rounded-2xl bg-slate-100 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200 w-full">
                    Réinitialiser
                </button>
            </div>
        </div>
    </div>

    {{-- Tableau prestataires --}}
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Prestataire</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Étape</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Documents</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Stripe</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Statut</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($providers as $profile)
                    @php
                        $docs = $this->documentsCountFor($profile->user_id);
                        $totalDocs = $docs['approved'] + $docs['pending'] + $docs['rejected'];
                        $stepLabels = [
                            0 => 'Profil',
                            1 => 'Identité',
                            2 => 'Fiscal',
                            3 => 'Assurance',
                            4 => 'Compétences',
                            5 => 'Stripe',
                            6 => 'Validé',
                        ];
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $profile->user?->name ?? '—' }}</div>
                            <div class="text-xs text-slate-500">{{ $profile->user?->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="inline-flex flex-col items-center">
                                <span class="text-xs font-semibold text-slate-700">
                                    {{ $stepLabels[(int) $profile->onboarding_step] ?? '—' }}
                                </span>
                                <span class="text-xs text-slate-400">
                                    Étape {{ $profile->onboarding_step }} / 6
                                </span>
                                <div class="mt-1 h-1.5 w-20 bg-slate-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-sky-500" style="width: {{ ($profile->onboarding_step / 6) * 100 }}%"></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($totalDocs === 0)
                                <span class="text-xs text-slate-400">Aucun</span>
                            @else
                                <div class="inline-flex flex-col items-center gap-0.5">
                                    @if ($docs['approved'] > 0)
                                        <span class="text-xs text-emerald-700">✓ {{ $docs['approved'] }}</span>
                                    @endif
                                    @if ($docs['pending'] > 0)
                                        <span class="text-xs text-amber-700">⏳ {{ $docs['pending'] }}</span>
                                    @endif
                                    @if ($docs['rejected'] > 0)
                                        <span class="text-xs text-red-700">✕ {{ $docs['rejected'] }}</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php $stripeStatus = $profile->user?->stripe_connect_status; @endphp
                            @if ($stripeStatus === 'active')
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-700">✓ Actif</span>
                            @elseif ($stripeStatus === 'pending')
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-700">En cours</span>
                            @else
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-700">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($profile->verification_status === 'verified')
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700">
                                    ✓ Validé
                                </span>
                            @elseif ($profile->onboarding_step >= 5)
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-sky-100 text-sky-700">
                                    À approuver
                                </span>
                            @else
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-amber-100 text-amber-700">
                                    En cours
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($profile->verification_status !== 'verified' && $profile->onboarding_step >= 5)
                                <button wire:click="approveOnboarding({{ $profile->user_id }})"
                                        wire:confirm="Approuver définitivement l'onboarding de {{ $profile->user?->name }} ? Le prestataire pourra recevoir des missions immédiatement."
                                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                    Approuver
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-slate-500">
                            Aucun prestataire à afficher avec ces filtres.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($providers->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $providers->links() }}
            </div>
        @endif
    </div>
</div>
