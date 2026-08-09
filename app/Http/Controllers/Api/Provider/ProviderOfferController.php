<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\MissionAssignment;
use App\Services\Dispatch\OfferPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * L'OFFRE EN COURS — le repli qui fait tenir tout le reste.
 *
 * Le temps réel ouvre la modale, le push réveille l'application, mais ni l'un ni l'autre n'est
 * garanti : la socket tombe dans un ascenseur, le push se perd chez le fabricant du téléphone. Ce
 * point d'entrée est la SOURCE DE VÉRITÉ que l'application interroge à intervalle court — sans
 * lui, un défaut d'infrastructure rendrait la plateforme silencieuse et les prestataires
 * croiraient simplement qu'il n'y a pas de travail.
 *
 * IL REND LA MÊME CHARGE UTILE QUE LES DEUX AUTRES CANAUX (`OfferPayloadBuilder`). Trois formes
 * feraient afficher trois écrans différents selon le chemin emprunté par l'offre.
 *
 * UNE SEULE OFFRE, LA PLUS URGENTE. Le prestataire ne voit qu'une modale à la fois — c'est le
 * patron VTC, et deux comptes à rebours concurrents font accepter la mauvaise course. Après la
 * dernière vague, plusieurs offres peuvent coexister en base : on rend celle qui expire en premier.
 */
class ProviderOfferController extends Controller
{
    public function __construct(
        protected OfferPayloadBuilder $payloads,
    ) {}

    public function current(Request $request): JsonResponse
    {
        $assignment = MissionAssignment::query()
            ->where('user_id', $request->user()->id)
            ->where('assignment_status', 'assigned')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with([
                'mission',
                'mission.bookingViaBookingId.serviceCatalog',
                'mission.bookingViaBookingId.trade',
                'mission.bookingViaBookingId.customer',
                'mission.rendezVous.serviceCatalog',
                'mission.rendezVous.trade',
                'mission.rendezVous.customer',
            ])
            ->orderBy('expires_at')
            ->first();

        return response()->json([
            'ok' => true,
            'data' => $assignment ? $this->payloads->build($assignment) : null,
        ]);
    }
}
