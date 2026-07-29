<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Rules\ValidBusinessNumber;
use App\Services\KybV2\CompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Auth — Recherche d'entreprise
 *
 * Le prestataire tape son numéro d'entreprise pendant l'inscription et voit sa raison sociale
 * remonter du registre officiel : il confirme d'un geste au lieu de recopier. C'est ce qui
 * distingue une inscription de trente secondes d'une inscription de trois minutes.
 *
 * Cette route est nécessairement publique — elle sert au milieu de l'inscription, avant que le
 * compte existe. Deux garde-fous, parce qu'elle relaie des appels vers un registre tiers :
 *
 *  - la clé de contrôle du numéro est vérifiée AVANT tout appel sortant, ce qui élimine
 *    l'énumération à l'aveugle : deviner un numéro valide demande déjà de connaître le modulo ;
 *  - `throttle:otp` (5/min par IP) limite ce qu'un même client peut extraire.
 *
 * Les données rendues sont publiques par nature — les registres BCE et SIRENE sont ouverts et
 * consultables en ligne. Cette route ne divulgue donc rien qui ne le soit déjà.
 */
class CompanyLookupController extends Controller
{
    public function __construct(protected CompanyLookup $lookup) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:32', new ValidBusinessNumber],
        ]);

        $company = $this->lookup->find($data['number']);

        // 200 avec `found: false` plutôt qu'un 404 : ne pas trouver une entreprise est un
        // résultat normal de la recherche, pas une erreur de la requête. L'inscription se
        // poursuit en saisie manuelle.
        return response()->json([
            'ok' => true,
            'found' => $company !== null,
            'company' => $company,
        ]);
    }
}
