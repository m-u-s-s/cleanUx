{{--
    Un POINT sur la carte, pas une ligne de texte.

    Le type `address` voisin est un champ libre : ce qu'on y tape n'a ni coordonnées ni zone, et
    c'est très bien pour une précision d'accès. Ici, la réponse doit être SITUÉE — c'est d'elle que
    sortent la distance, le prix au kilomètre, l'itinéraire et le point où la course se termine.
    Le champ pousse donc vers la sélection d'une proposition ou l'usage de la position du téléphone,
    et refuse une adresse tapée que personne n'a su placer.
--}}
<div class="space-y-2" wire:key="location-{{ $question->code }}">

    @if (is_array($value) && ($value['lat'] ?? null) !== null)
        {{-- Le lieu est retenu : on montre CE QUI A ÉTÉ CHOISI, pas un champ qu'il faudrait relire. --}}
        <div class="flex items-start justify-between gap-3 rounded-xl border border-slate-900/10 bg-slate-50 px-4 py-3">
            <span class="flex min-w-0 items-start gap-2">
                <span aria-hidden="true" class="mt-0.5 text-slate-400">◎</span>
                <span class="min-w-0">
                    <span class="block truncate text-[15px] font-medium text-slate-900">{{ $value['label'] }}</span>
                    @if ($value['postal_code'] ?? null)
                        <span class="block text-xs text-slate-500">{{ $value['postal_code'] }}</span>
                    @endif
                </span>
            </span>

            <button type="button" wire:click="clearLocation"
                class="shrink-0 text-sm font-medium text-slate-500 underline underline-offset-4 transition hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2">
                Changer
            </button>
        </div>
    @else
        <input type="text"
            wire:model.live.debounce.400ms="locationQuery"
            id="{{ $question->code }}"
            autocomplete="street-address"
            placeholder="{{ $question->placeholder ?: 'Rue, numéro, ville' }}"
            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-[15px] text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">

        @if (count($this->locationSuggestions))
            {{--
                LA LISTE S'AMÈNE À L'ŒIL, ET DÉGAGE LA BARRE DU POUCE.

                Elle naît SOUS le pli quand le champ est bas : la barre fixe la recouvrait, et
                l'appui atteignait « Continuer » au lieu de la suggestion — l'adresse restait donc
                sans coordonnées, sans que rien ne le dise. `pb-28` ne réserve que la FIN de page.
            --}}
            <ul x-data x-init="$nextTick(() => $el.scrollIntoView({ block: 'nearest', behavior: 'smooth' }))"
                class="scroll-mb-32 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200">
                @foreach ($this->locationSuggestions as $suggestion)
                    <li>
                        <button type="button"
                            wire:click="chooseLocation(
                                @js($suggestion->description),
                                @js($suggestion->latitude),
                                @js($suggestion->longitude),
                                @js($suggestion->postalCode)
                            )"
                            class="flex w-full items-start gap-2 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50 focus-visible:bg-slate-50 focus-visible:outline-none">
                            <span aria-hidden="true" class="mt-0.5 text-slate-400">◎</span>
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-slate-900">{{ $suggestion->mainText ?? $suggestion->description }}</span>
                                @if ($suggestion->secondaryText)
                                    <span class="block truncate text-xs text-slate-500">{{ $suggestion->secondaryText }}</span>
                                @endif
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        {{--
            Sur un trajet, « ma position » n'est pas un raccourci : le départ d'une course est
            presque toujours là où se trouve le téléphone. Le bouton reste inerte si le navigateur
            refuse la géolocalisation — on ne bloque pas la saisie manuelle pour autant.
        --}}
        <button type="button"
            x-data
            x-on:click="
                if (! navigator.geolocation) { return }
                navigator.geolocation.getCurrentPosition(
                    (p) => $wire.useMyLocation(p.coords.latitude, p.coords.longitude),
                    () => {},
                    { enableHighAccuracy: true, timeout: 8000 }
                )
            "
            class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 underline underline-offset-4 transition hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2">
            <span aria-hidden="true">⌖</span> Utiliser ma position
        </button>
    @endif
</div>
