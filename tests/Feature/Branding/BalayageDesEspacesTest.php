<?php

namespace Tests\Feature\Branding;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Support\Brand\BrandMark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LE BALAYAGE — chaque page de chaque espace, une par une.
 *
 * Les tests voisins prouvent que les quatre GABARITS servent la bonne marque. Ce fichier répond à
 * l'autre moitié de la question, la seule qui compte pour un utilisateur : **est-ce qu'une page
 * échappe à son gabarit ?** Une vue qui construirait sa propre coquille, un écran embarqué, une
 * page héritée d'avant la refonte — rien de tout cela n'apparaît dans un relevé de gabarits, et
 * c'est exactement ainsi que quatre identités concurrentes ont pu coexister pendant des mois.
 *
 * LA LISTE VIENT DU RÉPERTOIRE DE MODULES, pas d'un tableau écrit à la main : `config/modules.php`
 * est ce qui peuple les barres de navigation, donc ce qu'un utilisateur peut effectivement
 * atteindre. Un module ajouté demain entre dans ce balayage sans qu'on y touche.
 *
 * CE QUI EST TOLÉRÉ, ET POURQUOI. Une page qui ne rend pas 200 est IGNORÉE : beaucoup d'écrans
 * exigent des données que ce test n'a pas à fabriquer, et les faire échouer ici transformerait un
 * garde-fou de marque en test d'intégration fragile. Le compte de pages réellement inspectées est
 * affirmé — sans cela, un jour où tout redirigerait, ce test passerait en n'ayant rien regardé.
 */
class BalayageDesEspacesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: string} */
    private function acteur(string $contexte): array
    {
        return match ($contexte) {
            'client' => [User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]), BrandMark::CLIENT],
            'employe' => [$this->prestataire(), BrandMark::PROVIDER],
            'admin' => [User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]), BrandMark::PROVIDER],
            'client-company' => [$this->membreDeSociete(OrganizationType::CLIENT_COMPANY, User::ROLE_ENTREPRISE), BrandMark::CLIENT],
            'provider-company' => [$this->membreDeSociete(OrganizationType::PROVIDER_COMPANY, User::ROLE_EMPLOYE), BrandMark::PROVIDER],
        };
    }

    private function prestataire(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_EMPLOYE, 'is_active' => true]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $user->fresh();
    }

    private function membreDeSociete(OrganizationType $type, string $role): User
    {
        $org = OrganizationAccount::factory()->create(['type' => $type->value, 'status' => 'active']);

        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        if ($type === OrganizationType::PROVIDER_COMPANY) {
            ProviderProfile::create([
                'user_id' => $user->id,
                'organization_account_id' => $org->id,
                'provider_type' => ProviderType::COMPANY_WORKER->value,
                'status' => 'active',
                'verification_status' => 'verified',
            ]);
        }

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    /** @return list<string> les noms de route atteignables d'un contexte */
    private function routesDe(string $contexte): array
    {
        return collect(config('modules.catalogue', []))
            ->where('context', $contexte)
            ->pluck('route')
            ->filter(fn ($nom) => is_string($nom) && Route::has($nom))
            ->unique()
            ->values()
            ->all();
    }

    #[Test]
    public function chaque_espace_sert_sa_marque_sur_toutes_ses_pages(): void
    {
        $fautives = [];
        $inspectees = 0;

        foreach (['client', 'employe', 'admin', 'client-company', 'provider-company'] as $contexte) {
            [$utilisateur, $espaceAttendu] = $this->acteur($contexte);
            $autreEspace = $espaceAttendu === BrandMark::CLIENT ? BrandMark::PROVIDER : BrandMark::CLIENT;

            foreach ($this->routesDe($contexte) as $nomDeRoute) {
                try {
                    $reponse = $this->actingAs($utilisateur)->get(route($nomDeRoute));
                } catch (\Throwable $e) {
                    // La page exige des données qu'on ne fabrique pas ici : hors sujet.
                    continue;
                }

                if ($reponse->status() !== 200) {
                    continue;
                }

                /*
                 * SEULES LES PAGES HTML SONT JUGÉES. Le répertoire de modules contient au moins une
                 * entrée qui pointe vers un point d'entrée JSON (`presence.me`) : lui reprocher de
                 * ne pas porter de logo n'aurait aucun sens. Le vrai défaut est ailleurs — un lien
                 * de navigation qui rend du JSON brut à qui clique dessus — et il est signalé à
                 * part, pas corrigé sous couvert de marque.
                 */
                if (! str_contains((string) $reponse->headers->get('content-type'), 'text/html')) {
                    continue;
                }

                $html = $reponse->getContent();
                $inspectees++;

                if (! str_contains($html, "/images/brand/brio-{$espaceAttendu}-light")) {
                    $fautives[] = "{$contexte}:{$nomDeRoute} — marque absente";

                    continue;
                }

                if (str_contains($html, "/images/brand/brio-{$autreEspace}-")) {
                    $fautives[] = "{$contexte}:{$nomDeRoute} — porte la marque de l'autre espace";
                }

                foreach (['cx-logo-mark', '6875F5'] as $ancienneTrace) {
                    if (str_contains($html, $ancienneTrace)) {
                        $fautives[] = "{$contexte}:{$nomDeRoute} — reste de l'ancienne identité ({$ancienneTrace})";
                    }
                }
            }
        }

        /*
         * LE PLANCHER EST HAUT EXPRÈS. Sans lui, un jour où l'authentification redirigerait tout,
         * ce test passerait en n'ayant regardé aucune page — vert pour la pire des raisons. Le
         * balayage en inspectait 155 le jour où il a été écrit ; ce seuil laisse de la marge aux
         * écrans qui deviendront exigeants en données, et se plaint dès qu'un tiers du parc
         * disparaît d'un coup.
         */
        $this->assertGreaterThan(
            120,
            $inspectees,
            "Seules {$inspectees} pages ont pu être rendues : le balayage ne prouve plus rien.",
        );

        $this->assertSame(
            [],
            $fautives,
            "Pages sans la bonne marque ({$inspectees} inspectées) :\n  ".implode("\n  ", $fautives),
        );
    }
}
