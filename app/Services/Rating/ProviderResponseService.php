<?php

namespace App\Services\Rating;

use App\Events\Rating\RatingResponded;
use App\Models\Feedback;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Validation\ValidationException;

class ProviderResponseService
{
    public const MAX_LENGTH = 1000;

    public function reply(Feedback $feedback, User $provider, string $response): Feedback
    {
        if (! $feedback->isClientToProvider()) {
            throw ValidationException::withMessages([
                'response' => "Vous ne pouvez répondre qu'aux avis qui vous sont adressés.",
            ]);
        }

        if ((int) $feedback->employe_id !== (int) $provider->id) {
            throw ValidationException::withMessages([
                'response' => "Cet avis n'est pas adressé à votre compte.",
            ]);
        }

        $response = trim($response);
        if ($response === '') {
            throw ValidationException::withMessages([
                'response' => 'La réponse ne peut pas être vide.',
            ]);
        }
        if (strlen($response) > self::MAX_LENGTH) {
            throw ValidationException::withMessages([
                'response' => 'La réponse est trop longue (max ' . self::MAX_LENGTH . ' caractères).',
            ]);
        }

        $feedback->update([
            'provider_response' => $response,
            'provider_responded_at' => now(),
        ]);

        ActivityLogger::log('rating.responded', $feedback, [
            'provider_user_id' => $provider->id,
        ]);

        RatingResponded::dispatch($feedback);

        return $feedback->fresh();
    }
}
