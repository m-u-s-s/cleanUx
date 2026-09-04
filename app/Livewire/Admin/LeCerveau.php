<?php

namespace App\Livewire\Admin;

use App\Services\Cerveau\Cerveau;
use App\Services\Cerveau\Geste;
use App\Services\Cerveau\Recommandation;
use App\Services\Cerveau\RegistreDesGestes;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * LE CERVEAU — ce que la plateforme a vu, et ce qu'on peut en faire.
 *
 * RÉSERVÉ AU TITULAIRE DU SIÈGE, garde dans `boot()` : `/livewire/update` ne rejoue aucun
 * middleware de route, et une garde posée au premier rendu laisserait les GESTES ouverts.
 *
 * RIEN NE S'APPLIQUE SANS UN CLIC. Chaque geste affiche, AVANT ce clic, ce qu'il fait, ce qu'il
 * implique, et s'il est réversible. Un bouton qui ne dit pas ce qu'il coûte n'est pas un
 * conseil : c'est un piège.
 */
#[Layout('layouts.app')]
class LeCerveau extends Component
{
    use EnforcesAdminAccess;

    public string $domaine = '';

    public ?string $message = null;

    public ?string $erreur = null;

    /** Le geste dont on demande confirmation, avant de l'appliquer. */
    #[Locked]
    public ?string $gesteADetailler = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $argumentsDuGeste = [];

    public function boot(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() === true, 403);
    }

    /** @return list<Recommandation> */
    #[Computed]
    public function recommandations(): array
    {
        return app(Cerveau::class)->recommandations($this->domaine === '' ? null : $this->domaine);
    }

    /** @return array<string, int> */
    #[Computed]
    public function compteurs(): array
    {
        return app(Cerveau::class)->compteurs();
    }

    #[Computed]
    public function gesteDetaille(): ?Geste
    {
        return $this->gesteADetailler === null
            ? null
            : app(RegistreDesGestes::class)->trouver($this->gesteADetailler);
    }

    /**
     * DEMANDER LE DÉTAIL AVANT D'APPLIQUER.
     *
     * On n'exécute pas au premier clic : le second clic est celui qui compte, et entre les deux
     * on affiche ce que le geste implique.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function preparerLeGeste(string $cle, array $arguments = []): void
    {
        $this->message = $this->erreur = null;
        $this->gesteADetailler = $cle;
        $this->argumentsDuGeste = $arguments;
    }

    public function abandonnerLeGeste(): void
    {
        $this->gesteADetailler = null;
        $this->argumentsDuGeste = [];
    }

    public function appliquerLeGeste(): void
    {
        $this->message = $this->erreur = null;

        if ($this->gesteADetailler === null) {
            return;
        }

        try {
            $this->message = app(Cerveau::class)->appliquer(
                auth()->user(),
                $this->gesteADetailler,
                $this->argumentsDuGeste,
            );
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();

            return;
        }

        $this->abandonnerLeGeste();
        unset($this->recommandations, $this->compteurs);
    }

    public function render(): View
    {
        return view('livewire.admin.le-cerveau');
    }
}
