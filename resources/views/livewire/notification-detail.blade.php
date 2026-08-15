@php
    $estNonLue = is_null($notification->read_at);

    $accents = match ($severite) {
        'danger' => ['bord' => 'border-l-rose-500 dark:border-l-rose-400', 'pastille' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'],
        'warning' => ['bord' => 'border-l-amber-500 dark:border-l-amber-400', 'pastille' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'],
        'success' => ['bord' => 'border-l-emerald-500 dark:border-l-emerald-400', 'pastille' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'],
        'info' => ['bord' => 'border-l-sky-500 dark:border-l-sky-400', 'pastille' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'],
        default => ['bord' => 'border-l-slate-300 dark:border-l-slate-500', 'pastille' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'],
    };

    $severites = [
        'danger' => __('ui.notifications.severity.danger'),
        'warning' => __('ui.notifications.severity.warning'),
        'success' => __('ui.notifications.severity.success'),
        'info' => __('ui.notifications.severity.info'),
        'default' => __('ui.notifications.severity.default'),
    ];

    /*
     * DEUX PASTILLES QUI DISENT LE MÊME MOT N'EN VALENT QU'UNE.
     *
     * Le type « Urgent » a pour sévérité « danger », dont le libellé est aussi « Urgent » : la
     * fiche affichait « URGENT  URGENT ». Le type prime — c'est lui qui classe la notification —
     * et la sévérité ne s'affiche que lorsqu'elle ajoute quelque chose. Même famille que le
     * « SYSTÈME / Système » de la liste.
     */
    $libelleSeverite = $severites[$severite] ?? $severite;
    $severiteAjouteQuelqueChose = mb_strtolower($libelleSeverite) !== mb_strtolower($libelle);
@endphp

<div class="mx-auto max-w-4xl space-y-6 px-4 py-8">
    <a href="{{ route('notifications.index') }}"
       class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 transition hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">
        <x-ui.icon name="arrow-left" class="w-4 h-4" />
        {{ __('ui.notifications.back_to_center') }}
    </a>

    {{-- En-tête : ce dont il s'agit, et à quel point c'est urgent. --}}
    <article class="rounded-2xl border border-l-4 {{ $accents['bord'] }} border-slate-200 bg-white p-6 shadow-soft-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                {{ $libelle }}
            </span>

            @if($severiteAjouteQuelqueChose)
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $accents['pastille'] }}">
                    {{ $libelleSeverite }}
                </span>
            @endif

            @if($estNonLue)
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-white">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-white"></span>
                    {{ __('ui.notifications.new') }}
                </span>
            @else
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-700 dark:text-slate-400">
                    <x-ui.icon name="check" class="w-3 h-3" />
                    {{ __('ui.notifications.already_read') }}
                </span>
            @endif
        </div>

        <h1 class="mt-3 text-xl font-bold tracking-tight text-slate-950 md:text-2xl dark:text-slate-100">
            {{ $titre }}
        </h1>

        @if($message !== $titre)
            <p class="mt-2 text-base leading-7 text-slate-700 dark:text-slate-300">{{ $message }}</p>
        @endif

        <p class="mt-3 text-xs text-slate-600 dark:text-slate-400">
            {{ $notification->created_at?->translatedFormat('l j F Y \à H:i') }}
            <span class="text-slate-400 dark:text-slate-500">— {{ $notification->created_at?->diffForHumans() }}</span>
        </p>
    </article>

    {{--
        LA RAISON D'ÊTRE DE CETTE PAGE.

        Le centre affiche des lignes qu'on lit ; il ne dit pas quoi FAIRE. Ce bloc porte le lien de
        résolution, nommé par sa destination — « Ouvrir » disait la même chose pour un virement et
        pour un rappel de mission — et l'URL est écrite en clair dessous : personne ne devrait
        cliquer sans savoir où il atterrit.
    --}}
    <section class="rounded-2xl border border-brand-200 bg-brand-50/60 p-6 dark:border-brand-500/40 dark:bg-brand-500/10">
        <h2 class="text-sm font-bold uppercase tracking-wide text-brand-800 dark:text-brand-200">
            {{ __('ui.notifications.resolve_title') }}
        </h2>
        <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
            {{ __('ui.notifications.resolve_help') }}
        </p>

        <a href="{{ $lienResolution }}"
           class="brio-btn-primary mt-4 inline-flex items-center gap-2">
            <x-ui.icon name="arrow-right" class="w-4 h-4" />
            <span>{{ $libelleResolution }}</span>
        </a>

        <p class="mt-2 break-all font-mono text-xs text-slate-500 dark:text-slate-400">{{ $lienResolution }}</p>
    </section>

    {{-- Tout le contenu du payload, y compris les clés qu'aucun écran ne connaît encore. --}}
    @if($payload !== [])
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-soft-sm dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700 dark:text-slate-200">
                {{ __('ui.notifications.details_title') }}
            </h2>

            <dl class="mt-4 divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($payload as $cle => $valeur)
                    <div class="flex flex-col gap-1 py-2.5 sm:flex-row sm:items-baseline sm:gap-4">
                        <dt class="w-56 shrink-0 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ $this->libellePayload($cle) }}
                        </dt>
                        <dd class="break-words text-sm text-slate-900 dark:text-slate-100">{{ $valeur }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    {{-- Traçabilité : sans ces trois lignes, une notification en litige n'est pas discutable. --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-soft-sm dark:border-slate-700 dark:bg-slate-800">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700 dark:text-slate-200">
            {{ __('ui.notifications.technical_title') }}
        </h2>

        <dl class="mt-4 divide-y divide-slate-100 dark:divide-slate-700">
            <div class="flex flex-col gap-1 py-2.5 sm:flex-row sm:items-baseline sm:gap-4">
                <dt class="w-56 shrink-0 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ __('ui.notifications.technical_reference') }}
                </dt>
                <dd class="break-all font-mono text-xs text-slate-700 dark:text-slate-300">{{ $notification->id }}</dd>
            </div>

            <div class="flex flex-col gap-1 py-2.5 sm:flex-row sm:items-baseline sm:gap-4">
                <dt class="w-56 shrink-0 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ __('ui.notifications.technical_source') }}
                </dt>
                <dd class="break-all font-mono text-xs text-slate-700 dark:text-slate-300">{{ class_basename((string) $notification->type) }}</dd>
            </div>

            <div class="flex flex-col gap-1 py-2.5 sm:flex-row sm:items-baseline sm:gap-4">
                <dt class="w-56 shrink-0 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ __('ui.notifications.technical_read_at') }}
                </dt>
                <dd class="text-sm text-slate-700 dark:text-slate-300">
                    {{ $notification->read_at?->translatedFormat('j F Y \à H:i') ?? __('ui.notifications.unread') }}
                </dd>
            </div>
        </dl>
    </section>

    <div class="flex flex-wrap gap-2">
        @if($estNonLue)
            <button wire:click="markAsRead"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25">
                <x-ui.icon name="check" class="w-4 h-4" />
                {{ __('ui.notifications.mark_read') }}
            </button>
        @else
            <button wire:click="markAsUnread"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-100 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-300 dark:hover:bg-amber-500/25">
                <x-ui.icon name="bell" class="w-4 h-4" />
                {{ __('ui.notifications.mark_unread') }}
            </button>
        @endif

        <button wire:click="deleteNotification"
                class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-500/40 dark:bg-rose-500/15 dark:text-rose-300 dark:hover:bg-rose-500/25">
            <x-ui.icon name="x-mark" class="w-4 h-4" />
            {{ __('ui.notifications.delete') }}
        </button>
    </div>
</div>
