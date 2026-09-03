<?php

namespace App\Services\Automation\Actions;

use App\Models\EmailSendRule;
use App\Models\EmailTemplate;
use App\Services\Automation\ActionResult;
use App\Services\Automation\Contracts\Action;
use App\Services\Email\EnvoiDEmail;
use Illuminate\Database\Eloquent\Model;

/**
 * LE MOTEUR D'AUTOMATISATION SAIT ENFIN ENVOYER UN E-MAIL.
 *
 * Il portait cinq actions — imposer, relancer, journaliser, notifier, pinguer — et AUCUNE ne
 * savait écrire à quelqu'un. C'est la porte qui manquait entre le studio et les événements.
 *
 * L'action ne rend PAS « réussie » quand l'envoi a été refusé : un plafond atteint ou un
 * désabonnement sont des décisions, pas des succès, et les confondre rendrait le journal du
 * moteur inutilisable le jour où un e-mail attendu n'arrive pas.
 */
class EnvoyerUnEmail implements Action
{
    public function __construct(private readonly EnvoiDEmail $envoi) {}

    public function cle(): string
    {
        return 'email.envoyer';
    }

    public function libelle(): string
    {
        return 'Envoyer un e-mail';
    }

    public function entitesSupportees(): array
    {
        return ['booking', 'alerte', 'mission'];
    }

    public function champs(): array
    {
        return ['gabarit' => 'texte', 'destinataire' => 'texte'];
    }

    /** Écrire à quelqu'un ne modifie aucune donnée du domaine. */
    public function toucheAuDomaine(): bool
    {
        return false;
    }

    public function executer(Model $entite, array $parametres): ActionResult
    {
        $code = trim((string) ($parametres['gabarit'] ?? ''));
        $destinataire = trim((string) ($parametres['destinataire'] ?? ''));

        if ($code === '' || $destinataire === '') {
            return ActionResult::echouee('Gabarit et destinataire sont requis.');
        }

        $gabarit = EmailTemplate::query()->where('code', $code)->first();

        if (! $gabarit instanceof EmailTemplate) {
            return ActionResult::echouee("Gabarit « {$code} » introuvable.");
        }

        // LA REGLE DE L'EVENEMENT porte le plafond et le respect de l'opt-out. Sans elle, l'envoi
        // reste possible mais SANS FREIN : c'est le cas d'un declencheur pose a la main.
        $regle = EmailSendRule::query()
            ->where('email_template_id', $gabarit->id)
            ->where('trigger_type', 'event')
            ->active()
            ->first();

        $resultat = $this->envoi->envoyer($gabarit, $destinataire, $this->variables($entite), $regle);

        return $resultat->parti
            ? ActionResult::reussie('E-mail envoyé à '.$destinataire.'.')
            : ActionResult::echouee($resultat->raison);
    }

    /**
     * LES VARIABLES QUE L'ENTITE SAIT DONNER.
     *
     * On ne lit que ce qui existe : un modèle sans `date` ne doit pas faire échouer un envoi.
     *
     * @return array<string, scalar|null>
     */
    private function variables(Model $entite): array
    {
        $variables = [];

        foreach (['client_name' => 'client_name', 'date' => 'date', 'heure' => 'heure',
            'adresse' => 'adresse', 'statut' => 'status'] as $cle => $colonne) {
            $valeur = $entite->getAttribute($colonne);

            if (is_scalar($valeur)) {
                $variables[$cle] = $valeur;
            }
        }

        $variables['action_url'] = url('/');

        return $variables;
    }
}
