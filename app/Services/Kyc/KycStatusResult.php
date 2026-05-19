<?php

namespace App\Services\Kyc;

/**
 * Résultat d'une lecture (poll ou webhook) du statut d'une vérification chez
 * le provider externe.
 */
class KycStatusResult
{
    public function __construct(
        public readonly string $status,
        public readonly string $decision,
        public readonly ?float $score = null,
        /** @var array<int,array{type:string,result:string,sub_result?:string,confidence?:float,breakdown?:array,external_id?:string}> */
        public readonly array $checks = [],
        public readonly ?string $rejectionReason = null,
        public readonly array $raw = [],
    ) {}
}
