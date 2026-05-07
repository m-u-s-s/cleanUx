<div class="mx-auto max-w-7xl px-4 py-6">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Templates de récurrence</h1>
            <p class="text-sm text-slate-500">Crée une récurrence en 1 clic à partir d'un modèle prêt à l'emploi.</p>
        </div>
        @if (Route::has('client.recurring.index'))
            <a href="{{ route('client.recurring.index') }}"
               class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                Mes récurrences actives
            </a>
        @endif
    </div>

    {{-- Flash --}}
    @if ($flashMessage)
        <div class="mb-4 flex items-start justify-between rounded-lg border px-4 py-2 text-sm
                {{ $flashType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800' }}">
            <span>{{ $flashType === 'success' ? '✅' : '❌' }} {{ $flashMessage }}</span>
            <button wire:click="clearFlash" class="ml-2 text-slate-500 hover:text-slate-700">✕</button>
        </div>
    @endif

    {{-- Filtres catégorie --}}
    <div class="mb-5 flex flex-wrap gap-2">
        @foreach ($categories as $cat)
            @if ($cat['count'] > 0 || $cat['value'] === 'all')
                <button wire:click="setCategory('{{ $cat['value'] }}')"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition
                            {{ $selectedCategory === $cat['value'] ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
                    {{ $cat['label'] }}
                    <span class="rounded-full {{ $selectedCategory === $cat['value'] ? 'bg-blue-500' : 'bg-slate-100' }} px-1.5 text-[10px]">
                        {{ $cat['count'] }}
                    </span>
                </button>
            @endif
        @endforeach
    </div>

    {{-- Galerie cards --}}
    @if ($templates->isEmpty())
        <div class="rounded-lg border border-slate-200 bg-white p-12 text-center shadow-sm">
            <p class="text-sm text-slate-500">Aucun template dans cette catégorie.</p>
        </div>
    @else
        <div class="grid gap-3 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($templates as $template)
                <div class="group flex flex-col rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-md">
                    {{-- Header card --}}
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">{{ $template->icon ?: '🔁' }}</span>
                            <h3 class="text-sm font-bold text-slate-900 leading-tight">{{ $template->name }}</h3>
                        </div>
                        @if ($template->usage_count > 5)
                            <span class="inline-flex items-center gap-0.5 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
                                ⭐ Populaire
                            </span>
                        @endif
                    </div>

                    {{-- Description --}}
                    @if ($template->description)
                        <p class="mb-3 text-xs text-slate-600 leading-relaxed">{{ $template->description }}</p>
                    @endif

                    {{-- Détails techniques --}}
                    <div class="mb-3 flex-1 space-y-1 text-xs text-slate-500">
                        <div>🔁 {{ $template->human_description }}</div>
                        @if ($template->default_duration_minutes)
                            <div>⏱ Durée : {{ $template->default_duration_minutes }} min</div>
                        @endif
                    </div>

                    {{-- Bouton --}}
                    <button wire:click="openApplyModal({{ $template->id }})"
                            class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700">
                        Utiliser ce template
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ─────── Modal d'application ─────── --}}
    @if ($applyingTemplate)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
             x-data
             @click.self="$wire.closeApplyModal()">
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
                <div class="mb-3 flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">{{ $applyingTemplate->icon }}</span>
                            <h3 class="text-lg font-bold text-slate-900">{{ $applyingTemplate->name }}</h3>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $applyingTemplate->human_description }}</p>
                    </div>
                    <button wire:click="closeApplyModal" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="applyTemplate" class="space-y-3">
                    {{-- Site (uniquement pour entreprise) --}}
                    @if ($isCompany && $sites->isNotEmpty())
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Site</label>
                            <select wire:model="selectedSiteId"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <option value="">— Choisir un site —</option>
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}">
                                        {{ $site->name }}@if ($site->city) — {{ $site->city }}@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Date démarrage --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Date de démarrage</label>
                        <input type="date" wire:model="applyStartsAt"
                               min="{{ now()->addDay()->toDateString() }}"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    </div>

                    {{-- Heure --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Heure</label>
                        <input type="time" wire:model="applyCustomTime"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    </div>

                    {{-- Date fin (optionnel) --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">
                            Date de fin <span class="text-slate-400 font-normal">(optionnel)</span>
                        </label>
                        <input type="date" wire:model="applyEndsAt"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <p class="mt-1 text-[10px] text-slate-500">Laisser vide pour une récurrence sans fin.</p>
                    </div>

                    {{-- Nombre d'occurrences (optionnel) --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">
                            Limite d'occurrences <span class="text-slate-400 font-normal">(optionnel)</span>
                        </label>
                        <input type="number" wire:model="applyOccurrenceCount" min="1" max="500"
                               placeholder="ex: 12 (3 mois en hebdo)"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="submit"
                                class="flex-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                            ✅ Créer la récurrence
                        </button>
                        <button type="button" wire:click="closeApplyModal"
                                class="rounded-lg bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
