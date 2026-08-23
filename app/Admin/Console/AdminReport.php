<?php

namespace App\Admin\Console;

/** Le contrat d'un module d'administration qui n'est PAS une liste. POURQUOI UN SECOND CONTRAT. */
interface AdminReport
{
    /** La clé du module dans `config/admin_console.php`. */
    public function key(): string;

    /**
     * Les sections du rapport, chacune portant ses tuiles.
     *
     * @return list<array{title: string, tiles: list<ReportTile>}>
     */
    public function sections(): array;
}
