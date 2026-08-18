<?php

namespace App\Livewire\SuperAdmin;

use App\Models\PlatformSettlementAccount;
use App\Services\Payments\PlatformSettlementService;
use App\Support\International\Devise;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * LE REGISTRE DE RÈGLEMENT BRIO — où part la commission de 20 %, et par quelle banque.
 *
 * CETTE PAGE ATTESTE, ELLE NE PILOTE PAS, et c'est une décision de sécurité et non une limite
 * technique subie. La destination réelle des versements se règle chez Stripe, derrière sa propre
 * double authentification et sa vérification bancaire. Si cette console pouvait rediriger les
 * versements, un compte super-administrateur compromis suffirait à détourner l'encaissement
 * suivant — pour le seul gain d'éviter un aller-retour vers Stripe une fois par an.
 *
 * Ce qu'elle apporte, à la place : la trace de qui a changé quoi et quand (audit automatique via
 * le modèle), la confrontation du registre aux versements réellement exécutés par Stripe, et
 * surtout l'alerte sur les devises dépourvues de compte de secours vérifié.
 */
class PlatformSettlement extends Component
{
    public string $label = '';

    /**
     * Vide a l'initialisation, posee au montage : une propriete PHP ne peut pas appeler de
     * fonction dans sa valeur par defaut, et `'eur'` en dur ferait creer des comptes de secours
     * libelles en euros sur une plateforme qui n'y regle pas.
     */
    public string $currency = '';

    public string $country = 'BE';

    public string $bank_name = '';

    public string $holder_name = '';

    public string $iban_last4 = '';

    public string $role = PlatformSettlementAccount::ROLE_BACKUP;

    public string $notes = '';

    public ?string $avis = null;

    public function mount(): void
    {
        $this->currency = strtolower(Devise::plateforme());
    }

    protected function service(): PlatformSettlementService
    {
        return app(PlatformSettlementService::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'holder_name' => ['nullable', 'string', 'max:120'],
            // QUATRE CARACTÈRES, JAMAIS L'IBAN COMPLET. Le champ est volontairement trop court
            // pour en accueillir un : le registre reconnaît un compte, il ne le rejoue pas.
            'iban_last4' => ['nullable', 'string', 'size:4', 'alpha_num'],
            'role' => ['required', 'in:primary,backup'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function enregistrer(): void
    {
        $donnees = $this->validate();

        PlatformSettlementAccount::create([
            ...$donnees,
            'currency' => strtolower($donnees['currency']),
            'country' => $donnees['country'] ? strtoupper($donnees['country']) : null,
            'status' => PlatformSettlementAccount::STATUS_DRAFT,
            'created_by_user_id' => Auth::id(),
        ]);

        $this->reset(['label', 'bank_name', 'holder_name', 'iban_last4', 'notes']);
        $this->avis = 'Compte enregistré. Déclarez-le aussi chez Stripe, puis marquez-le vérifié une fois la vérification bancaire aboutie.';
    }

    /**
     * Le compte est vérifié CHEZ STRIPE ; on ne fait qu'en prendre acte.
     *
     * C'est cette étape qui a une valeur opérationnelle : un compte vérifié d'avance est le seul
     * qui permette de changer de banque en une journée.
     */
    public function marquerVerifie(int $id): void
    {
        $compte = PlatformSettlementAccount::findOrFail($id);

        $compte->update([
            'status' => PlatformSettlementAccount::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $this->avis = 'Compte marqué comme vérifié. Il peut désormais prendre le relais sans délai bancaire.';
    }

    public function promouvoir(int $id): void
    {
        $compte = PlatformSettlementAccount::findOrFail($id);

        $this->service()->promouvoir($compte);

        $this->avis = "Registre mis à jour. ATTENTION : les versements ne changeront de destination qu'une fois le compte par défaut modifié dans le Dashboard Stripe.";
    }

    public function retirer(int $id): void
    {
        $compte = PlatformSettlementAccount::findOrFail($id);

        $compte->update(['status' => PlatformSettlementAccount::STATUS_RETIRED]);

        $this->avis = 'Compte retiré du registre. La ligne est conservée pour la traçabilité.';
    }

    public function render(): View
    {
        return view('livewire.super-admin.platform-settlement', [
            'comptesParDevise' => $this->service()->comptesParDevise(),
            'commission' => $this->service()->commissionEncaissee(),
            'devisesSansSecours' => $this->service()->devisesSansSecours(),
            'versements' => $this->service()->versementsRecents(),
        ]);
    }
}
