<?php

declare(strict_types=1);

$apply = in_array('--apply', $argv, true);
$basePath = dirname(__DIR__);

$files = [
    'app/Livewire/Admin/ExecutiveDashboard.php',
    'app/Livewire/Admin/MissionAdvancedSearch.php',
    'app/Livewire/Admin/MissionQualityCenter.php',
    'resources/views/livewire/admin/executive-dashboard.blade.php',
    'resources/views/livewire/admin/mission-advanced-search.blade.php',
    'resources/views/livewire/admin/mission-quality-center.blade.php',
];

fwrite(STDOUT, "CleanUx orphan Livewire cleanup\n");
fwrite(STDOUT, $apply ? "Mode: APPLY\n\n" : "Mode: DRY RUN\nAjoute --apply pour supprimer réellement les fichiers.\n\n");

$deleted = 0;
$missing = 0;

foreach ($files as $relativePath) {
    $fullPath = $basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (! file_exists($fullPath)) {
        fwrite(STDOUT, "[missing] {$relativePath}\n");
        $missing++;
        continue;
    }

    if (! $apply) {
        fwrite(STDOUT, "[delete] {$relativePath}\n");
        continue;
    }

    if (@unlink($fullPath)) {
        fwrite(STDOUT, "[deleted] {$relativePath}\n");
        $deleted++;
        continue;
    }

    fwrite(STDERR, "[error] Impossible de supprimer {$relativePath}\n");
}

if ($apply) {
    fwrite(STDOUT, "\nSuppression terminée. {$deleted} fichier(s) supprimé(s), {$missing} déjà absent(s).\n");
} else {
    fwrite(STDOUT, "\nAucune suppression effectuée.\n");
}
