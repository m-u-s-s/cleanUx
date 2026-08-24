{{--
    Bouton de bascule clair / sombre. Toute la logique vit dans `<x-theme-amorce />`.
    `variant="responsive"` rend la version pleine largeur du menu mobile.
--}}
@props(['variant' => 'inline'])

@php
    $lune = 'M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z';
    $soleil = 'M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z';
    $svg = 'h-5 w-5 shrink-0';
@endphp

@if ($variant === 'responsive')
    {{-- Pleine largeur et 44 px de haut : la cible tactile exigée par le harnais mobile. --}}
    <button type="button" x-data x-on:click="window.brioTheme.basculer()"
            class="flex min-h-11 w-full items-center gap-3 border-l-4 border-transparent px-4 py-2 text-start text-base font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200">
        {{-- La lune en clair, le soleil en sombre : l'icône montre où l'on va. --}}
        <svg class="{{ $svg }} dark:hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $lune }}" /></svg>
        <svg class="{{ $svg }} hidden dark:block" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $soleil }}" /></svg>
        <span class="dark:hidden">{{ __('Mode sombre') }}</span>
        <span class="hidden dark:inline">{{ __('Mode clair') }}</span>
    </button>
@else
    <button type="button" x-data x-on:click="window.brioTheme.basculer()"
            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            aria-label="{{ __('Changer le thème') }}">
        <svg class="{{ $svg }} dark:hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $lune }}" /></svg>
        <svg class="{{ $svg }} hidden dark:block" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $soleil }}" /></svg>
    </button>
@endif
