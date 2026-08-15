{{--
    LE CENTRE DE NOTIFICATIONS PERSONNEL, ET RIEN D'AUTRE.

    Cette page ouvrait sur le bandeau « Centre de communication & suivi qualité »
    (`livewire.shared.communication.layout-stack`) : un héros éditorial, quatre tuiles décoratives
    sans donnée, un tableau de liens et un mémo de process qualité — le tout AVANT la moindre
    notification. Or ce bandeau est du contenu d'administration : il reste sur les pages admin qui
    l'incluent, mais il n'a pas sa place au-dessus du courrier personnel d'un compte, qu'il
    s'agisse d'un client ou d'un prestataire.
--}}
<div class="mx-auto max-w-6xl space-y-6 px-4 py-8">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="ui-page-eyebrow !mt-0">{{ __('ui.notifications.title') }}</p>
            <h1 class="ui-page-title">{{ __('ui.notifications.center_title') }}</h1>
            <p class="ui-page-subtitle">{{ __('ui.notifications.center_subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 dark:border-brand-500/40 dark:bg-brand-500/15 dark:text-brand-200">
                <x-ui.icon name="bell" class="w-3.5 h-3.5" />
                {{ __('ui.notifications.unread_count', ['count' => $unreadCount]) }}
            </span>
            @if($unreadCount > 0)
                <button wire:click="markAllAsRead" class="brio-btn-primary inline-flex items-center gap-2 !py-2 !text-xs">
                    <x-ui.icon name="check" class="w-3.5 h-3.5" />
                    <span>{{ __('ui.notifications.mark_all_read') }}</span>
                </button>
            @endif
        </div>
    </div>

    {{--
        Filtres — LES TROIS, Y COMPRIS CELUI DU TYPE.

        `$type` était lié au queryString et appliqué dans le composant, mais aucun contrôle ne le
        rendait : `/notifications?type=finance` vidait la liste pendant que le sélecteur visible
        affichait toujours « Toutes ». Un filtre qu'on ne voit pas est un filtre qu'on ne peut pas
        désarmer.
    --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-soft-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="relative md:col-span-2">
                <x-ui.icon name="magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live.debounce.300ms="search" type="text"
                       aria-label="{{ __('ui.notifications.search_placeholder') }}"
                       placeholder="{{ __('ui.notifications.search_placeholder') }}"
                       class="ui-input !pl-9" />
            </div>

            <select wire:model.live="filter" aria-label="{{ __('ui.notifications.status_filter') }}" class="ui-input">
                <option value="all">{{ __('ui.notifications.all') }}</option>
                <option value="unread">{{ __('ui.notifications.unread') }}</option>
                <option value="read">{{ __('ui.notifications.read') }}</option>
            </select>

            <select wire:model.live="type" aria-label="{{ __('ui.notifications.type_filter') }}" class="ui-input">
                @foreach($typeOptions as $valeur => $libelle)
                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                @endforeach
            </select>
        </div>

        @if($hasActiveFilters)
            <div class="mt-3 flex justify-end">
                <button type="button" wire:click="resetFilters"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-700 transition hover:underline dark:text-brand-300">
                    <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                    {{ __('ui.notifications.reset_filters') }}
                </button>
            </div>
        @endif
    </div>

    {{-- Liste --}}
    <div class="space-y-2.5">
        @forelse($notifications as $notification)
            @php
                /*
                 * TOUT PASSE PAR LE PRESENTER — il était injecté dans cette vue et jamais appelé.
                 *
                 * La vue réimplémentait une version dégradée : `class_basename($notification->type)`
                 * affichait « RAPPELRDV » là où `label()` dit « Rendez-vous », le titre du payload
                 * n'était jamais rendu, la sévérité était ignorée, et surtout aucun lien
                 * n'existait — on lisait une notification sans pouvoir s'y rendre, alors que
                 * `actionUrl()` était déjà écrit et utilisé ailleurs.
                 *
                 * Même cause pour le contexte : la recherche indexe `invoice_number`, `zone_name`
                 * et `service_label` (`searchableText()`), trois champs que la carte n'affichait
                 * pas. On trouvait par un numéro de facture une carte qui ne le montrait pas.
                 */
                $estNonLue = is_null($notification->read_at);
                $libelle = $presenter->label($notification);
                $message = $presenter->message($notification);
                $contexte = $presenter->context($notification);
                $lien = $presenter->actionUrl($notification, auth()->user());

                /*
                 * SANS `title` DANS LE PAYLOAD, `title()` RETOMBE SUR LE LIBELLÉ DU TYPE — et la
                 * carte affichait alors « SYSTÈME » en pastille puis « Système » en titre, deux
                 * fois le même mot, avant le seul texte utile. Toutes les notifications n'ont pas
                 * de titre : dans ce cas c'est le MESSAGE qui porte le lien, pas un doublon.
                 */
                $titre = $presenter->title($notification);
                $entete = $titre === $libelle ? $message : $titre;

                /*
                 * L'accent porte SA propre variante sombre. Sans elle, `dark:border-brand-500/40`
                 * de la carte non-lue — une règle `border-color` sur les quatre côtés, émise dans
                 * la couche sombre — repeignait le bord gauche et effaçait la sévérité en mode
                 * sombre alors qu'elle restait visible en clair.
                 */
                $accent = match ($presenter->severity($notification)) {
                    'danger' => 'border-l-rose-500 dark:border-l-rose-400',
                    'warning' => 'border-l-amber-500 dark:border-l-amber-400',
                    'success' => 'border-l-emerald-500 dark:border-l-emerald-400',
                    'info' => 'border-l-sky-500 dark:border-l-sky-400',
                    default => 'border-l-slate-300 dark:border-l-slate-500',
                };
            @endphp

            <article class="rounded-xl border border-l-4 {{ $accent }} p-4 shadow-soft-sm transition hover:shadow-soft {{ $estNonLue
                ? 'border-brand-200 bg-brand-50/60 dark:border-brand-500/40 dark:bg-brand-500/10'
                : 'border-slate-200/70 bg-white dark:border-slate-700 dark:bg-slate-800' }}">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0 space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                {{ $libelle }}
                            </span>
                            @if($estNonLue)
                                <span class="inline-flex items-center gap-1 rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-white"></span>
                                    {{ __('ui.notifications.new') }}
                                </span>
                            @endif
                        </div>

                        <a href="{{ $lien }}"
                           class="block text-sm font-semibold text-slate-900 underline-offset-2 transition hover:text-brand-700 hover:underline dark:text-slate-100 dark:hover:text-brand-300">
                            {{ $entete }}
                        </a>

                        @if($message !== $entete)
                            <p class="text-sm text-slate-700 dark:text-slate-300">{{ $message }}</p>
                        @endif

                        {{-- Contraste relevé : cette ligne porte la référence et l'heure, les deux
                             informations les plus utiles, et elle était la moins lisible de la carte. --}}
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-600 dark:text-slate-400">
                            @isset($contexte['rdv_id'])
                                <span class="inline-flex items-center gap-1">
                                    <x-ui.icon name="briefcase" class="w-3 h-3" />
                                    #{{ $contexte['rdv_id'] }}
                                </span>
                            @endisset

                            @isset($contexte['invoice_number'])
                                <span class="inline-flex items-center gap-1">{{ $contexte['invoice_number'] }}</span>
                            @endisset

                            @isset($contexte['service'])
                                <span class="inline-flex items-center gap-1">{{ $contexte['service'] }}</span>
                            @endisset

                            @isset($contexte['zone'])
                                <span class="inline-flex items-center gap-1">{{ $contexte['zone'] }}</span>
                            @endisset

                            {{-- `context()` expose cinq champs ; les cinq sont rendus, sinon la
                                 recherche continue d'indexer ce que la carte cache. --}}
                            @isset($contexte['google_email'])
                                <span class="inline-flex items-center gap-1">
                                    <x-ui.icon name="envelope" class="w-3 h-3" />
                                    {{ $contexte['google_email'] }}
                                </span>
                            @endisset

                            <span class="inline-flex items-center gap-1">
                                <x-ui.icon name="clock" class="w-3 h-3" />
                                {{ $notification->created_at?->diffForHumans() }}
                            </span>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-1.5">
                        <a href="{{ $lien }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600">
                            <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                            <span class="hidden sm:inline">{{ __('ui.notifications.open') }}</span>
                        </a>

                        @if($estNonLue)
                            <button wire:click="markAsRead('{{ $notification->id }}')"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25">
                                <x-ui.icon name="check" class="w-3.5 h-3.5" />
                                <span class="hidden sm:inline">{{ __('ui.notifications.mark_read') }}</span>
                            </button>
                        @else
                            <button wire:click="markAsUnread('{{ $notification->id }}')"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-300 dark:hover:bg-amber-500/25">
                                <x-ui.icon name="bell" class="w-3.5 h-3.5" />
                                <span class="hidden sm:inline">{{ __('ui.notifications.mark_unread') }}</span>
                            </button>
                        @endif

                        <button wire:click="deleteNotification('{{ $notification->id }}')"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-500/40 dark:bg-rose-500/15 dark:text-rose-300 dark:hover:bg-rose-500/25">
                            <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                            <span class="hidden sm:inline">{{ __('ui.notifications.delete') }}</span>
                        </button>
                    </div>
                </div>
            </article>
        @empty
            {{-- Une boîte vide n'est pas un filtre trop strict : le message distingue les deux. --}}
            <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-800">
                <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-400">
                    <x-ui.icon name="bell" class="w-6 h-6" />
                </div>
                <p class="font-semibold text-slate-700 dark:text-slate-200">
                    {{ $hasAnyNotifications && $hasActiveFilters
                        ? __('ui.notifications.none_filtered')
                        : __('ui.notifications.none') }}
                </p>

                @if($hasAnyNotifications && $hasActiveFilters)
                    <button type="button" wire:click="resetFilters"
                            class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-brand-700 transition hover:underline dark:text-brand-300">
                        <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                        {{ __('ui.notifications.reset_filters') }}
                    </button>
                @endif
            </div>
        @endforelse
    </div>

    @if($notifications->hasPages())
        <div>
            {{ $notifications->links() }}
        </div>
    @endif
</div>
