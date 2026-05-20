<?php

namespace App\Services\AccountingV2\Exports;

use Illuminate\Database\Eloquent\Builder;

interface ExportBuilderContract
{
    public function format(): string;

    /**
     * Construit le contenu du fichier export pour une période.
     *
     * @return array{content:string, row_count:int, mime:string, extension:string}
     */
    public function build(Builder $entriesQuery, array $opts = []): array;
}
