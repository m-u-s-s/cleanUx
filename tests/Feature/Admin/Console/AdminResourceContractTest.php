<?php

namespace Tests\Feature\Admin\Console;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\Field;
use App\Admin\Console\Filter;
use Tests\TestCase;

/**
 * La forme sérialisée du contrat de console.
 *
 * Ces quatre objets sont le SEUL langage entre les descripteurs serveur et le rendu natif. Toute
 * clé renommée casse silencieusement des écrans : le mobile lit du JSON, aucun typage ne relie les
 * deux bouts. Ce test est ce lien.
 */
class AdminResourceContractTest extends TestCase
{
    public function test_une_colonne_se_serialise_avec_son_type(): void
    {
        $colonne = Column::make('total', 'Montant', Column::TYPE_MONEY);

        $this->assertSame(
            ['key' => 'total', 'label' => 'Montant', 'type' => 'money'],
            $colonne->toArray(),
        );
    }

    public function test_une_colonne_est_du_texte_par_defaut(): void
    {
        $this->assertSame('text', Column::make('name', 'Nom')->toArray()['type']);
    }

    public function test_un_type_de_colonne_inconnu_est_refuse(): void
    {
        // Un type inconnu ne planterait pas le serveur : il produirait une cellule vide sur le
        // téléphone, et personne ne saurait pourquoi. Mieux vaut refuser à la construction.
        $this->expectException(\InvalidArgumentException::class);

        Column::make('x', 'X', 'licorne');
    }

    public function test_un_filtre_porte_ses_options(): void
    {
        $filtre = Filter::select('status', 'Statut', [
            ['value' => 'open', 'label' => 'Ouvert'],
            ['value' => 'closed', 'label' => 'Clôturé'],
        ]);

        $this->assertSame([
            'key' => 'status',
            'label' => 'Statut',
            'type' => 'select',
            'options' => [
                ['value' => 'open', 'label' => 'Ouvert'],
                ['value' => 'closed', 'label' => 'Clôturé'],
            ],
        ], $filtre->toArray());
    }

    public function test_un_filtre_de_recherche_n_a_pas_d_options(): void
    {
        $this->assertSame([
            'key' => 'q',
            'label' => 'Rechercher',
            'type' => 'search',
            'options' => [],
        ], Filter::search('q', 'Rechercher')->toArray());
    }

    public function test_une_action_annonce_si_elle_est_destructive(): void
    {
        $action = Action::make('suspend', 'Suspendre', fn () => null)
            ->destructive('Ce compte ne pourra plus se connecter.');

        $this->assertSame([
            'key' => 'suspend',
            'label' => 'Suspendre',
            'destructive' => true,
            'confirm' => 'Ce compte ne pourra plus se connecter.',
            // Vide : cette action n'exige aucune saisie préalable.
            'fields' => [],
        ], $action->toArray());
    }

    public function test_une_action_ordinaire_ne_demande_pas_de_confirmation(): void
    {
        $this->assertSame([
            'key' => 'refresh',
            'label' => 'Rafraîchir',
            'destructive' => false,
            'confirm' => null,
            'fields' => [],
        ], Action::make('refresh', 'Rafraîchir', fn () => null)->toArray());
    }

    public function test_une_action_destructive_exige_un_texte_de_confirmation(): void
    {
        // Une confirmation vide afficherait une boîte de dialogue muette : l'utilisateur validerait
        // sans savoir ce qu'il détruit.
        $this->expectException(\InvalidArgumentException::class);

        Action::make('delete', 'Supprimer', fn () => null)->destructive('');
    }

    public function test_une_action_publie_les_champs_qu_elle_exige(): void
    {
        $action = Action::make('reject', 'Refuser', fn () => null)
            ->destructive('Le dossier sera refusé.')
            ->requires([
                Field::make('reason', 'Motif', Field::TYPE_TEXTAREA)->rules(['required', 'min:10']),
            ]);

        $forme = $action->toArray();

        // Le mobile doit pouvoir dessiner la feuille de saisie sans rien connaître du domaine :
        // il reçoit le type et le caractère obligatoire, jamais les règles — les publier
        // donnerait l'illusion qu'il peut valider seul.
        $this->assertSame([
            ['key' => 'reason', 'label' => 'Motif', 'type' => 'textarea', 'required' => true, 'options' => []],
        ], $forme['fields']);

        $this->assertSame(['required', 'min:10'], $action->fields()[0]->validationRules());
    }

    public function test_l_action_expose_son_execution_sans_la_serialiser(): void
    {
        $action = Action::make('ping', 'Ping', fn ($model) => "pong-{$model}");

        // La closure ne doit JAMAIS traverser le JSON : le mobile reçoit une clé, pas du code.
        $this->assertArrayNotHasKey('handler', $action->toArray());
        $this->assertSame('pong-7', ($action->handler())(7));
    }

    public function test_un_champ_porte_ses_regles_de_validation(): void
    {
        $champ = Field::make('email', 'Adresse email', Field::TYPE_EMAIL)
            ->rules(['required', 'email', 'max:255']);

        $this->assertSame([
            'key' => 'email',
            'label' => 'Adresse email',
            'type' => 'email',
            'required' => true,
            'options' => [],
        ], $champ->toArray());

        // Les règles restent côté serveur : les publier donnerait au mobile l'illusion de pouvoir
        // valider seul, alors que l'autorité est ici.
        $this->assertSame(['required', 'email', 'max:255'], $champ->validationRules());
    }

    public function test_un_champ_sait_s_il_est_obligatoire(): void
    {
        $this->assertFalse(Field::make('bio', 'Bio')->rules(['nullable'])->toArray()['required']);
        $this->assertTrue(Field::make('name', 'Nom')->rules(['required'])->toArray()['required']);
    }

    public function test_un_champ_a_choix_porte_ses_options(): void
    {
        $champ = Field::select('role', 'Rôle', [
            ['value' => 'admin', 'label' => 'Administrateur'],
            ['value' => 'client', 'label' => 'Client'],
        ]);

        $this->assertSame('select', $champ->toArray()['type']);
        $this->assertCount(2, $champ->toArray()['options']);
    }
}
