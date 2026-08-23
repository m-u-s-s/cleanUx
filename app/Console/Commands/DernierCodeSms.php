<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** LIRE LES CODES ENVOYÉS PAR SMS, SANS TÉLÉPHONE. Un émulateur ne reçoit pas de SMS. */
class DernierCodeSms extends Command
{
    protected $signature = 'sms:dernier-code
        {telephone? : Ne montrer que les messages envoyés à ce numéro}
        {--limite=5 : Nombre de messages à relire}
        {--tout : Montrer aussi les messages qui ne portent aucun code}';

    protected $description = 'Relit les derniers codes envoyés par SMS (développement : le pilote mock enregistre au lieu d’envoyer)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusé en production : ces codes sont partis vers de vrais numéros.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('sms_messages')) {
            $this->warn('Le module SMS n’est pas installé sur cette base.');

            return self::SUCCESS;
        }

        $messages = DB::table('sms_messages')
            ->when(
                $this->argument('telephone'),
                fn ($q, $tel) => $q->where('to_phone', 'like', '%'.ltrim((string) $tel, '+').'%'),
            )
            ->latest('id')
            ->limit(max(1, (int) $this->option('limite')))
            ->get(['id', 'to_phone', 'body', 'status', 'created_at']);

        if ($messages->isEmpty()) {
            $this->warn('Aucun SMS enregistré'.($this->argument('telephone') ? ' pour ce numéro.' : '.'));

            return self::SUCCESS;
        }

        $lignes = [];

        foreach ($messages as $m) {
            // Six chiffres isolés : c'est la forme de tous les codes de la plateforme — début et
            // fin de mission, vérification de numéro.
            preg_match('/\b(\d{6})\b/', (string) $m->body, $trouve);
            $code = $trouve[1] ?? null;

            if ($code === null && ! $this->option('tout')) {
                continue;
            }

            $lignes[] = [
                Carbon::parse($m->created_at)->format('H:i:s'),
                $m->to_phone,
                $code ?? '—',
                // LE STATUT EST MONTRÉ, et il n'est pas décoratif : `rate_limited` veut dire que le plafond par numéro a été atteint.
                $m->status,
                mb_strimwidth((string) $m->body, 0, 52, '…'),
            ];
        }

        if ($lignes === []) {
            $this->warn('Aucun code à six chiffres dans les derniers messages. Utilisez --tout pour les voir tous.');

            return self::SUCCESS;
        }

        $this->table(['Heure', 'Destinataire', 'Code', 'Statut', 'Message'], $lignes);

        $this->newLine();
        $this->line('  Les codes de mission expirent au bout de 20 minutes.');
        $this->line('  Pour en régénérer une paire : le prestataire retape « Je suis arrivé » (le geste est rejouable).');

        return self::SUCCESS;
    }
}
