<?php

namespace App\Livewire\Admin;

use App\Models\EmailMessage;
use App\Models\EmailTemplate;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * CE QUI EST RÉELLEMENT PARTI, ET CE QU'ON NE MESURE PAS ENCORE.
 *
 * L'écran ne montre que ce que la base sait : les envois, leur gabarit, leur statut, leur date, et
 * les retours rapportés par le service d'expédition.
 *
 * UN ZÉRO N'A PAS LE MÊME SENS SELON QUE LE POINT D'ENTRÉE EST BRANCHÉ OU NON. Sans clé de
 * signature, aucun retour n'arrive : « zéro ouverture » veut alors dire « nous l'ignorons », pas
 * « personne n'a ouvert ». L'écran dit lequel des deux — le taire serait mentir par omission.
 *
 * @property-read array<string, int> $reperes
 * @property-read bool $retoursConfigures
 * @property-read Collection<int, object> $parGabarit
 * @property-read Collection<int, EmailMessage> $derniers
 */
class EmailMesureStudio extends Component
{
    use EnforcesAdminAccess;

    #[Url(as: 'jours', except: 30)]
    public int $fenetre = 30;

    public function boot(): void
    {
        Gate::authorize('manage-communication');
    }

    /** @return array<string, int> */
    #[Computed]
    public function reperes(): array
    {
        if (! Schema::hasTable('email_messages')) {
            return array_fill_keys(
                ['envoyes', 'gabarits', 'destinataires', 'echecs', 'remis', 'ouverts', 'cliques', 'rebonds'],
                0,
            );
        }

        $base = EmailMessage::query()->where('created_at', '>=', $this->depuis());

        return [
            'envoyes' => (clone $base)->count(),
            'gabarits' => (clone $base)->whereNotNull('template_code')->distinct()->count('template_code'),
            'destinataires' => (clone $base)->distinct()->count('to_email'),
            'echecs' => (clone $base)->whereIn('status', ['failed', 'bounced'])->count(),
            'remis' => (clone $base)->whereNotNull('delivered_at')->count(),
            'ouverts' => (clone $base)->whereNotNull('opened_at')->count(),
            'cliques' => (clone $base)->whereNotNull('clicked_at')->count(),
            'rebonds' => (clone $base)->whereNotNull('bounced_at')->count(),
        ];
    }

    /**
     * LE POINT D'ENTREE EST-IL CONFIGURE ?
     *
     * Sans clef de signature, aucun retour n'arrive : les ouvertures resteront a zero, et ce zero
     * ne voudra pas dire « personne n'a ouvert » mais « nous ne le savons pas ». L'ecran doit dire
     * lequel des deux, sinon il ment par omission.
     */
    #[Computed]
    public function retoursConfigures(): bool
    {
        foreach ((array) config('email_v2.webhooks', []) as $reglages) {
            if (trim((string) ($reglages['signing_key'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Les envois par gabarit sur la fenêtre, du plus actif au plus discret.
     *
     * @return Collection<int, EmailMessage>
     */
    #[Computed]
    public function parGabarit(): Collection
    {
        if (! Schema::hasTable('email_messages')) {
            return collect();
        }

        $noms = EmailTemplate::query()->pluck('name', 'code');

        return EmailMessage::query()
            ->select('template_code', DB::raw('count(*) as envois'), DB::raw('count(distinct to_email) as destinataires'))
            ->where('created_at', '>=', $this->depuis())
            ->whereNotNull('template_code')
            ->groupBy('template_code')
            ->orderByDesc('envois')
            ->get()
            ->map(function (EmailMessage $ligne) use ($noms) {
                // LE NOM DU GABARIT PEUT AVOIR DISPARU : un envoi survit a la suppression de son
                // gabarit, et le code reste alors la seule identite disponible.
                $ligne->setAttribute('nom', $noms[$ligne->template_code] ?? $ligne->template_code);

                return $ligne;
            });
    }

    /** @return Collection<int, EmailMessage> */
    #[Computed]
    public function derniers(): Collection
    {
        if (! Schema::hasTable('email_messages')) {
            return collect();
        }

        return EmailMessage::query()->latest('created_at')->limit(15)->get();
    }

    private function depuis(): Carbon
    {
        return Carbon::now()->subDays(max(1, min(365, $this->fenetre)));
    }

    public function render(): View
    {
        return view('livewire.admin.email-mesure-studio');
    }
}
