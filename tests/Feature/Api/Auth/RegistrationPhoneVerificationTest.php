<?php

namespace Tests\Feature\Api\Auth;

use App\Models\PhoneVerificationCode;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms\PhoneVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Vérification du téléphone AVANT la création du compte.
 *
 * Le parcours prestataire demande le numéro au premier écran et le vérifie par SMS, comme Uber et
 * Heetch : c'est l'identifiant opérationnel du prestataire. Or le module OTP existant exigeait un
 * `User` — sa table portait `user_id` NOT NULL avec clé étrangère — et ne savait donc vérifier que
 * le téléphone d'un compte déjà créé.
 *
 * La pièce sensible est le jeton rendu après vérification : c'est lui, et non un booléen envoyé
 * par le client, qui autorise `phone_verified_at`. Les tests ci-dessous décrivent surtout ce
 * qu'il ne doit PAS permettre.
 */
class RegistrationPhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+32471234567';

    private const REQUEST_ROUTE = '/api/auth/phone/verify-request';

    private const CONFIRM_ROUTE = '/api/auth/phone/verify-confirm';

    public function test_anyone_can_request_a_code_without_being_logged_in(): void
    {
        $this->postJson(self::REQUEST_ROUTE, ['phone' => self::PHONE])
            ->assertCreated()
            ->assertJsonPath('phone', self::PHONE);

        $this->assertDatabaseHas('phone_verification_codes', [
            'phone' => self::PHONE,
            'user_id' => null,
            'purpose' => PhoneVerificationService::PURPOSE_REGISTRATION,
        ]);
    }

    public function test_a_malformed_number_is_rejected_before_any_sms_is_sent(): void
    {
        $this->postJson(self::REQUEST_ROUTE, ['phone' => '0471234567'])
            ->assertStatus(422);

        $this->assertSame(0, SmsMessage::count());
    }

    public function test_confirming_the_right_code_returns_a_token(): void
    {
        $code = $this->issueCode();

        $this->postJson(self::CONFIRM_ROUTE, ['phone' => self::PHONE, 'code' => $code])
            ->assertOk()
            ->assertJsonStructure(['phone_verification_token']);
    }

    public function test_a_wrong_code_is_rejected_and_counts_as_an_attempt(): void
    {
        $this->issueCode();

        $this->postJson(self::CONFIRM_ROUTE, ['phone' => self::PHONE, 'code' => '000000'])
            ->assertStatus(422);

        $this->assertSame(1, PhoneVerificationCode::first()->attempts);
    }

    public function test_the_token_marks_the_new_account_as_verified(): void
    {
        $token = $this->verifiedToken();

        $this->postJson('/api/auth/register', $this->payload([
            'phone' => self::PHONE,
            'phone_verification_token' => $token,
        ]))->assertCreated();

        $user = User::firstOrFail();
        $this->assertSame(self::PHONE, $user->phone);
        $this->assertNotNull($user->phone_verified_at);
    }

    /**
     * Le point de sécurité de tout ce lot : un numéro vérifié une fois ne doit pas ouvrir un
     * nombre illimité de comptes vérifiés. C'est ce que `consumed_at` garantit.
     */
    public function test_a_token_cannot_be_used_twice(): void
    {
        $token = $this->verifiedToken();

        $this->postJson('/api/auth/register', $this->payload([
            'email' => 'premier@test.be',
            'phone' => self::PHONE,
            'phone_verification_token' => $token,
        ]))->assertCreated();

        $this->postJson('/api/auth/register', $this->payload([
            'email' => 'second@test.be',
            'phone' => self::PHONE,
            'phone_verification_token' => $token,
        ]))->assertCreated();

        $second = User::where('email', 'second@test.be')->firstOrFail();
        $this->assertNull(
            $second->phone_verified_at,
            'le second compte ne doit pas hériter de la vérification du premier'
        );
    }

    /** Un jeton obtenu pour un numéro ne doit pas en valider un autre. */
    public function test_a_token_does_not_verify_a_different_number(): void
    {
        $token = $this->verifiedToken();

        $this->postJson('/api/auth/register', $this->payload([
            'phone' => '+32499999999',
            'phone_verification_token' => $token,
        ]))->assertCreated();

        $this->assertNull(User::firstOrFail()->phone_verified_at);
    }

    /** Un jeton forgé ne doit ni valider le téléphone, ni faire échouer l'inscription. */
    public function test_a_forged_token_is_ignored(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'phone' => self::PHONE,
            'phone_verification_token' => 'nimportequoi',
        ]))->assertCreated();

        $this->assertNull(User::firstOrFail()->phone_verified_at);
    }

    /** Sans jeton, le comportement d'origine est inchangé : téléphone stocké, non vérifié. */
    public function test_registering_without_a_token_still_works(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['phone' => self::PHONE]))
            ->assertCreated();

        $user = User::firstOrFail();
        $this->assertSame(self::PHONE, $user->phone);
        $this->assertNull($user->phone_verified_at);
    }

    /**
     * Demande un code et le relit dans le SMS émis — seul endroit où il circule en clair, la
     * table n'en gardant que le hash. Le déduire du hash demanderait un million de vérifications
     * bcrypt ; le reconstruire à la main ferait de ce test un décor qui resterait vert si
     * l'implémentation changeait.
     */
    private function issueCode(): string
    {
        $this->postJson(self::REQUEST_ROUTE, ['phone' => self::PHONE])->assertCreated();

        $body = SmsMessage::latest('id')->firstOrFail()->body;

        $this->assertSame(1, preg_match('/\b(\d{6})\b/', $body, $m), "code absent du SMS : {$body}");
        $this->assertTrue(
            Hash::check($m[1], PhoneVerificationCode::latest('id')->firstOrFail()->code_hash),
            'le code lu dans le SMS doit être celui enregistré'
        );

        return $m[1];
    }

    /** Jeton obtenu par le vrai parcours HTTP, format compris. */
    private function verifiedToken(): string
    {
        $code = $this->issueCode();

        return $this->postJson(self::CONFIRM_ROUTE, ['phone' => self::PHONE, 'code' => $code])
            ->assertOk()
            ->json('phone_verification_token');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nouveau Prestataire',
            'email' => 'nouveau@prestataire.test',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'accept_terms' => true,
        ], $overrides);
    }
}
