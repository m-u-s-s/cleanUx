<?php

namespace App\Services\Email;

use App\Models\EmailSendRule;
use App\Models\EmailTemplate;
use App\Models\MarketingOptOut;
use App\Models\User;
use App\Services\EmailV2\EmailService;
use App\Services\Marketing\OptOutService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LE PASSAGE DU GABARIT À LA BOÎTE DE RÉCEPTION.
 *
 * Trois freins se posent AVANT le rendu, dans cet ordre — c'est le seul qui tienne :
 *
 *   1. LE GABARIT EST-IL ACTIF ? Un brouillon ne part pas, même appelé par une règle.
 *   2. LE DESTINATAIRE A-T-IL REFUSÉ ? L'opt-out vaut pour le marketing, JAMAIS pour une alerte
 *      de fraude : refuser une publicité n'est pas renoncer à être prévenu qu'on vous vole.
 *   3. LE PLAFOND EST-IL ATTEINT ? Sur une fenêtre glissante, par destinataire et par gabarit.
 *      Sans lui, une règle mal réglée transforme la plateforme en source de courrier indésirable
 *      — et brûle l'adresse d'expédition avec elle.
 *
 * Le rendu ne vient qu'après : composer un document qu'on n'enverra pas est du travail perdu.
 */
class EnvoiDEmail
{
    /** Les catégories qui ne se refusent jamais, quoi qu'ait coché le destinataire. */
    public const CATEGORIES_INCONTOURNABLES = ['fraude', 'transactionnel', 'interne'];

    public function __construct(
        private readonly MoteurDeThemeEmail $themes,
        private readonly RenduDeBlocsEmail $rendu,
        private readonly EmailService $expedition,
    ) {}

    /**
     * Envoie un gabarit à une adresse, sous la règle donnée.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public function envoyer(
        EmailTemplate $gabarit,
        string $destinataire,
        array $variables = [],
        ?EmailSendRule $regle = null,
        ?string $locale = null,
    ): ResultatDEnvoi {
        if (! $gabarit->is_active) {
            return ResultatDEnvoi::refuse('Le gabarit est inactif.');
        }

        if ($this->estRefuse($gabarit, $destinataire, $regle)) {
            return ResultatDEnvoi::refuse('Le destinataire s’est désabonné de cette catégorie.');
        }

        if ($regle !== null && $this->plafondAtteint($gabarit, $destinataire, $regle)) {
            return ResultatDEnvoi::refuse('Plafond atteint pour ce destinataire sur la fenêtre.');
        }

        $theme = $this->themes->pour($gabarit);

        $objet = $this->substitue($gabarit->subjectForLocale($locale), $variables);

        $html = $this->rendu->documentComplet(
            $gabarit->blocsPourLaLangue($locale),
            $theme,
            $variables,
            $objet,
            $gabarit->preheader,
        );

        $message = $this->expedition->send([
            'to_email' => $destinataire,
            'subject' => $objet,
            'body_html' => $html,
            'template_code' => (string) $gabarit->code,
            'locale' => $locale,
            'metadata' => [
                'email_send_rule_id' => $regle?->id,
                'email_theme' => $theme->code,
            ],
        ]);

        return $message === null
            ? ResultatDEnvoi::refuse('La couche d’expédition est indisponible.')
            : ResultatDEnvoi::parti((int) $message->id, $objet);
    }

    /**
     * LE REFUS DU DESTINATAIRE, ET SES LIMITES.
     *
     * LE DRAPEAU D'UNE RÈGLE NE PEUT QUE RESSERRER, JAMAIS DESSERRER. Seule la CATÉGORIE rend un
     * e-mail incontournable ; une règle peut décider de respecter l'opt-out là où la catégorie
     * l'exempterait — l'inverse est interdit.
     *
     * Sans cette asymétrie, cocher une case sur une règle suffirait à écrire à quelqu'un qui a dit
     * non, et le désabonnement ne vaudrait plus rien.
     */
    public function estRefuse(EmailTemplate $gabarit, string $destinataire, ?EmailSendRule $regle = null): bool
    {
        $categorieExempte = in_array((string) $gabarit->category, self::CATEGORIES_INCONTOURNABLES, true);

        if ($categorieExempte && ($regle === null || ! $regle->respects_opt_out)) {
            return false;
        }

        if (! Schema::hasTable('marketing_opt_outs')) {
            return false;
        }

        // LA TABLE EST INDEXEE PAR `user_id` ET `channel`, PAS PAR `email`. Interroger une
        // colonne `email` inexistante rendait TOUJOURS faux sur SQLite — l'identifiant inconnu
        // y devient une chaine litterale — et aurait leve sur MySQL. Le refus n'a donc jamais
        // fonctionne. `OptOutService` connait la vraie forme, et traite aussi le canal « tout ».
        $compte = User::query()->where('email', $destinataire)->first();

        return $compte instanceof User
            && app(OptOutService::class)->isOptedOut($compte, MarketingOptOut::CHANNEL_EMAIL);
    }

    /** Combien ce destinataire a déjà reçu de ce gabarit sur la fenêtre de la règle. */
    public function envoisRecents(EmailTemplate $gabarit, string $destinataire, int $fenetreHeures): int
    {
        if (! Schema::hasTable('email_messages')) {
            return 0;
        }

        return DB::table('email_messages')
            ->where('to_email', $destinataire)
            ->where('template_code', (string) $gabarit->code)
            ->where('created_at', '>=', Carbon::now()->subHours(max(1, $fenetreHeures)))
            ->count();
    }

    private function plafondAtteint(EmailTemplate $gabarit, string $destinataire, EmailSendRule $regle): bool
    {
        // ZERO VEUT DIRE « PAS DE PLAFOND », pas « zéro envoi » : une borne absente ne bloque rien.
        if ($regle->cap_per_recipient <= 0) {
            return false;
        }

        return $this->envoisRecents($gabarit, $destinataire, $regle->cap_window_hours)
            >= $regle->cap_per_recipient;
    }

    /** @param array<string, scalar|null> $variables */
    private function substitue(string $sujet, array $variables): string
    {
        foreach ($variables as $cle => $valeur) {
            $propre = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $cle);
            $sujet = str_replace(['{{'.$propre.'}}', '{{ '.$propre.' }}'], (string) $valeur, $sujet);
        }

        return $sujet;
    }
}
