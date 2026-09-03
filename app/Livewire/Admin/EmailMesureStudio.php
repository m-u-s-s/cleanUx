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
 * L'écran ne montre que ce que la base sait : les envois, leur gabarit, leur statut, leur date.
 *
 * LES OUVERTURES ET LES CLICS NE SONT PAS MESURÉS. Les colonnes existent sur `email_messages`,
 * `EmailWebhookEvent` existe aussi — mais AUCUNE route ni aucun écrivain ne les alimente. Afficher
 * un taux d'ouverture serait afficher un zéro permanent présenté comme un résultat : c'est
 * exactement le genre de chiffre faux qu'un tableau de bord ne doit jamais produire.
 *
 * @property-read array<string, int> $reperes
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
            return ['envoyes' => 0, 'gabarits' => 0, 'destinataires' => 0, 'echecs' => 0];
        }

        $base = EmailMessage::query()->where('created_at', '>=', $this->depuis());

        return [
            'envoyes' => (clone $base)->count(),
            'gabarits' => (clone $base)->whereNotNull('template_code')->distinct()->count('template_code'),
            'destinataires' => (clone $base)->distinct()->count('to_email'),
            'echecs' => (clone $base)->whereIn('status', ['failed', 'bounced'])->count(),
        ];
    }

    /**
     * Les envois par gabarit sur la fenêtre, du plus actif au plus discret.
     *
     * @return Collection<int, object>
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
