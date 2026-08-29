@props([
    'count' => null,
    'apercu' => null,
])

@php
    // La barre principale a deja compte ; les autres gabarits laissent le composant compter.
    $utilisateur = auth()->user();
    $nb = $count ?? (auth()->check() ? min($utilisateur->unreadNotifications()->count(), 99) : 0);
    $items = $apercu ?? ($nb > 0 ? $utilisateur->unreadNotifications()->latest()->take(5)->get() : collect());
@endphp

@if (\Illuminate\Support\Facades\Route::has('notifications.index'))
    {{-- `@mouseleave` sur le CONTENEUR : sur la cloche seule, le panneau se fermerait
         des que la souris descendrait vers lui. --}}
    <div class="relative" x-data="{ ouvert: false }" @mouseenter="ouvert = true" @mouseleave="ouvert = false">
        <a href="{{ route('notifications.index') }}"
            data-cloche-compteur="{{ $nb }}"
            aria-label="Notifications{{ $nb > 0 ? ' ('.$nb.' non '.($nb > 1 ? 'lues' : 'lue').')' : '' }}"
            class="relative inline-flex items-center rounded-xl bg-slate-100 p-2 text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white">
            <x-ui.icon name="bell" class="h-5 w-5" />

            @if($nb > 0)
            <span class="absolute -end-1 -top-1 inline-flex min-w-[1.25rem] justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-black leading-none text-white">
                {{ $nb }}
            </span>
            @endif
        </a>

        <div x-show="ouvert"
            x-transition.opacity.duration.150ms
            x-cloak
            class="absolute end-0 z-50 mt-2 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800">
            <div class="border-b border-slate-100 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-400 dark:border-slate-700 dark:text-slate-500">
                Notifications
            </div>

            <div class="max-h-80 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700">
                @forelse($items as $notification)
                @php
                    $donnees = $notification->data ?? [];
                    $message = $donnees['message'] ?? __('ui.notifications.item_fallback');
                @endphp
                {{-- Chaque ligne mene a SA notification, pas au centre. --}}
                <a href="{{ route('notifications.show', $notification->id) }}" class="block px-4 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <p class="text-sm text-slate-800 dark:text-slate-100">{{ $message }}</p>
                    <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                        {{ $notification->created_at?->diffForHumans() }}
                    </p>
                </a>
                @empty
                <p class="px-4 py-6 text-center text-sm text-slate-400 dark:text-slate-500">
                    Aucune notification
                </p>
                @endforelse
            </div>

            @if($nb > count($items))
            <a href="{{ route('notifications.index') }}"
                class="block border-t border-slate-100 px-4 py-2.5 text-center text-xs font-semibold text-blue-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-blue-400 dark:hover:bg-slate-700/50">
                Voir les {{ $nb }} notifications
            </a>
            @endif
        </div>
    </div>
@endif
