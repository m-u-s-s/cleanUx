<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Platform\SiegeDuSuperAdmin;
use Illuminate\Console\Command;

/**
 * LE SIÈGE DE SUPER-ADMINISTRATEUR, EN LIGNE DE COMMANDE.
 *
 * C'est le seul geste qui peut prendre un siège VACANT — et il ne s'exécute que sur le serveur.
 * L'adresse est un argument, jamais une constante : rien de ce compte ne vit dans le dépôt.
 *
 * La phrase se saisit masquée et n'apparaît ni dans l'historique du shell, ni dans les journaux.
 */
class SiegeDeLaPlateforme extends Command
{
    protected $signature = 'plateforme:siege
        {email? : L’adresse du compte qui prend le siège}
        {--etat : Afficher qui détient le siège, sans rien changer}
        {--changer-la-phrase : Remplacer la phrase du titulaire actuel}';

    protected $description = 'Affiche, réclame ou reprotège le siège unique de super-administrateur.';

    public function handle(SiegeDuSuperAdmin $siege): int
    {
        if ($this->option('etat') || (! $this->argument('email') && ! $this->option('changer-la-phrase'))) {
            return $this->afficherLEtat($siege);
        }

        if ($this->option('changer-la-phrase')) {
            return $this->changerLaPhrase($siege);
        }

        return $this->reclamer($siege);
    }

    private function afficherLEtat(SiegeDuSuperAdmin $siege): int
    {
        $titulaire = $siege->titulaire();

        if ($titulaire === null) {
            $this->warn('Le siège est VACANT : aucun super-administrateur sur cette plateforme.');
            $this->line('  php artisan plateforme:siege <email>');

            return self::SUCCESS;
        }

        $this->info('Siège détenu par : '.$titulaire->email.' (#'.$titulaire->id.')');
        $this->line('  Pris le : '.($titulaire->seat_claimed_at?->toDateTimeString() ?? 'inconnu'));
        $this->line('  Phrase enregistrée : '.($titulaire->seat_secret_hash ? 'oui' : 'NON — le siège ne peut pas être transféré'));
        $this->line('  Second facteur : '.(empty($titulaire->two_factor_secret) ? 'non activé' : 'activé'));

        $transfert = $siege->transfertEnAttente();

        if ($transfert !== null) {
            $this->newLine();
            $this->warn('Un transfert est ARMÉ vers '.$transfert->to->email);
            $this->line('  Effectif le : '.$transfert->effective_at->toDateTimeString());
        }

        return self::SUCCESS;
    }

    private function reclamer(SiegeDuSuperAdmin $siege): int
    {
        if (! $siege->estVacant()) {
            $this->error('Le siège est occupé par '.$siege->titulaire()->email.'.');
            $this->line('Il se TRANSFÈRE depuis l’écran du titulaire, il ne se reprend pas ici.');

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $utilisateur = User::query()->where('email', $email)->first();

        if ($utilisateur === null) {
            $this->error('Aucun compte avec l’adresse '.$email.'.');

            return self::FAILURE;
        }

        $phrase = $this->demanderUnePhrase();

        if ($phrase === null) {
            return self::FAILURE;
        }

        try {
            $siege->reclamer($utilisateur, $phrase);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Siège pris par '.$utilisateur->email.'.');
        $this->line('Gardez cette phrase : sans elle, le siège ne peut plus être transféré depuis l’écran.');

        return self::SUCCESS;
    }

    private function changerLaPhrase(SiegeDuSuperAdmin $siege): int
    {
        $titulaire = $siege->titulaire();

        if ($titulaire === null) {
            $this->error('Le siège est vacant : il n’y a pas de phrase à changer.');

            return self::FAILURE;
        }

        $ancienne = (string) $this->secret('Phrase actuelle');
        $nouvelle = $this->demanderUnePhrase();

        if ($nouvelle === null) {
            return self::FAILURE;
        }

        try {
            $siege->changerLaPhrase($titulaire, $ancienne, $nouvelle);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Phrase remplacée.');

        return self::SUCCESS;
    }

    /** LA SAISIE EST MASQUÉE ET DOUBLE : une faute de frappe verrouillerait le siège pour de bon. */
    private function demanderUnePhrase(): ?string
    {
        $phrase = (string) $this->secret('Nouvelle phrase du siège (12 caractères minimum)');

        if ($phrase !== (string) $this->secret('Répétez la phrase')) {
            $this->error('Les deux saisies diffèrent.');

            return null;
        }

        return $phrase;
    }
}
