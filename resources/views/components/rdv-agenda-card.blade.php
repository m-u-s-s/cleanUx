@props(['rdv'])

@php
    use App\Support\Domain\BookingStatus;

    $minutes = ($rdv->duree ?? $rdv->estimated_duration_minutes ?? 90) + 30;
    $urgent = $rdv->priorite === 'urgente';
    $haute = $rdv->priorite === 'haute';
    $intervenant = $rdv->employe?->name;

    /*
        L'ACCENT DE STATUT PASSE PAR UNE TRANCHE, PLUS PAR LE FOND.

        La carte large teinte son fond (`bg-emerald-50/70`). Ces teintes a modificateur
        d'opacite ne figurent dans aucune des listes de `glass.css` : le fond reste CLAIR
        en mode sombre pendant que l'encre, elle, passe au clair. Mesure du 2026-08-30 sur
        cet ecran : encre 234/240/251 sur surface 248/250/252, soit 1,3:1.
        (Ecrire ces triplets sous forme de fonction CSS ferait tomber le garde-fou des
        couleurs en dur, qui balaie aussi les commentaires.)

        Une barre pleine ne connait pas ce probleme : `bg-emerald-400` n'est transmute par
        rien, et le fond de la carte suit le theme comme le reste de l'application.
    */
    $accent = match ($rdv->status) {
        BookingStatus::CONFIRME => ['barre' => 'bg-emerald-400', 'libelle' => 'Confirmé'],
        BookingStatus::EN_ROUTE => ['barre' => 'bg-blue-400', 'libelle' => 'En route'],
        BookingStatus::SUR_PLACE => ['barre' => 'bg-indigo-400', 'libelle' => 'Sur place'],
        BookingStatus::TERMINE => ['barre' => 'bg-slate-300 dark:bg-slate-600', 'libelle' => 'Terminé'],
        BookingStatus::REFUSE => ['barre' => 'bg-rose-400', 'libelle' => 'Refusé'],
        default => ['barre' => 'bg-amber-400', 'libelle' => 'En attente'],
    };
@endphp

<article
    class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white py-2 pl-3.5 pr-2.5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md focus-within:ring-2 focus-within:ring-sky-400 motion-reduce:transform-none {{ $urgent ? 'ring-1 ring-rose-400/60' : '' }}"
    title="{{ $accent['libelle'] }} — {{ substr((string) $rdv->heure, 0, 5) }} · {{ $rdv->service_display_name }}">

    {{-- La tranche de statut. `aria-hidden` : le libelle est rendu en clair ci-dessous,
         la couleur ne porte jamais seule l'information. --}}
    <span aria-hidden="true" class="absolute inset-y-0 left-0 w-1 {{ $accent['barre'] }}"></span>

    <p class="text-[13px] font-black tabular-nums tracking-tight text-slate-900">
        {{ substr((string) $rdv->heure, 0, 5) }}
    </p>

    <p class="mt-0.5 line-clamp-2 text-xs font-bold leading-snug text-slate-900">
        {{ $rdv->service_display_name }}
    </p>

    <p class="mt-1 truncate text-[11px] {{ $intervenant ? 'text-slate-500' : 'font-semibold text-amber-600 dark:text-amber-400' }}">
        {{ $intervenant ?? 'À assigner' }}
    </p>

    {{-- Le statut EN TOUTES LETTRES, sur la ligne basse ou il y a la place. Pose en
         haut a droite, il se coupait en deux (« EN / ATTENTE ») a 150 px — et la barre
         de couleur ne peut pas porter l'information seule. --}}
    <p class="mt-1.5 truncate text-[10px] font-semibold text-slate-500">
        {{ $accent['libelle'] }} · <span class="tabular-nums">{{ $minutes }} min</span>
    </p>

    @if($urgent || $haute)
        <p class="mt-1.5 text-[10px]">
            <span class="rounded-full px-1.5 py-px font-bold {{ $urgent ? 'bg-rose-100 text-rose-700' : 'bg-orange-100 text-orange-700' }}">
                {{ $urgent ? 'Urgente' : 'Haute' }}
            </span>
        </p>
    @endif

    {{--
        LA SURFACE CLIQUABLE COUVRE LA CARTE, ET RESTE UN VRAI BOUTON.

        Un `<button>` ne peut contenir que du contenu de phrase : envelopper ces
        paragraphes dedans donnerait un balisage invalide. Pose en dernier et etire sur
        la carte, il garde le clavier, le focus visible (`focus-within` sur la carte) et
        un nom accessible complet.
    --}}
    <button
        type="button"
        wire:click="ouvrirRdv({{ $rdv->id }})"
        wire:loading.attr="disabled"
        class="absolute inset-0 h-full w-full cursor-pointer focus:outline-none">
        <span class="sr-only">
            Ouvrir le rendez-vous de {{ substr((string) $rdv->heure, 0, 5) }} — {{ $rdv->service_display_name }}
        </span>
    </button>
</article>
