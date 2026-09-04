<?php

namespace App\Services\Platform;

use App\Models\PlatformBankAccount;
use App\Models\PlatformVaultAccess;
use App\Models\User;
use App\Support\ActivityLogger;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * LE COFFRE — la porte unique vers le compte qui reçoit les commissions.
 *
 * CE QU'ON PROTÈGE N'EST PAS L'IBAN, C'EST LE CHANGEMENT D'IBAN. Un IBAN figure sur chaque
 * facture émise ; la ligne qui dit « voici où va l'argent de la plateforme », elle, détourne tous
 * les encaissements à venir si on la remplace.
 *
 * QUATRE VERROUS :
 *   1. LE SIÈGE. Pas une capacité qu'on accorde — la propriété de la plateforme.
 *   2. UN CODE, distinct du mot de passe de connexion ET de la phrase du siège : compromettre
 *      l'un des trois n'ouvre pas les deux autres. Le super-administrateur le repose à chaque
 *      changement, et il a le droit de reposer le même.
 *   3. CINQ ESSAIS PAR QUART D'HEURE. Un code se devine en le répétant, pas en le cassant.
 *   4. TOUT EST TRACÉ, y compris les refus — une série de codes faux est le premier signe qu'on
 *      essaie d'entrer, et le seul moment où l'on peut encore réagir.
 *
 * ON NE MODIFIE JAMAIS UNE LIGNE : on en ajoute une, l'ancienne se ferme. Un détournement qui
 * pourrait réécrire en place effacerait sa propre trace.
 */
class CoffreBancaire
{
    private const ESSAIS = 5;

    private const FENETRE_SECONDES = 900;

    private const LONGUEUR_MINIMALE = 8;

    public function compteActif(): ?PlatformBankAccount
    {
        return PlatformBankAccount::query()->actif()->latest('id')->first();
    }

    public function unCodeExiste(User $titulaire): bool
    {
        return ! empty($titulaire->vault_code_hash);
    }

    /**
     * OUVRIR — la lecture exige le code, comme l'écriture.
     *
     * @throws DomainException
     */
    public function ouvrir(User $titulaire, string $code): ?PlatformBankAccount
    {
        $this->exigeLeTitulaire($titulaire);
        $this->exigeLeCode($titulaire, $code);

        $compte = $this->compteActif();

        $this->tracer(PlatformVaultAccess::OUVERT, $titulaire, $compte);

        return $compte;
    }

    /**
     * CHANGER LE COMPTE — et reposer le code au passage.
     *
     * Le code neuf peut être le même que l'ancien : c'est une décision du titulaire, pas une
     * contrainte de la plateforme. Ce qui compte est qu'il le SAISISSE, chaque fois — un
     * changement de destination bancaire ne doit jamais se faire d'un seul geste distrait.
     *
     * @param  array<string, string|null>  $valeurs
     *
     * @throws DomainException
     */
    public function remplacerLeCompte(
        User $titulaire,
        array $valeurs,
        string $codeActuel,
        string $codeNeuf,
    ): PlatformBankAccount {
        $this->exigeLeTitulaire($titulaire);

        // LE PREMIER COMPTE N'A PAS DE CODE À DEMANDER : le coffre est vide, et l'accès au siège
        // est déjà la preuve. Dès le second, l'ancien code est exigé.
        if ($this->unCodeExiste($titulaire)) {
            $this->exigeLeCode($titulaire, $codeActuel);
        }

        $this->exigeUnCodeSolide($codeNeuf);

        $iban = $this->ibanValide($valeurs['iban'] ?? '');
        $titulaireDuCompte = trim((string) ($valeurs['holder_name'] ?? ''));

        if ($titulaireDuCompte === '') {
            throw new DomainException('Le nom du titulaire du compte est obligatoire.');
        }

        return DB::transaction(function () use ($titulaire, $valeurs, $iban, $titulaireDuCompte, $codeNeuf): PlatformBankAccount {
            // L'ANCIENNE LIGNE SE FERME AVANT que la neuve s'ouvre : l'index unique n'accepte
            // qu'un seul compte actif, et deux destinations pour le même argent n'ont aucun sens.
            PlatformBankAccount::query()->actif()->update([
                'is_active' => false,
                'closed_at' => now(),
            ]);

            $compte = PlatformBankAccount::create([
                'holder_name' => $titulaireDuCompte,
                'iban' => $iban,
                'bic' => $valeurs['bic'] ?? null,
                'bank_name' => $valeurs['bank_name'] ?? null,
                'iban_last4' => PlatformBankAccount::quatreDerniers($iban),
                'country_code' => strtoupper((string) ($valeurs['country_code'] ?? 'BE')),
                'currency' => strtoupper((string) ($valeurs['currency'] ?? 'EUR')),
                'note' => $valeurs['note'] ?? null,
                'is_active' => true,
                'created_by' => $titulaire->id,
                'created_ip' => request()->ip(),
            ]);

            $titulaire->forceFill(['vault_code_hash' => Hash::make($codeNeuf)])->save();

            $this->tracer(PlatformVaultAccess::MODIFIE, $titulaire, $compte);

            return $compte;
        });
    }

    /**
     * LES DERNIÈRES OUVERTURES — c'est ce qu'on regarde quand on doute.
     *
     * @return Collection<int, PlatformVaultAccess>
     */
    public function dernieresOuvertures(int $combien = 20): Collection
    {
        return PlatformVaultAccess::query()
            ->with('acteur:id,name,email')
            ->latest('id')
            ->limit($combien)
            ->get();
    }

    /** @throws DomainException */
    private function exigeLeTitulaire(User $utilisateur): void
    {
        if (! $utilisateur->isSuperAdmin()) {
            throw new DomainException('Seul le titulaire du siège ouvre le coffre.');
        }
    }

    /** @throws DomainException */
    private function exigeLeCode(User $titulaire, string $code): void
    {
        $cle = 'coffre:code:'.$titulaire->id;

        if (RateLimiter::tooManyAttempts($cle, self::ESSAIS)) {
            $this->tracer(PlatformVaultAccess::REFUSE, $titulaire, null);

            throw new DomainException(
                'Trop d’essais. Réessayez dans '.ceil(RateLimiter::availableIn($cle) / 60).' minutes.'
            );
        }

        $empreinte = (string) $titulaire->vault_code_hash;

        if ($empreinte === '' || ! Hash::check($code, $empreinte)) {
            RateLimiter::hit($cle, self::FENETRE_SECONDES);
            $this->tracer(PlatformVaultAccess::REFUSE, $titulaire, null);

            throw new DomainException('Code du coffre incorrect.');
        }

        RateLimiter::clear($cle);
    }

    /** @throws DomainException */
    private function exigeUnCodeSolide(string $code): void
    {
        if (mb_strlen(trim($code)) < self::LONGUEUR_MINIMALE) {
            throw new DomainException('Le code du coffre fait au moins '.self::LONGUEUR_MINIMALE.' caractères.');
        }
    }

    /**
     * UN IBAN QUI N'EN EST PAS UN NE SE DÉCOUVRE PAS AU PREMIER VIREMENT.
     *
     * On ne valide pas la clé de contrôle ici — les formats varient trop d'un pays à l'autre —
     * mais un champ vide ou manifestement trop court est refusé tout de suite.
     *
     * @throws DomainException
     */
    private function ibanValide(string $iban): string
    {
        $propre = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $iban) ?? '');

        if (mb_strlen($propre) < 14 || ! preg_match('/^[A-Z]{2}[0-9A-Z]+$/', $propre)) {
            throw new DomainException('Cet IBAN n’a pas une forme valide.');
        }

        return $propre;
    }

    private function tracer(string $action, User $acteur, ?PlatformBankAccount $compte): void
    {
        PlatformVaultAccess::create([
            'action' => $action,
            'actor_id' => $acteur->id,
            'actor_ip' => request()->ip(),
            'actor_user_agent' => mb_substr((string) request()->userAgent(), 0, 255) ?: null,
            'platform_bank_account_id' => $compte?->id,
        ]);

        ActivityLogger::critical('finance.coffre_'.$action, $acteur, [
            'domain' => 'finance',
            'compte' => $compte?->masque(),
        ]);
    }
}
