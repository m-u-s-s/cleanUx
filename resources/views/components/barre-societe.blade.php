@props(['espace'])

@php
    use App\Support\Navigation\EspaceCourant;
    use App\Support\Navigation\ModuleCatalogue;

    $prestataire = $espace === 'provider-company';
    $liens = ModuleCatalogue::principaux($espace);
    $routeModules = EspaceCourant::routeDesModules($espace);
    $routeAccueil = EspaceCourant::routeDAccueil($espace);
    $membre = Auth::user()?->membershipIn();

    $classeLien = fn (string $route) => request()->routeIs($route) || request()->routeIs($route.'.*')
        ? 'bg-slate-100 font-semibold text-slate-900 dark:bg-slate-700 dark:text-white'
        : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200';
@endphp

{{-- UNE SEULE BARRE POUR LES DEUX ESPACES SOCIETE, et pour les ecrans personnels qui y
     vivent desormais. Deux definitions cote a cote avaient deja diverge. --}}
<nav data-chrome="primary-nav" aria-label="Navigation principale"
    class="sticky top-0 z-40 flex h-14 items-center justify-between border-b border-slate-100 bg-white/95 px-4 backdrop-blur dark:border-slate-700 dark:bg-slate-900/95">

    <div class="flex min-w-0 flex-1 items-center gap-3">
        <a href="{{ route($routeAccueil) }}"
            class="flex flex-shrink-0 items-center gap-2 text-lg font-black text-slate-900 dark:text-white">
            <x-brand.logo :space="$prestataire ? 'provider' : 'client'" :size="32" />
            @if ($prestataire)
                Brio <span class="text-sky-600 dark:text-sky-400">Pro</span>
            @else
                {{-- La marque etait coupee par une balise : « Clean<span>Ux</span> ». Le
                     renommage global ne pouvait pas la voir. --}}
                Br<span class="text-sky-600 dark:text-sky-400">io</span>
            @endif
        </a>

        <div class="hidden min-w-0 items-center gap-1 overflow-x-auto sm:flex [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            {{-- Les liens viennent de `config/modules.php`, comme la page Modules. --}}
            @foreach ($liens as $lien)
                <a href="{{ route($lien['route']) }}"
                    class="flex flex-shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 text-sm transition {{ $classeLien($lien['route']) }}">
                    <span class="text-sm">{{ $lien['icon'] }}</span>
                    <span>{{ __($lien['label']) }}</span>
                </a>
            @endforeach

            {{-- La porte vers tout le reste : la barre ne porte que les principaux. --}}
            @if (\Illuminate\Support\Facades\Route::has($routeModules))
                <a href="{{ route($routeModules) }}"
                    class="flex flex-shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 text-sm transition {{ $classeLien($routeModules) }}">
                    <span class="text-sm">🧩</span>
                    <span>{{ __('Modules') }}</span>
                </a>
            @endif
        </div>
    </div>

    <div class="flex flex-shrink-0 items-center gap-2">
        @if (! $prestataire && \Illuminate\Support\Facades\Route::has('client-company.bookings.create'))
            <a href="{{ route('client-company.bookings.create') }}"
                class="hidden flex-shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700 sm:flex"
                title="{{ __('Demande rapide') }}" aria-label="{{ __('Demande rapide') }}">
                ⚡ <span class="hidden 2xl:inline">{{ __('Demande rapide') }}</span>
            </a>
        @endif

        <x-theme-toggle />
        <div class="hidden lg:block">
            <x-language-switcher />
        </div>
        <x-cloche-notifications />

        <a href="{{ route('profile.show') }}"
            class="flex items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-slate-100 dark:hover:bg-slate-800">
            <img alt="" src="{{ Auth::user()->profile_photo_url }}"
                class="h-7 w-7 rounded-full border border-slate-200 object-cover dark:border-slate-600">
            <div class="hidden text-right sm:block">
                <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ str(Auth::user()->name)->before(' ') }}</p>
                @if ($membre?->roleLabel())
                    <p class="text-[10px] text-sky-600 dark:text-sky-400">{{ $membre->roleLabel() }}</p>
                @endif
            </div>
        </a>
    </div>
</nav>
