<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase 8 — Génère les icônes PWA depuis un logo source ou crée des
 * placeholders monogramme.
 *
 *   php artisan pwa:icons                    # placeholders "CU" sur fond #2563eb
 *   php artisan pwa:icons --source=logo.png  # depuis un PNG/JPG/SVG existant
 *   php artisan pwa:icons --bg="#10b981"     # changer le fond
 *
 * Sortie : public/icons/icon-{72,96,128,144,152,192,384,512}.png
 *          public/icons/icon-{192,512}-maskable.png
 *
 * Dépendance : ext-imagick OU /usr/bin/convert (ImageMagick CLI).
 *
 * Pourquoi cette commande existe : sans icônes, le manifest référence des
 * fichiers absents et la PWA refuse de s'installer (Chrome ne propose pas
 * "Ajouter à l'écran d'accueil").
 */
class GeneratePwaIconsCommand extends Command
{
    protected $signature = 'pwa:icons
        {--source= : Fichier source (png/jpg/svg). Par défaut : monogramme CU généré.}
        {--bg=#2563eb : Couleur de fond (hex) pour les icônes générées.}
        {--label=CU : Texte affiché si pas de source (max 3 caractères recommandé).}
        {--force : Écraser les icônes existantes.}
    ';

    protected $description = 'Génère les icônes PWA dans public/icons/.';

    /** @var int[] Tailles "any" purpose (manifest standard). */
    protected array $sizes = [72, 96, 128, 144, 152, 192, 384, 512];

    /** @var int[] Tailles avec variante maskable (safe-area 80%). */
    protected array $maskableSizes = [192, 512];

    public function handle(): int
    {
        $source = $this->option('source');
        $bg     = $this->option('bg');
        $label  = mb_substr((string) $this->option('label'), 0, 3) ?: 'CU';
        $force  = (bool) $this->option('force');

        $convert = $this->detectConvert();
        if (! $convert) {
            $this->error("ImageMagick introuvable (ni 'convert' ni 'magick' dans le PATH).");
            $this->line("Installe-le : apt install imagemagick (Linux) / brew install imagemagick (macOS).");
            return self::FAILURE;
        }

        $iconsDir = public_path('icons');
        if (! is_dir($iconsDir) && ! mkdir($iconsDir, 0755, true)) {
            $this->error("Impossible de créer le dossier {$iconsDir}.");
            return self::FAILURE;
        }

        // Garde-fou : ne pas écraser sans --force
        if (! $force) {
            $existing = glob($iconsDir . '/icon-*.png');
            if (! empty($existing)) {
                $this->warn(count($existing) . ' icône(s) déjà présente(s) dans public/icons/.');
                $this->line('Utilise --force pour les remplacer.');
                return self::INVALID;
            }
        }

        $this->info("Génération des icônes PWA dans {$iconsDir}/");

        if ($source) {
            $this->generateFromSource($convert, $source, $iconsDir);
        } else {
            $this->generateMonogram($convert, $iconsDir, $bg, $label);
        }

        $count = count(glob($iconsDir . '/icon-*.png'));
        $this->info("✅ {$count} icône(s) générée(s).");
        $this->line('');
        $this->line('Pour utiliser un vrai logo : php artisan pwa:icons --source=path/to/logo.png --force');

        return self::SUCCESS;
    }

    protected function detectConvert(): ?string
    {
        foreach (['magick', 'convert'] as $bin) {
            $path = trim((string) shell_exec("command -v {$bin} 2>/dev/null"));
            if ($path !== '') {
                return $bin === 'magick' ? "{$path} convert" : $path;
            }
        }
        return null;
    }

    protected function generateMonogram(string $convert, string $dir, string $bg, string $label): void
    {
        foreach ($this->sizes as $size) {
            $pointsize = (int) round($size * 0.4);
            $cmd = sprintf(
                '%s -size %dx%d -background %s -fill white -gravity center '
                . '-font DejaVu-Sans-Bold -pointsize %d label:%s %s 2>/dev/null',
                $convert, $size, $size,
                escapeshellarg($bg),
                $pointsize,
                escapeshellarg($label),
                escapeshellarg("{$dir}/icon-{$size}.png"),
            );
            shell_exec($cmd);
            $this->line("  ✓ icon-{$size}.png");
        }

        // Maskable : safe-area en ajoutant un bord ~12.5% de la taille (zone safe ~80%)
        foreach ($this->maskableSizes as $size) {
            $border = (int) round($size / 8);
            $cmd = sprintf(
                '%s %s -bordercolor %s -border %dx%d -resize %dx%d %s',
                $convert,
                escapeshellarg("{$dir}/icon-{$size}.png"),
                escapeshellarg($bg),
                $border, $border,
                $size, $size,
                escapeshellarg("{$dir}/icon-{$size}-maskable.png"),
            );
            shell_exec($cmd);
            $this->line("  ✓ icon-{$size}-maskable.png");
        }
    }

    protected function generateFromSource(string $convert, string $source, string $dir): void
    {
        if (! is_file($source)) {
            $this->error("Source introuvable : {$source}");
            return;
        }

        foreach ($this->sizes as $size) {
            $cmd = sprintf(
                '%s %s -resize %dx%d -gravity center -extent %dx%d %s',
                $convert,
                escapeshellarg($source),
                $size, $size,
                $size, $size,
                escapeshellarg("{$dir}/icon-{$size}.png"),
            );
            shell_exec($cmd);
            $this->line("  ✓ icon-{$size}.png");
        }

        foreach ($this->maskableSizes as $size) {
            $inner = (int) round($size * 0.8);
            // Centre l'image source à 80% sur fond solide
            $cmd = sprintf(
                '%s -size %dx%d xc:white \( %s -resize %dx%d \) -gravity center -composite %s',
                $convert,
                $size, $size,
                escapeshellarg($source),
                $inner, $inner,
                escapeshellarg("{$dir}/icon-{$size}-maskable.png"),
            );
            shell_exec($cmd);
            $this->line("  ✓ icon-{$size}-maskable.png");
        }
    }
}
