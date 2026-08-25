{{--
    LA MODALE D'ANNULATION — dire ce que ça coûte AVANT de le prélever.

    Ce fichier contenait quinze lignes de JavaScript COLLÉES en clair, hors de toute balise
    `<script>` : elles s'affichaient telles quelles au client, en-tête `Authorization: Bearer`
    compris. Elles étaient la tentative abandonnée d'interroger `/cancellation-quote` avant de
    confirmer — si bien que le moteur prélevait des frais dont personne n'avait parlé, et que
    le client les découvrait sur son relevé.

    Le devis est désormais calculé côté serveur, où nous sommes déjà, et affiché ici. Sa devise
    vient du devis lui-même : un rendez-vous marocain ne s'annule pas en euros.
--}}
@if($cancelRdvId)
    @php($devis = $this->devisAnnulation)

    <div class="brio-modal-fond grid place-items-center p-4" x-data x-on:keydown.escape.window="$wire.fermerAnnulation()">
        <div class="brio-modal {{ $devis && $devis->feeAmountCents > 0 ? 'brio-modal-danger' : '' }}"
             role="alertdialog"
             aria-modal="true"
             aria-labelledby="titre-annulation">
            <h2 id="titre-annulation" class="brio-modal-titre">{{ __('Confirmer l’annulation') }}</h2>

            @if($devis && $devis->feeAmountCents > 0)
                {{--
                    LE MONTANT D'ABORD, la raison ensuite. C'est la seule information qui peut
                    faire changer d'avis, et elle arrivait après le bouton.
                --}}
                <p class="brio-modal-texte">
                    {{ __('Cette annulation entraîne des frais de') }}
                    <strong><x-money :amount="$devis->feeAmountCents / 100" :currency="$devis->currency" /></strong>@if($devis->tierLabel), {{ $devis->tierLabel }}@endif.
                </p>

                @if($devis->refundAmountCents > 0)
                    <p class="brio-modal-texte">
                        {{ __('Vous serez remboursé de') }}
                        <strong><x-money :amount="$devis->refundAmountCents / 100" :currency="$devis->currency" /></strong>.
                    </p>
                @endif
            @elseif($devis)
                <p class="brio-modal-texte">
                    {{ __('Cette annulation est sans frais.') }}
                    @if($devis->hoursBefore !== null)
                        {{ __('Il reste :heures h avant le rendez-vous.', ['heures' => $devis->hoursBefore]) }}
                    @endif
                </p>
            @else
                {{-- Le devis n'a pas pu être établi : on ne fabrique pas un montant rassurant. --}}
                <p class="brio-modal-texte">
                    {{ __('Des frais d’annulation peuvent s’appliquer selon le délai.') }}
                </p>
            @endif

            @foreach(($devis?->warnings ?? []) as $avertissement)
                <p class="brio-modal-texte">{{ $avertissement }}</p>
            @endforeach

            <label class="mt-4 block">
                <span class="sr-only">{{ __('Raison d’annulation') }}</span>
                <textarea
                    wire:model.defer="cancelReason"
                    rows="3"
                    placeholder="{{ __('Raison d’annulation (facultatif)…') }}"
                    class="w-full rounded-xl px-4 py-3 text-sm"></textarea>
            </label>

            <div class="brio-modal-actions">
                {{-- LE RETOUR PORTE LE FOCUS : une modale qui s'ouvre sur son bouton
                     destructeur transforme une touche Entrée en annulation payante. --}}
                <button type="button"
                        x-init="$el.focus()"
                        wire:click="fermerAnnulation"
                        class="brio-btn brio-btn-nu">{{ __('Retour') }}</button>

                <button type="button"
                        wire:click="confirmerAnnulation"
                        wire:loading.attr="disabled"
                        class="brio-btn brio-btn-accent">
                    @if($devis && $devis->feeAmountCents > 0)
                        {{ __('Annuler et payer') }}
                        <x-money :amount="$devis->feeAmountCents / 100" :currency="$devis->currency" />
                    @else
                        {{ __('Confirmer l’annulation') }}
                    @endif
                </button>
            </div>
        </div>
    </div>
@endif
