{{--
    Le bandeau qui dit pourquoi le téléphone ne sonne plus — ou ne sonnera bientôt plus.

    Il ne s'affiche que s'il a quelque chose à dire. Un bandeau permanent sur un dossier complet
    devient du décor, et le jour où il compte vraiment, plus personne ne le lit.
--}}
<div @class(['space-y-3' => count($alertes)])>
    @foreach ($alertes as $alerte)
        <div @class([
            'rounded-2xl px-4 py-3 text-sm',
            'bg-rose-50 text-rose-900' => $alerte['deja_bloquant'],
            'bg-amber-50 text-amber-900' => ! $alerte['deja_bloquant'],
        ])>
            <p class="font-medium">
                @if ($alerte['deja_bloquant'])
                    Vous ne recevez plus les missions « {{ $alerte['metier'] }} ».
                @else
                    Dossier à compléter pour « {{ $alerte['metier'] }} ».
                @endif
            </p>

            <p class="mt-1">
                Il manque&nbsp;: {{ implode(', ', $alerte['manquants']) }}.
                @if (! $alerte['deja_bloquant'] && $alerte['bloquant_depuis'])
                    Ces missions cesseront de vous être proposées à partir du
                    {{ $alerte['bloquant_depuis']->format('d/m/Y') }}.
                @endif
            </p>

            <a href="{{ route('employe.driving') }}"
                class="mt-2 inline-block font-medium underline underline-offset-4">
                Compléter mon dossier de conduite
            </a>
        </div>
    @endforeach
</div>
