<?php

namespace App\Livewire\Admin;

use App\Models\PlatformSeatTransfer;
use App\Models\User;
use App\Notifications\TransfertDeSiegeArme;
use App\Services\Platform\SiegeDuSuperAdmin;
use App\Support\ActivityLogger;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * L'ÉCRAN DU SIÈGE — le seul endroit où il se lit et se déplace.
 *
 * SEUL LE TITULAIRE ENTRE ICI. Ni `manage-users`, ni une permission d'administration : le siège
 * n'est pas un module de plus, c'est la propriété de la plateforme. La garde est dans `mount()`
 * parce que `/livewire/update` ne rejoue aucun middleware de route — une garde posée seulement
 * sur la route laisserait les actions accessibles.
 *
 * @property-read Collection<int, User> $administrateurs
 */
#[Layout('layouts.app')]
class LeSiegeDeLaPlateforme extends Component
{
    // LA GARDE DU DEPOT EN PREMIER — elle s'execute a CHAQUE requete Livewire, `mount()`
    // seulement a la premiere. La garde du siege, ci-dessous, la resserre.
    use EnforcesAdminAccess;

    public ?string $message = null;

    public ?string $erreur = null;

    /** @var int|null Le compte vers qui armer le transfert. */
    public ?int $destinataire = null;

    public string $phrase = '';

    public string $codeDouble = '';

    public string $motifAnnulation = '';

    /**
     * SEUL LE TITULAIRE, ET A CHAQUE REQUETE.
     *
     * `boot()` s'execute aussi sur les mises a jour Livewire, la ou `mount()` ne passe
     * qu'au premier rendu : une garde posee dans `mount()` seul laisserait les ACTIONS
     * ouvertes a qui rejoue l'instantane.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() === true, 403);
    }

    #[Computed]
    public function titulaire(): ?User
    {
        return app(SiegeDuSuperAdmin::class)->titulaire();
    }

    #[Computed]
    public function transfert(): ?PlatformSeatTransfer
    {
        return app(SiegeDuSuperAdmin::class)->transfertEnAttente();
    }

    /**
     * LES CIBLES POSSIBLES : le siège ne fabrique pas un pouvoir, il déplace celui qui existe.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function administrateurs(): Collection
    {
        return User::query()
            ->whereIn('platform_role', [User::PLATFORM_ADMIN, User::PLATFORM_SUPER_ADMIN])
            ->where('is_active', true)
            ->whereKeyNot(auth()->id())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    #[Computed]
    public function delaiEnHeures(): int
    {
        return app(SiegeDuSuperAdmin::class)->delaiEnHeures();
    }

    public function armerLeTransfert(): void
    {
        $this->message = $this->erreur = null;

        $this->validate([
            'destinataire' => ['required', 'integer'],
            'phrase' => ['required', 'string'],
        ]);

        try {
            $transfert = app(SiegeDuSuperAdmin::class)->armerLeTransfert(
                auth()->user(),
                User::findOrFail($this->destinataire),
                $this->phrase,
                $this->codeDouble === '' ? null : $this->codeDouble,
                request()->ip(),
                request()->userAgent(),
            );
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();
            $this->oublierLesSecrets();

            return;
        }

        // L'ANNONCE PART AVANT TOUT LE RESTE : c'est elle qui rend le délai utile, et elle part
        // même quand c'est bien le titulaire qui a armé — un accusé de réception ne coûte rien.
        $this->titulaire()?->notify(new TransfertDeSiegeArme($transfert));

        ActivityLogger::critical('security.platform_seat_transfer_armed', $transfert->to, [
            'domain' => 'security',
            'from' => $this->titulaire()?->email,
            'effective_at' => $transfert->effective_at->toIso8601String(),
        ]);

        $this->oublierLesSecrets();
        $this->destinataire = null;
        $this->message = __('Transfert armé. Il prendra effet le :date.', [
            'date' => $transfert->effective_at->translatedFormat('d/m/Y à H:i'),
        ]);

        unset($this->transfert);
    }

    public function annulerLeTransfert(): void
    {
        $this->message = $this->erreur = null;

        $this->validate(['phrase' => ['required', 'string']]);

        try {
            app(SiegeDuSuperAdmin::class)->annulerLeTransfert(
                auth()->user(),
                $this->phrase,
                $this->motifAnnulation,
            );
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();
            $this->oublierLesSecrets();

            return;
        }

        ActivityLogger::critical('security.platform_seat_transfer_cancelled', auth()->user(), [
            'domain' => 'security',
        ]);

        $this->oublierLesSecrets();
        $this->motifAnnulation = '';
        $this->message = __('Transfert annulé. Le siège ne bouge pas.');

        unset($this->transfert);
    }

    /**
     * LA PHRASE NE SURVIT PAS À L'ACTION.
     *
     * Une propriété publique Livewire voyage dans l'instantané du navigateur à chaque rendu : la
     * laisser remplie exposerait le secret dans le HTML de la page suivante.
     */
    private function oublierLesSecrets(): void
    {
        $this->phrase = '';
        $this->codeDouble = '';
    }

    public function render(): View
    {
        return view('livewire.admin.le-siege-de-la-plateforme');
    }
}
