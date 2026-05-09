<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase 8 — Génère une nouvelle paire de clés VAPID.
 *
 *   php artisan webpush:vapid
 *
 * Affiche les 2 clés à coller dans .env :
 *   VAPID_PUBLIC_KEY=...
 *   VAPID_PRIVATE_KEY=...
 *
 * IMPORTANT : les clés doivent rester STABLES en prod. Si tu les régénères,
 * toutes les subscriptions existantes deviennent invalides.
 */
class GenerateVapidKeysCommand extends Command
{
    protected $signature = 'webpush:vapid';
    protected $description = 'Générer une paire de clés VAPID pour Web Push';

    public function handle(): int
    {
        if (! class_exists(\Minishlink\WebPush\VAPID::class)) {
            $this->error('Le package minishlink/web-push n\'est pas installé.');
            $this->line('Installe-le avec : composer require minishlink/web-push');
            return self::FAILURE;
        }

        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();

        $this->info('✅ Clés VAPID générées avec succès !');
        $this->line('');
        $this->line('Ajoute ces lignes dans ton .env :');
        $this->line('');
        $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:contact@ton-domaine.com');
        $this->line('');
        $this->warn('⚠ Une fois en prod, NE JAMAIS régénérer ces clés.');

        return self::SUCCESS;
    }
}
