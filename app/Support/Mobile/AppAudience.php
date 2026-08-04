<?php

namespace App\Support\Mobile;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Quelle application mobile parle, et a-t-elle le droit de servir ce compte ?
 *
 * Les deux APK — `com.cleanux.client` et `com.cleanux.provider` — partagent le même point de
 * connexion et les mêmes jetons Sanctum. Un prestataire pouvait donc se connecter à l'application
 * cliente : il obtenait un jeton parfaitement valide, puis chaque écran appelait des routes que son
 * rôle refuse. Une application qui s'ouvre et ne fonctionne nulle part, sans qu'aucun message
 * n'explique pourquoi.
 *
 * CE QUE CE GARDE-FOU EST, ET CE QU'IL N'EST PAS. L'application se DÉCLARE dans un en-tête, et une
 * déclaration se falsifie. Ce n'est donc pas une frontière de privilèges : celle-ci reste tenue par
 * les gardes de rôle sur chaque route, et un jeton de prestataire n'ouvre aucune route cliente,
 * en-tête ou pas. C'est une frontière de PRODUIT — elle évite qu'on entre dans une application qui
 * ne saura rien faire de vous, et le dit en une phrase au lieu de laisser dix écrans échouer.
 */
class AppAudience
{
    public const HEADER = 'X-CleanUx-App';

    public const CLIENT = 'client';

    public const PROVIDER = 'provider';

    /**
     * L'application déclarée, ou `null` si elle ne se déclare pas.
     *
     * Une valeur inconnue vaut absence : la porte reste ouverte plutôt que de se fermer sur une
     * faute de frappe dans une version future d'un des deux APK.
     */
    public static function declared(Request $request): ?string
    {
        $app = (string) $request->header(self::HEADER, '');

        return in_array($app, [self::CLIENT, self::PROVIDER], true) ? $app : null;
    }

    /**
     * Ce compte a-t-il sa place dans cette application ?
     *
     * Trois règles, dans cet ordre :
     *
     * 1. Application non déclarée → OUI. Les installations déjà en service ne connaissent pas
     *    l'en-tête ; refuser en son absence déconnecterait tout le parc jusqu'à la mise à jour,
     *    pour un garde-fou de confort.
     *
     * 2. Administrateur → OUI, partout. Il n'est ni client ni prestataire : la règle appliquée
     *    telle quelle l'enfermerait DEHORS des deux applications, alors que le registre de parité
     *    lui sert déjà des modules sur mobile.
     *
     * 3. Sinon, le compte doit porter la casquette de l'application. Une DOUBLE casquette entre
     *    des deux côtés — un prestataire qui commande aussi chez lui existe, et le refuser
     *    l'obligerait à tenir un second compte, avec un second historique et une seconde
     *    facturation.
     */
    public static function allows(User $user, ?string $app): bool
    {
        if ($app === null) {
            return true;
        }

        if ($user->isPlatformAdmin()) {
            return true;
        }

        return match ($app) {
            self::PROVIDER => $user->isProvider(),
            self::CLIENT => $user->isClient(),
            default => true,
        };
    }

    /**
     * L'application vers laquelle renvoyer ce compte.
     *
     * Le refus doit dire QUOI FAIRE. « Accès refusé » sur l'écran de connexion d'une application
     * qu'on vient de télécharger ne se comprend pas : on retente, on doute de son mot de passe,
     * puis on appelle le support.
     */
    public static function expectedFor(User $user): ?string
    {
        if ($user->isProvider()) {
            return self::PROVIDER;
        }

        return $user->isClient() ? self::CLIENT : null;
    }

    /** @return array{message: string, app: string|null} */
    public static function refusal(User $user, string $app): array
    {
        $expected = self::expectedFor($user);

        return [
            'message' => match ($expected) {
                self::PROVIDER => 'Ce compte est un compte professionnel. Connectez-vous depuis l’application brio Pro.',
                self::CLIENT => 'Ce compte est un compte client. Connectez-vous depuis l’application brio.',
                default => 'Ce compte ne peut pas être utilisé depuis cette application.',
            },
            'app' => $expected,
        ];
    }
}
