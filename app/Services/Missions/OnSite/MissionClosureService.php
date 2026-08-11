<?php

namespace App\Services\Missions\OnSite;

use App\Models\ContractDocument;
use App\Models\ContractTemplate;
use App\Models\Mission;
use App\Models\MissionExtra;
use App\Models\MissionReport;
use App\Models\User;
use App\Notifications\MissionReportReadyNotification;
use App\Services\ContractsV2\ContractService;
use App\Services\Missions\MissionQualityService;
use App\Services\Missions\MissionReportService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * LE RAPPORT DE FIN (F9) ET LA SIGNATURE DU CLIENT (F10).
 *
 * DEUX GÉNÉRATEURS EXISTAIENT SANS SE CONNAÎTRE. `MissionReportService` fabriquait un PDF sur le
 * disque privé ; `MissionQualityService::generateOrRefreshReport()` écrivait une fiche de synthèse
 * en base — photos avant/après, taux de checklist, incidents. Ni l'un ni l'autre ne savait que
 * l'autre existait : la fiche ne portait pas le chemin du PDF, et le PDF ignorait la synthèse.
 *
 * ET SURTOUT, RIEN NE L'ENVOYAIT AU CLIENT. Le rapport était produit à la clôture puis rangé sur un
 * disque que le client ne peut pas atteindre. Un compte rendu que le destinataire ne reçoit pas est
 * un fichier, pas un compte rendu — et c'est précisément la pièce qu'on cherche trois semaines plus
 * tard quand une contestation arrive.
 *
 * LA GÉNÉRATION EST EN SOFT-FAIL, la clôture ne l'est pas. Une bibliothèque PDF qui échoue ne doit
 * pas empêcher un prestataire de terminer sa journée : la mission se clôture, la fiche de synthèse
 * existe, et le PDF se rattrape. L'inverse — bloquer la clôture sur un rendu — laisserait des
 * missions ouvertes pour une raison qui n'intéresse personne sur le terrain.
 *
 * LA SIGNATURE EST CELLE DU CLIENT, PAS UNE CASE À COCHER. Elle passe par Contracts v2, qui la
 * date, la hache avec le contenu signé et l'attribue à un compte : une croix dans une case ne
 * prouve rien le jour où le client dit n'avoir jamais validé. Elle est FACULTATIVE — le client
 * n'est pas toujours là, et faire dépendre la clôture de sa présence bloquerait toutes les
 * interventions en son absence.
 */
class MissionClosureService
{
    public function __construct(
        protected MissionQualityService $qualityService,
        protected MissionReportService $reportService,
        protected MissionExtraService $extraService,
        protected ContractService $contracts,
    ) {}

    /**
     * Produit le rapport de fin, l'archive, et l'envoie au client.
     */
    public function cloturer(Mission $mission, ?User $auteur = null): MissionReport
    {
        $rapport = $this->qualityService->generateOrRefreshReport($mission, $auteur);

        $this->attacherLePdf($mission, $rapport);
        $this->prevenirLeClient($mission, $rapport);

        return $rapport->fresh();
    }

    /**
     * LE PDF, ET SON CHEMIN ENFIN CONSIGNÉ SUR LA FICHE.
     *
     * `mission_reports.pdf_path` existait et restait vide : la fiche de synthèse ne savait pas où
     * trouver le document qu'elle résume. Retrouver le PDF supposait de reconstruire son chemin de
     * mémoire.
     */
    protected function attacherLePdf(Mission $mission, MissionReport $rapport): void
    {
        try {
            $chemin = $this->reportService->generate($mission);

            $rapport->forceFill([
                'pdf_path' => $chemin,
                'disk' => 'private',
                'path' => $chemin,
            ])->save();
        } catch (\Throwable $e) {
            report($e);

            // Le motif est consigné SUR la fiche : sans lui, un rapport sans PDF se lit comme un
            // rapport jamais généré, et on relance une génération qui échouera pareillement.
            Log::warning('Rapport de mission : PDF non généré', [
                'mission_id' => $mission->id,
                'message' => $e->getMessage(),
            ]);

            $meta = (array) ($rapport->metadata ?? []);
            $meta['pdf_error'] = $e->getMessage();
            $rapport->forceFill(['metadata' => $meta])->save();
        }
    }

    protected function prevenirLeClient(Mission $mission, MissionReport $rapport): void
    {
        $client = $mission->booking?->client;

        if (! $client) {
            return;
        }

        try {
            $client->notify(new MissionReportReadyNotification($mission, $rapport));

            $rapport->forceFill(['sent_at' => now()])->save();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * LA SIGNATURE DU CLIENT, RECUEILLIE SUR L'ÉCRAN DU PRESTATAIRE (F10).
     *
     * Elle vaut par ce qui l'accompagne : l'horodatage, l'empreinte du contenu signé, et le compte
     * auquel elle est attribuée. Une case cochée ne prouve rien le jour où le client affirme
     * n'avoir jamais validé l'intervention.
     *
     * @param  string  $signatureData  le tracé, tel que l'écran l'a capturé
     */
    public function signerParLeClient(
        Mission $mission,
        User $client,
        string $signatureData,
        string $signerName,
        ?Request $request = null,
    ): ContractDocument {
        $rapport = MissionReport::query()->where('mission_id', $mission->id)->first();

        if (! $rapport) {
            throw new DomainException('Le rapport de fin doit exister avant d’être signé.');
        }

        $document = ContractDocument::query()->firstOrCreate(
            ['code' => $this->codeDuDocument($mission)],
            [
                // `template_id` est OBLIGATOIRE sur cette table : un document sans modèle ne peut
                // pas exister. Plutôt que de contourner la contrainte, on tient un modèle dédié au
                // rapport d'intervention — il porte la version des conditions, que la signature
                // recopie et qui devient la référence en cas de contestation.
                'template_id' => $this->modeleDuRapport()->id,
                'user_id' => $client->id,
                'body_rendered_html' => $this->corpsSignable($mission, $rapport),
                'status' => ContractDocument::STATUS_PENDING_SIGNATURE,
                'generated_at' => now(),
                'metadata' => ['mission_id' => $mission->id, 'mission_report_id' => $rapport->id],
            ],
        );

        $this->contracts->signDocument(
            $document,
            $client,
            $signatureData,
            $signerName,
            $request,
            ['mission_id' => $mission->id],
        );

        // La fiche porte la trace de la validation : c'est elle qu'on relit en cas de contestation,
        // pas la table des signatures.
        $rapport->forceFill(['client_validation' => 'signed'])->save();

        return $document->fresh();
    }

    /**
     * Le modèle « rapport d'intervention », créé à la première signature.
     *
     * UN SEUL MODÈLE POUR TOUS LES RAPPORTS, et non un par mission : c'est la version des
     * conditions qu'il porte, pas le contenu. Le contenu, lui, est propre à chaque document et
     * c'est lui que l'empreinte eIDAS-lite couvre.
     */
    protected function modeleDuRapport(): ContractTemplate
    {
        return ContractTemplate::query()->firstOrCreate(
            ['code' => 'mission_report_ack'],
            [
                'name' => 'Validation du rapport d’intervention',
                'description' => 'Signature du client attestant la réalisation de l’intervention.',
                'type' => ContractTemplate::TYPE_OTHER,
                // Le destinataire est le client : c'est lui qui atteste, pas le prestataire.
                'role' => 'client',
                'version' => '2026-08-v1',
                'body_markdown' => 'Le client atteste de la réalisation de l’intervention décrite dans le rapport joint.',
                'is_active' => true,
                'valid_from' => now()->toDateString(),
            ],
        );
    }

    protected function codeDuDocument(Mission $mission): string
    {
        return 'mission-report-'.$mission->id;
    }

    /**
     * CE QUE LE CLIENT SIGNE, EN TOUTES LETTRES.
     *
     * L'empreinte eIDAS-lite est calculée sur ce texte : il doit donc décrire précisément ce qui est
     * validé — sinon la signature atteste d'un contenu que personne ne peut reconstituer.
     */
    protected function corpsSignable(Mission $mission, MissionReport $rapport): string
    {
        $extras = $this->extraService->pourLaMission($mission)
            ->filter(fn (MissionExtra $extra) => $extra->estAccepte())
            ->map(fn (MissionExtra $extra) => $extra->label.' ('.number_format($extra->montantEnEuros(), 2, ',', ' ').' €)')
            ->implode(', ');

        $lignes = [
            'Rapport d’intervention '.$rapport->report_number,
            'Référence : '.($mission->booking->booking_reference ?? '—'),
            'Tâches réalisées : '.$rapport->checklist_completion_rate.' %',
            'Photos avant : '.$rapport->before_photos_count.' — après : '.$rapport->after_photos_count,
            'Imprévus signalés : '.$rapport->incident_count,
            'Suppléments acceptés : '.($extras !== '' ? $extras : 'aucun'),
        ];

        return '<p>'.implode('</p><p>', array_map('e', $lignes)).'</p>';
    }
}
