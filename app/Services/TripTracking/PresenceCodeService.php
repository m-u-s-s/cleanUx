<?php

namespace App\Services\TripTracking;

use App\Models\TripTrackingSession;
use App\Models\User;
use App\Services\Missions\MissionVerificationCodeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Confirmation de présence du prestataire, par code affiché chez le client.
 *
 * La géo-barrière atteste d'une proximité, pas d'une présence : un téléphone à 100 m de la porte
 * la franchit. Le client affiche donc un code à usage unique, que le prestataire scanne sur place.
 * Confirmer exige ainsi les deux appareils au même endroit — ce qu'aucune coordonnée GPS ne peut
 * établir seule.
 *
 * Les garde-fous sont ceux de {@see MissionVerificationCodeService}, dont
 * ce mécanisme est le pendant côté suivi : empreinte plutôt que clair, péremption courte, et
 * plafond de tentatives — six chiffres se devinent en un million d'essais, ce qui n'est rien pour
 * une machine.
 */
class PresenceCodeService
{
    /** Durée de vie d'un code, en minutes. Assez pour scanner, trop peu pour transmettre. */
    public const TTL_MINUTES = 10;

    /** Au-delà, le code est brûlé et il faut en demander un neuf. */
    public const MAX_ATTEMPTS = 5;

    /**
     * Délivre le code courant du client, ou en forge un neuf s'il n'y en a pas d'exploitable.
     *
     * Idempotent tant que le code vaut : rouvrir l'écran ne doit pas invalider le code que le
     * prestataire est en train de scanner.
     *
     * @return array{code: string, expires_at: Carbon}
     */
    public function issueFor(TripTrackingSession $session): array
    {
        if ($session->presence_confirmed_at !== null) {
            throw ValidationException::withMessages([
                'presence' => ['La présence est déjà confirmée pour cette intervention.'],
            ]);
        }

        // Un code en clair n'est jamais relisible : le renouveler est la seule option quand
        // l'empreinte existante n'est plus exploitable.
        $plain = $this->generatePlainCode();

        $session->update([
            'presence_code_hash' => Hash::make($plain),
            'presence_code_expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'presence_code_attempts' => 0,
        ]);

        return [
            'code' => $plain,
            'expires_at' => $session->fresh()->presence_code_expires_at,
        ];
    }

    /**
     * Valide le code scanné par le prestataire et grave la confirmation.
     *
     * @throws ValidationException
     */
    public function confirm(TripTrackingSession $session, string $plainCode, User $provider): TripTrackingSession
    {
        /**
         * Le refus est RENVOYÉ, jamais levé depuis la transaction : une exception la ferait
         * annuler, et avec elle l'incrément du compteur. Le plafond anti-force-brute ne
         * compterait alors jamais — un essai raté ne laisserait aucune trace.
         *
         * @var array<string, list<string>>|null $failure
         */
        $failure = null;

        $confirmed = DB::transaction(function () use ($session, $plainCode, $provider, &$failure) {
            /** @var TripTrackingSession $locked */
            $locked = TripTrackingSession::query()->lockForUpdate()->findOrFail($session->id);

            // Idempotence : un double scan ne doit pas être traité comme un échec.
            if ($locked->presence_confirmed_at !== null) {
                return $locked;
            }

            if ($locked->presence_code_hash === null) {
                $failure = ['code' => ["Aucun code en cours. Demandez au client d'afficher le sien."]];

                return null;
            }

            $locked->increment('presence_code_attempts');
            $locked->refresh();

            // Le plafond est vérifié AVANT l'empreinte : une bonne réponse tardive ne doit pas
            // racheter une série d'essais.
            if ($locked->presence_code_attempts > self::MAX_ATTEMPTS) {
                $locked->update(['presence_code_hash' => null]);
                $failure = ['code' => ['Trop de tentatives. Demandez au client un nouveau code.']];

                return null;
            }

            if ($locked->presence_code_expires_at !== null && $locked->presence_code_expires_at->isPast()) {
                $failure = ['code' => ['Ce code a expiré. Demandez au client de le rafraîchir.']];

                return null;
            }

            if (! Hash::check(trim($plainCode), $locked->presence_code_hash)) {
                $failure = ['code' => ['Code invalide.']];

                return null;
            }

            $locked->update([
                'presence_confirmed_at' => now(),
                'presence_confirmed_by_user_id' => $provider->id,
                // Le code a servi : il ne doit plus pouvoir l'être.
                'presence_code_hash' => null,
                'presence_code_expires_at' => null,
            ]);

            return $locked->fresh();
        });

        if ($failure !== null) {
            throw ValidationException::withMessages($failure);
        }

        return $confirmed;
    }

    protected function generatePlainCode(): string
    {
        return (string) random_int(100000, 999999);
    }
}
