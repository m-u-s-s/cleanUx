<?php

namespace App\Services\Email;

use App\Models\EmailTemplate;
use App\Models\EmailTheme;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * QUEL HABILLAGE POUR CET E-MAIL, CE JOUR-LÀ.
 *
 * L'ordre n'est pas négociable et il est le même partout : un thème IMPOSÉ par le gabarit gagne
 * toujours — une facture ne se déguise pas en Black Friday. Vient ensuite la SAISON, à priorité
 * décroissante. Puis le thème PAR DÉFAUT. Puis un repli intégré, pour qu'un envoi ne dépende
 * jamais de la présence d'une ligne en base.
 */
class MoteurDeThemeEmail
{
    /** Le thème qui habille ce gabarit à cette date. */
    public function pour(?EmailTemplate $gabarit = null, ?CarbonInterface $date = null): EmailTheme
    {
        $date ??= Carbon::now();

        // 1. LE GABARIT IMPOSE. Un e-mail de fraude ou de facture garde son habit en toute saison.
        if ($gabarit?->email_theme_id !== null) {
            $impose = $gabarit->emailTheme;

            if ($impose instanceof EmailTheme && $impose->is_active) {
                return $impose;
            }
        }

        return $this->deLaSaison($date) ?? $this->parDefaut();
    }

    /** Le thème saisonnier de plus haute priorité couvrant cette date, s'il y en a un. */
    public function deLaSaison(?CarbonInterface $date = null): ?EmailTheme
    {
        $date ??= Carbon::now();

        return EmailTheme::query()
            ->active()
            ->where('is_default', false)
            ->whereNotNull('starts_on')
            ->whereNotNull('ends_on')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->first(fn (EmailTheme $theme) => $theme->couvre($date));
    }

    /**
     * LE THÈME DE BASE, ET SON REPLI.
     *
     * Le repli n'est pas enregistré : un envoi ne doit pas dépendre de la présence d'une ligne.
     * Il porte exactement les valeurs que la coquille historique avait en dur.
     */
    public function parDefaut(): EmailTheme
    {
        $enBase = EmailTheme::query()->active()->where('is_default', true)->orderBy('id')->first();

        return $enBase ?? new EmailTheme([
            'code' => 'brio',
            'name' => 'Brio',
            'is_default' => true,
            'is_active' => true,
            'footer_text' => 'Brio — plateforme de gestion des interventions.',
        ]);
    }

    /**
     * Les thèmes saisonniers à venir ou en cours, pour l'écran d'administration.
     *
     * @return array<int, array{theme: EmailTheme, actif: bool}>
     */
    public function calendrier(?CarbonInterface $date = null): array
    {
        $date ??= Carbon::now();
        $actif = $this->deLaSaison($date);

        return EmailTheme::query()
            ->where('is_default', false)
            ->whereNotNull('starts_on')
            ->orderByDesc('priority')
            ->orderBy('starts_on')
            ->get()
            ->map(fn (EmailTheme $theme) => [
                'theme' => $theme,
                'actif' => $actif !== null && $actif->id === $theme->id,
            ])
            ->all();
    }
}
