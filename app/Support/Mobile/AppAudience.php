<?php

namespace App\Support\Mobile;

use App\Models\User;
use Illuminate\Http\Request;

/** Quelle application mobile parle, et a-t-elle le droit de servir ce compte ? */
class AppAudience
{
    public const HEADER = 'X-Brio-App';

    public const CLIENT = 'client';

    public const PROVIDER = 'provider';

    /** L'application déclarée, ou `null` si elle ne se déclare pas. */
    public static function declared(Request $request): ?string
    {
        $app = (string) $request->header(self::HEADER, '');

        return in_array($app, [self::CLIENT, self::PROVIDER], true) ? $app : null;
    }

    /** Ce compte a-t-il sa place dans cette application ? Trois règles, dans cet ordre : 1. */
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

    /** L'application vers laquelle renvoyer ce compte. Le refus doit dire QUOI FAIRE. */
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
