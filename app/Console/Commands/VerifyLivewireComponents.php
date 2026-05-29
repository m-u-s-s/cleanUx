<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class VerifyLivewireComponents extends Command
{
    protected $signature = 'livewire:verify';

    protected $description = 'Vérifie que tous les composants Livewire sont bien définis et au bon endroit';

    public function handle()
    {
        $path = app_path('Livewire');
        if (! File::isDirectory($path)) {
            $this->error('Le dossier app/Livewire n’existe pas.');

            return;
        }

        $files = File::allFiles($path);
        $found = 0;

        $this->info('📦 Composants Livewire trouvés dans app/Livewire :');

        foreach ($files as $file) {
            $className = str_replace(
                ['/', '.php'],
                ['\\', ''],
                'App\\Livewire\\'.$file->getRelativePathname()
            );

            if (class_exists($className)) {
                $this->line("✔️ $className");
                $found++;
            } else {
                $this->error("❌ Classe non trouvée : $className — vérifie le namespace.");
            }
        }

        if ($found === 0) {
            $this->warn('Aucun composant valide trouvé.');
        } else {
            $this->info("✅ $found composant(s) vérifié(s) avec succès.");
        }
    }
}
