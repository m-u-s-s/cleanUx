<?php

namespace Tests\Feature\Api;

use App\Exceptions\ApiJsonRenderer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use PDOException;
use Tests\TestCase;

/** Le rendu JSON des erreurs d'API plantait lui-même sur les erreurs SQL. */
class ApiJsonRendererTest extends TestCase
{
    public function test_a_sql_error_renders_as_a_five_hundred_instead_of_crashing_the_renderer(): void
    {
        $response = ApiJsonRenderer::render($this->apiRequest(), $this->sqlStateException());

        $this->assertNotNull($response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('server_error', $response->getData(true)['error_code']);
    }

    /** En mode debug, le message d'origine doit être visible : c'est lui qui nomme la colonne manquante. */
    public function test_debug_mode_surfaces_the_underlying_message(): void
    {
        config(['app.debug' => true]);

        $payload = ApiJsonRenderer::render($this->apiRequest(), $this->sqlStateException())->getData(true);

        $this->assertSame(QueryException::class, $payload['debug']['exception']);
        $this->assertStringContainsString('self_registered_at', $payload['debug']['message']);
    }

    public function test_debug_details_never_leak_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $payload = ApiJsonRenderer::render($this->apiRequest(), $this->sqlStateException())->getData(true);

        $this->assertArrayNotHasKey('debug', $payload);
    }

    /** Les exceptions métier portent un vrai statut HTTP entier dans leur code : cette branche ne doit pas être perdue en réparant la précédente. */
    public function test_a_domain_exception_still_maps_to_its_own_status(): void
    {
        $response = ApiJsonRenderer::render(
            $this->apiRequest(),
            new \RuntimeException('Créneau déjà réservé.', 409),
        );

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('domain_error', $response->getData(true)['error_code']);
    }

    /** Reproduit fidèlement ce que renvoie le pilote MySQL : `getCode()` y vaut la CHAÎNE '42S22', vérifié sur la vraie base. */
    private function sqlStateException(): QueryException
    {
        $pdoException = new PDOException(
            "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'self_registered_at' in 'field list'"
        );

        $code = new \ReflectionProperty(\Exception::class, 'code');
        $code->setAccessible(true);
        $code->setValue($pdoException, '42S22');

        return new QueryException(
            'mysql',
            'update `provider_profiles` set `self_registered_at` = ?',
            [],
            $pdoException,
        );
    }

    private function apiRequest(): Request
    {
        return Request::create('/api/auth/register', 'POST');
    }
}
