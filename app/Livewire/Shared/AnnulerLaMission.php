<?php

namespace App\Livewire\Shared;

use App\Models\Booking;
use App\Models\CancellationQuestionOption;
use App\Services\Cancellation\CancellationQuestionnaireService;
use App\Services\CancellationV2\CancellationEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * ANNULER — la même porte pour les deux rôles, et un questionnaire plutôt qu'un champ libre.
 *
 * ── POURQUOI UN SEUL COMPOSANT ───────────────────────────────────────────────────────────────
 *
 * Client et prestataire répondent à des questions différentes, mais empruntent le MÊME tuyau :
 * `CancellationEngine`, ses politiques, ses paliers, sa capture partielle d'empreinte. Deux
 * composants auraient donné deux façons d'annuler la même réservation, et l'une des deux aurait
 * fini par diverger sur les frais. C'est l'audience qui change, pas le mécanisme.
 *
 * ── LE QUESTIONNAIRE EST UN AIGUILLAGE ───────────────────────────────────────────────────────
 *
 * Certaines réponses ne mènent PAS à une annulation : « le travail ne correspond pas » renvoie vers
 * le nouveau devis, « le chantier est trop gros » vers le renfort, « le client ne répond pas » vers
 * le no-show. On le montre au moment exact où la personne s'apprête à faire le mauvais geste —
 * après, elle a déjà annulé.
 *
 * ── LE MONTANT EST CELUI QU'ON PRÉLÈVE ───────────────────────────────────────────────────────
 *
 * L'aperçu passe par le même `quote()` que l'exécution, avec le même auteur : sans lui, le plafond
 * d'exemptions ne serait consulté qu'au moment du débit, et l'écran aurait annoncé « 0 € ».
 */
class AnnulerLaMission extends Component
{
    #[Locked]
    public int $bookingId;

    #[Locked]
    public string $role;

    public bool $ouvert = false;

    public string $optionChoisie = '';

    public string $precision = '';

    #[Locked]
    public ?string $erreur = null;

    public function mount(Booking $booking, string $role): void
    {
        abort_unless(in_array($role, ['client', 'provider'], true), 404);

        $this->assertPeutAnnuler($booking, $role);

        $this->bookingId = $booking->id;
        $this->role = $role;
    }

    public function ouvrir(): void
    {
        $this->erreur = null;
        $this->ouvert = true;
    }

    public function fermer(): void
    {
        $this->reset(['ouvert', 'optionChoisie', 'precision', 'erreur']);
    }

    public function render(): View
    {
        $booking = $this->booking();
        $option = $this->option();

        return view('livewire.shared.annuler-la-mission', [
            'booking' => $booking,
            'questions' => $this->ouvert
                ? app(CancellationQuestionnaireService::class)->pour(Auth::user(), $booking, $this->role)
                : [],
            'option' => $option,
            // L'aiguillage se calcule ICI : la vue ne décide pas si l'on annule ou si l'on renvoie.
            'aiguillage' => $option?->estUnAiguillage() ? $this->messageDAiguillage($option) : null,
            'devis' => $this->devis($option),
        ]);
    }

    /**
     * CONFIRMER — et seulement si la réponse mène réellement à une annulation.
     */
    public function confirmer(): void
    {
        $this->erreur = null;

        $option = $this->option();

        if ($option === null) {
            $this->erreur = 'Choisissez ce qui se passe.';

            return;
        }

        if ($option->estUnAiguillage()) {
            // Garde de dernier recours : l'écran n'offre pas ce bouton sur un aiguillage, mais un
            // appel Livewire se forge depuis la console du navigateur.
            $this->erreur = 'Cette réponse ne mène pas à une annulation.';

            return;
        }

        if ($option->requires_text && trim($this->precision) === '') {
            $this->erreur = 'Dites en une phrase ce qui se passe.';

            return;
        }

        try {
            $annulation = app(CancellationEngine::class)->execute(
                bookingId: $this->bookingId,
                actor: Auth::user(),
                actorRole: $this->role,
                reasonCode: $option->code,
                reasonText: trim($this->precision) ?: $option->label,
            );
        } catch (ValidationException $e) {
            $this->erreur = collect($e->errors())->flatten()->first() ?? 'Annulation impossible.';

            return;
        }

        /*
         * L'INSTANTANÉ DU QUESTIONNAIRE, écrit APRÈS coup et volontairement.
         *
         * Un libellé modifié demain ne doit pas altérer ce qui a été MONTRÉ hier. `reason_code`
         * suffit à compter ; l'instantané, lui, sert à relire un dossier tel qu'il s'est présenté.
         */
        $annulation->forceFill([
            'metadata' => array_merge($annulation->metadata ?? [], [
                'questionnaire' => [
                    'option_code' => $option->code,
                    'option_label' => $option->label,
                    'question_code' => $option->question?->code,
                    'verification' => $option->verification,
                    'collusion_signal' => (bool) $option->collusion_signal,
                    'audience' => $this->role,
                ],
            ]),
        ])->save();

        $this->fermer();
        $this->dispatch('mission-annulee');
    }

    /** Ce que l'aiguillage propose à la place — dit en clair, jamais en code. */
    private function messageDAiguillage(CancellationQuestionOption $option): string
    {
        return match ($option->outcome) {
            CancellationQuestionOption::ISSUE_VERS_DEVIS => 'Ce n’est pas une annulation : proposez '
                .'un nouveau devis depuis votre page terrain. Le client accepte ou refuse, et vous '
                .'gardez la mission.',
            CancellationQuestionOption::ISSUE_VERS_RENFORT => 'Ce n’est pas une annulation : '
                .'demandez du renfort. Un collègue vient, et l’intervention se fait.',
            CancellationQuestionOption::ISSUE_VERS_ABSENCE => 'Ce n’est pas une annulation : '
                .'déclarez l’absence du client depuis votre page terrain, une fois le délai écoulé. '
                .'Vous y gagnez l’indemnité qu’une annulation vous ferait perdre.',
            default => 'Cette réponse demande un examen : notre équipe vous répond.',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function devis(?CancellationQuestionOption $option): ?array
    {
        if ($option === null || $option->estUnAiguillage()) {
            return null;
        }

        try {
            return app(CancellationEngine::class)->quote(
                bookingId: $this->bookingId,
                actorRole: $this->role,
                reasonCode: $option->code,
                // L'AUTEUR AUSSI SUR L'APERÇU : sans lui, le plafond d'exemptions ne serait consulté
                // qu'au débit, et l'écran annoncerait « 0 € » avant de prélever.
                actorUserId: (int) Auth::id(),
            )->toArray();
        } catch (ValidationException) {
            return null;
        }
    }

    private function option(): ?CancellationQuestionOption
    {
        if ($this->optionChoisie === '') {
            return null;
        }

        return app(CancellationQuestionnaireService::class)->option($this->optionChoisie);
    }

    private function booking(): Booking
    {
        return Booking::query()->findOrFail($this->bookingId);
    }

    /**
     * QUI A LE DROIT D'ANNULER, ET JUSQU'À QUAND.
     *
     * Le client, jusqu'à la clôture. Le prestataire, AVANT le démarrage seulement : après, ce n'est
     * plus une annulation mais un abandon — deux faits différents pour le client, l'un le laisse
     * libre de recommander, l'autre le laisse avec un chantier ouvert.
     */
    private function assertPeutAnnuler(Booking $booking, string $role): void
    {
        if ($role === 'client') {
            abort_unless((int) $booking->client_id === (int) Auth::id(), 403);

            return;
        }

        $mission = $booking->missions()->latest('id')->first();

        abort_unless($mission !== null && $mission->estIntervenant(Auth::user()), 403);
        abort_if($mission->actual_start_at !== null, 403, 'L’intervention a démarré : ce n’est plus une annulation.');
    }
}
