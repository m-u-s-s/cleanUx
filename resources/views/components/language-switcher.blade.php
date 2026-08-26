@php
    /*
        LA LISTE VIENT DE LA CONFIGURATION, PLUS D'ICI.

        Trois langues etaient codees en dur pendant que `config/i18n.php` en declarait SIX
        actives : l'espagnol, l'italien et l'allemand etaient annonces et impossibles a choisir.
        `LocaleResolver::availableForSwitcher()` existait exactement pour cela — l'API et la
        console d'administration l'appellent deja, ce selecteur-ci l'ignorait.

        Le code court s'obtient du code de langue : le maintenir a la main dans une troisieme
        liste, c'est se donner une troisieme occasion de diverger.
    */
    $locales = collect(app(\App\Services\I18n\LocaleResolver::class)->availableForSwitcher())
        ->mapWithKeys(fn (array $l): array => [$l['code'] => [
            'flag' => $l['flag'],
            'label' => $l['native_name'],
            'short' => strtoupper($l['code']),
        ]])
        ->all();

    $current = app()->getLocale();
    $currentInfo = $locales[$current] ?? reset($locales);
@endphp

<div x-data="{ open: false }" @click.away="open = false" class="relative inline-block">
    <button
        @click="open = !open"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
        :aria-expanded="open"
    >
        <span class="text-base leading-none">{{ $currentInfo['flag'] }}</span>
        <span>{{ $currentInfo['short'] }}</span>
        <svg class="h-3 w-3 text-slate-400 transition" :class="open ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        x-cloak
        class="absolute right-0 z-50 mt-1 w-44 origin-top-right rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
    >
        @foreach ($locales as $code => $info)
            <form method="POST" action="{{ route('locale.switch') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $code }}">
                <button
                    type="submit"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs transition
                        {{ $current === $code ? 'bg-blue-50 font-semibold text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}"
                >
                    <span class="text-base leading-none">{{ $info['flag'] }}</span>
                    <span>{{ $info['label'] }}</span>
                    @if ($current === $code)
                        <svg class="ml-auto h-3.5 w-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                        </svg>
                    @endif
                </button>
            </form>
        @endforeach
    </div>
</div>
