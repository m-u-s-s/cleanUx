<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    
    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl md:text-3xl font-bold text-slate-900">{{ __('Mes employés favoris') }}</h1>

                @if($isPremium)
                <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">
                    {{ __('★ Premium') }}
                </span>
                @endif
            </div>

            <p class="text-sm text-slate-500 mt-2">
                @if($isPremium)
                {{ __('Sélectionnez vos employés favoris pour retrouver une expérience plus personnalisée.') }}
                @else
                {{ __('Cette fonctionnalité est disponible avec l’offre Premium mensuelle.') }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('client.dashboard') }}"
                class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                {{ __('Retour au dashboard') }}
            </a>

            @if(!$isPremium)
            <a href="{{ route('premium.offer') }}"
                class="inline-flex items-center justify-center rounded-2xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 transition">
                {{ __('Découvrir Premium') }}
            </a>
            @endif
        </div>
    </div>

    @if(!$isPremium)
    <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
        <p class="text-sm font-semibold text-amber-800">{{ __('Fonctionnalité Premium') }}</p>
        <h2 class="mt-2 text-xl font-bold text-slate-900">{{ __('Choisissez vos employés favoris') }}</h2>
        <p class="mt-3 text-sm text-slate-700 max-w-2xl">
            {{ __('Avec l’offre Premium mensuelle, vous pouvez sélectionner vos employés favoris, consulter leurs disponibilités et profiter d’une expérience plus personnalisée.') }}
        </p>

        <div class="mt-5">
            <a href="{{ route('premium.offer') }}"
                class="inline-flex items-center justify-center rounded-2xl bg-amber-500 px-5 py-3 text-sm font-semibold text-white hover:bg-amber-600 transition">
                {{ __('Passer en Premium') }}
            </a>
        </div>
    </div>
    @endif

    {{-- Search --}}
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
        <div class="max-w-md">
            <label class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Rechercher un employé') }}</label>
            <input type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Nom de l’employé...') }}"
                class="w-full rounded-2xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
        </div>
    </div>

    {{-- Employees grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($employes as $employe)
        @php
        $isFavorite = in_array($employe->id, $favoriteIds);
        @endphp

        <div class="bg-white rounded-3xl border {{ $isFavorite ? 'border-amber-200' : 'border-slate-200' }} shadow-sm p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-lg font-bold text-slate-900">{{ $employe->name }}</h3>

                        @if($isFavorite)
                        <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2.5 py-1 text-xs font-semibold text-amber-700">
                            {{ __('Favori') }}
                        </span>
                        @endif
                    </div>

                    <p class="text-sm text-slate-500 mt-2">
                        {{ __('Employé de votre équipe') }}
                    </p>
                </div>
            </div>

            <div class="mt-5 flex items-center gap-3">
                @if($isPremium)
                @if($isFavorite)
                <button type="button"
                    wire:click="removeFavorite({{ $employe->id }})"
                    class="inline-flex items-center justify-center rounded-2xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-100 transition">
                    {{ __('Retirer des favoris') }}
                </button>
                @else
                <button type="button"
                    wire:click="addFavorite({{ $employe->id }})"
                    class="inline-flex items-center justify-center rounded-2xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 transition">
                    {{ __('Ajouter aux favoris') }}
                </button>
                @endif

                <a href="{{ route('client.rendezvous.create', ['employe' => $employe->id]) }}"
                    class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                    {{ __('Réserver') }}
                </a>
                @else
                <a href="{{ route('premium.offer') }}"
                    class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 transition">
                    {{ __('Débloquer avec Premium') }}
                </a>
                @endif
            </div>
        </div>
        @empty
        <div class="md:col-span-2 xl:col-span-3">
            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                <p class="text-slate-700 font-medium">{{ __('Aucun employé trouvé.') }}</p>
                <p class="text-sm text-slate-500 mt-1">{{ __('Essayez une autre recherche.') }}</p>
            </div>
        </div>
        @endforelse
    </div>
</div>