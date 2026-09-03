<?php

namespace App\Services\Platform;

use App\Models\Concerns\HasAdminCapabilities;
use App\Models\PlatformSeatTransfer;
use App\Models\User;
use App\Support\Platform\PorteDuSiege;
use DomainException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * LE SIÈGE DE SUPER-ADMINISTRATEUR — la seule porte qui le pose et le déplace.
 *
 * IL N'Y A QU'UN SIÈGE, ET IL VIT DANS LES DONNÉES. Aucune adresse en dur, aucune variable
 * d'environnement : son titulaire est la ligne `users` portant `platform_role = 'super_admin'`,
 * désignée à l'exécution. Un index unique en base rend le second physiquement impossible ; le
 * crochet de {@see HasAdminCapabilities} refuse qu'on le déplace ailleurs
 * qu'ici.
 *
 * TROIS PREUVES POUR LE DÉPLACER, et elles ne se remplacent pas :
 *   1. être le titulaire ;
 *   2. la PHRASE DU SIÈGE — distincte du mot de passe de connexion, donc une session volée ou
 *      un mot de passe deviné ne suffisent pas ;
 *   3. un code d'authentification à deux facteurs, quand le titulaire en a activé un.
 *
 * ET LE TEMPS. Un transfert s'arme, il ne s'applique pas : le titulaire est prévenu et garde le
 * délai pour l'annuler. Un voleur de session ne peut donc ni faire vite, ni faire en silence.
 *
 * CE QUE CECI NE PROTÈGE PAS, et il faut le dire : quiconque tient la base ou le serveur peut
 * écrire ce qu'il veut. Aucune garde applicative n'y change rien — seuls les accès à
 * l'infrastructure le peuvent.
 */
class SiegeDuSuperAdmin
{
    /** Cinq essais par quart d'heure : une phrase ne se devine pas à la volée. */
    private const ESSAIS = 5;

    private const FENETRE_SECONDES = 900;

    public function titulaire(): ?User
    {
        return User::query()->where('platform_role', User::PLATFORM_SUPER_ADMIN)->first();
    }

    public function estVacant(): bool
    {
        return $this->titulaire() === null;
    }

    public function transfertEnAttente(): ?PlatformSeatTransfer
    {
        return PlatformSeatTransfer::query()->enAttente()->with(['from', 'to'])->latest('armed_at')->first();
    }

    public function delaiEnHeures(): int
    {
        return max(0, (int) Config::get('brio.seat.transfer_delay_hours', 24));
    }

    /**
     * PRENDRE UN SIÈGE VACANT.
     *
     * C'est le seul geste qui n'exige pas la phrase : il n'y a personne à qui la demander. Il se
     * paie donc autrement — il ne s'exécute qu'en ligne de commande, sur le serveur.
     *
     * @throws DomainException
     */
    public function reclamer(User $utilisateur, string $phrase): User
    {
        if (! $this->estVacant()) {
            throw new DomainException('Le siège est occupé : il se transfère, il ne se reprend pas.');
        }

        $this->exigeUnePhraseSolide($phrase);

        return PorteDuSiege::ouvrir(function () use ($utilisateur, $phrase): User {
            $utilisateur->forceFill([
                'platform_role' => User::PLATFORM_SUPER_ADMIN,
                'seat_secret_hash' => Hash::make($phrase),
                'seat_claimed_at' => now(),
                'is_active' => true,
            ])->save();

            return $utilisateur->refresh();
        });
    }

    /**
     * ARMER UN TRANSFERT — il ne s'applique pas tout de suite.
     *
     * La cible doit déjà être un administrateur actif : le siège ne fabrique pas un pouvoir à
     * partir de rien, il déplace celui qui existe.
     *
     * @throws DomainException
     */
    public function armerLeTransfert(
        User $titulaire,
        User $vers,
        string $phrase,
        ?string $codeDouble = null,
        ?string $ip = null,
        ?string $agent = null,
    ): PlatformSeatTransfer {
        $this->exigeLeTitulaire($titulaire);
        $this->exigeLaPhrase($titulaire, $phrase);
        $this->exigeLeSecondFacteur($titulaire, $codeDouble);

        if ($vers->id === $titulaire->id) {
            throw new DomainException('Vous détenez déjà le siège.');
        }

        if (! $vers->isAdmin() || ! ($vers->is_active ?? false)) {
            throw new DomainException('Le siège ne se transfère qu’à un administrateur actif.');
        }

        // UN SEUL TRANSFERT À LA FOIS : armer le suivant annule le précédent, sinon deux
        // transferts mûrs se disputeraient le siège au même passage.
        PlatformSeatTransfer::query()->enAttente()->update([
            'cancelled_at' => now(),
            'cancelled_reason' => 'Remplacé par un transfert plus récent.',
        ]);

        return PlatformSeatTransfer::create([
            'from_user_id' => $titulaire->id,
            'to_user_id' => $vers->id,
            'armed_at' => now(),
            'effective_at' => now()->addHours($this->delaiEnHeures()),
            'armed_ip' => $ip,
            'armed_user_agent' => $agent === null ? null : mb_substr($agent, 0, 255),
        ]);
    }

    /**
     * ANNULER — c'est le geste qui rend le délai utile.
     *
     * Il exige la phrase lui aussi : sans cela, celui qui a volé la session annulerait
     * l'annulation du vrai titulaire.
     *
     * @throws DomainException
     */
    public function annulerLeTransfert(User $titulaire, string $phrase, string $motif = ''): void
    {
        $this->exigeLeTitulaire($titulaire);
        $this->exigeLaPhrase($titulaire, $phrase);

        $transfert = $this->transfertEnAttente();

        if ($transfert === null) {
            throw new DomainException('Aucun transfert n’est en cours.');
        }

        $transfert->forceFill([
            'cancelled_at' => now(),
            'cancelled_reason' => $motif !== '' ? mb_substr($motif, 0, 255) : 'Annulé par le titulaire.',
        ])->save();
    }

    /**
     * APPLIQUER LES TRANSFERTS MÛRS — appelé par la passe planifiée.
     *
     * Le siège se déplace en UNE transaction : l'ancien titulaire redevient administrateur, le
     * nouveau prend le siège. Sans cela, l'index unique refuserait la seconde écriture et
     * laisserait la plateforme sans super-administrateur.
     *
     * @return int Le nombre de transferts appliqués.
     */
    public function appliquerLesTransfertsMurs(): int
    {
        $murs = PlatformSeatTransfer::query()
            ->enAttente()
            ->where('effective_at', '<=', now())
            ->with(['from', 'to'])
            ->get();

        $appliques = 0;

        foreach ($murs as $transfert) {
            // LA CIBLE A PU CHANGER ENTRE L'ARMEMENT ET L'ÉCHÉANCE : un compte suspendu ou
            // rétrogradé entre-temps ne doit pas hériter du passe-partout.
            $vers = $transfert->to;

            if ($vers === null || ! $vers->isAdmin() || ! ($vers->is_active ?? false)) {
                $transfert->forceFill([
                    'cancelled_at' => now(),
                    'cancelled_reason' => 'Le destinataire n’est plus un administrateur actif.',
                ])->save();

                continue;
            }

            $this->deplacerLeSiege($vers);

            $transfert->forceFill(['confirmed_at' => now()])->save();
            $appliques++;
        }

        return $appliques;
    }

    /** CHANGER LA PHRASE — l'ancienne est exigée, sinon une session volée la remplacerait. */
    public function changerLaPhrase(User $titulaire, string $ancienne, string $nouvelle): void
    {
        $this->exigeLeTitulaire($titulaire);
        $this->exigeLaPhrase($titulaire, $ancienne);
        $this->exigeUnePhraseSolide($nouvelle);

        $titulaire->forceFill(['seat_secret_hash' => Hash::make($nouvelle)])->save();
    }

    /** LE DÉPLACEMENT LUI-MÊME : une transaction, deux écritures, jamais deux sièges. */
    private function deplacerLeSiege(User $vers): void
    {
        PorteDuSiege::ouvrir(function () use ($vers) {
            DB::transaction(function () use ($vers) {
                $ancien = $this->titulaire();

                if ($ancien !== null) {
                    $ancien->forceFill(['platform_role' => User::PLATFORM_ADMIN])->save();
                }

                $vers->forceFill([
                    'platform_role' => User::PLATFORM_SUPER_ADMIN,
                    'seat_claimed_at' => now(),
                ])->save();
            });
        });
    }

    /** @throws DomainException */
    private function exigeLeTitulaire(User $utilisateur): void
    {
        $titulaire = $this->titulaire();

        if ($titulaire === null || $titulaire->id !== $utilisateur->id) {
            throw new DomainException('Seul le titulaire du siège peut le déplacer.');
        }
    }

    /**
     * LA PHRASE, ET SEULEMENT ELLE.
     *
     * Elle est limitée en fréquence par compte : une phrase se devine en la répétant, pas en la
     * cassant. Un titulaire sans phrase enregistrée ne peut rien déplacer — il doit d'abord en
     * poser une en ligne de commande.
     *
     * @throws DomainException
     */
    private function exigeLaPhrase(User $titulaire, string $phrase): void
    {
        $cle = 'siege:phrase:'.$titulaire->id;

        if (RateLimiter::tooManyAttempts($cle, self::ESSAIS)) {
            throw new DomainException(
                'Trop d’essais. Réessayez dans '.ceil(RateLimiter::availableIn($cle) / 60).' minutes.'
            );
        }

        $empreinte = (string) $titulaire->seat_secret_hash;

        if ($empreinte === '' || ! Hash::check($phrase, $empreinte)) {
            RateLimiter::hit($cle, self::FENETRE_SECONDES);

            throw new DomainException('Phrase du siège incorrecte.');
        }

        RateLimiter::clear($cle);
    }

    /**
     * LE SECOND FACTEUR, QUAND IL EXISTE.
     *
     * On ne l'invente pas : s'il est activé sur le compte, il est exigé. L'ignorer offrirait au
     * voleur de session le chemin que le titulaire a justement fermé.
     *
     * @throws DomainException
     */
    private function exigeLeSecondFacteur(User $titulaire, ?string $code): void
    {
        if (empty($titulaire->two_factor_secret)) {
            return;
        }

        if ($code === null || trim($code) === '') {
            throw new DomainException('Code d’authentification à deux facteurs requis.');
        }

        $valide = app(TwoFactorAuthenticationProvider::class)->verify(
            decrypt($titulaire->two_factor_secret),
            trim($code),
        );

        if (! $valide) {
            throw new DomainException('Code d’authentification invalide.');
        }
    }

    /** @throws DomainException */
    private function exigeUnePhraseSolide(string $phrase): void
    {
        // DOUZE CARACTÈRES : la phrase n'est pas saisie tous les jours, elle peut être longue.
        if (mb_strlen(trim($phrase)) < 12) {
            throw new DomainException('La phrase du siège fait au moins 12 caractères.');
        }
    }
}
