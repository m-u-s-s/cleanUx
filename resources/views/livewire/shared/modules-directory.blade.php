<div class="mx-auto max-w-7xl px-4 py-8">
    <header>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white">Modules</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Tout ce que cet espace sait faire, rangé par fonction.
        </p>
    </header>

    @foreach ($groupes as $groupe)
        <section class="mt-8" wire:key="categorie-{{ $groupe['category'] }}">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                {{ $groupe['label'] }}
            </h2>

            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($groupe['modules'] as $module)
                    @php $heroicon = \App\Support\Navigation\ModuleIcons::heroicon($module['icon']); @endphp

                    <a href="{{ route($module['route']) }}"
                       wire:key="module-{{ $module['key'] }}"
                       class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition
                              hover:border-blue-300 hover:shadow-sm
                              dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-500">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                                     bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            @if ($heroicon)
                                <x-ui.icon :name="$heroicon" class="h-5 w-5" />
                            @else
                                {{-- Repli d'origine : un emoji non mappé vaut mieux qu'une icône vide. --}}
                                <span class="text-lg leading-none">{{ $module['icon'] }}</span>
                            @endif
                        </span>
                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                            {{ $module['label'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
