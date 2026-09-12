<?php

namespace Tests\Feature\I18n;

use App\Models\User;
use App\Services\I18n\LocaleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * L'API accepte toutes les langues que la plateforme active, et rien de plus.
 *
 * Cinq points d'entrée écrivaient `in:fr,nl,en` à la main pendant que `config/i18n.php` en
 * activait six : un client mobile qui choisissait l'espagnol recevait un 422, alors que le site
 * le servait déjà. Le défaut ne se voyait nulle part — la langue restait simplement au français.
 */
class LApiAccepteLesLanguesActivesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un fournisseur de donnees s'execute AVANT que l'application soit montee : `app()` y leve
     * « A facade root has not been set ». La liste est donc ecrite ici, et
     * `test_les_six_langues_du_site_sont_actives` verifie qu'elle n'a pas derive de la
     * configuration.
     *
     * @return list<array{string}>
     */
    public static function languesActives(): array
    {
        return [['fr'], ['nl'], ['en'], ['es'], ['it'], ['de']];
    }

    #[DataProvider('languesActives')]
    public function test_un_client_peut_choisir_toute_langue_active(string $langue): void
    {
        $client = User::factory()->client()->create(['locale' => 'fr']);

        $this->actingAs($client, 'sanctum')
            ->putJson('/api/client/profile', ['locale' => $langue])
            ->assertOk();

        $this->assertSame($langue, $client->fresh()->locale);
    }

    #[DataProvider('languesActives')]
    public function test_un_prestataire_peut_choisir_toute_langue_active(string $langue): void
    {
        $prestataire = User::factory()->employe()->create(['locale' => 'fr']);

        $this->actingAs($prestataire, 'sanctum')
            ->putJson('/api/provider/profile', ['locale' => $langue])
            ->assertOk();

        $this->assertSame($langue, $prestataire->fresh()->locale);
    }

    /**
     * LE TÉMOIN DU REFUS. Sans lui, les cas ci-dessus passeraient au vert avec une règle qui
     * accepte n'importe quoi — y compris une langue qu'aucun catalogue ne sert.
     */
    public function test_une_langue_inconnue_est_refusee(): void
    {
        $client = User::factory()->client()->create(['locale' => 'fr']);

        $this->actingAs($client, 'sanctum')
            ->putJson('/api/client/profile', ['locale' => 'zz'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('locale');

        $this->assertSame('fr', $client->fresh()->locale);
    }

    /** La liste servie au mobile est celle de la configuration, pas une copie. */
    public function test_les_six_langues_du_site_sont_actives(): void
    {
        $this->assertSame(
            ['fr', 'nl', 'en', 'es', 'it', 'de'],
            app(LocaleResolver::class)->supportedCodes(),
        );
    }
}
