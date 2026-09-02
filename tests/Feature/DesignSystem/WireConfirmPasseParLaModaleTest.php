<?php

namespace Tests\Feature\DesignSystem;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LES CINQUANTE `wire:confirm` PASSENT PAR LA MODALE DE VERRE.
 *
 * Livewire implemente `wire:confirm` avec `window.confirm()` : la boite grise du navigateur,
 * celle que ce chantier a retiree partout ailleurs. Elle ignore le theme, ignore la langue de
 * la page, bloque le fil, se signale hors du cadre sur un telephone, et ne distingue pas
 * « Approuver ce document ? » de « Executer MAINTENANT l'erasure ? ».
 *
 * J'AVAIS AFFIRME QUE C'ETAIT IMPOSSIBLE, en disant que `confirm()` est synchrone et qu'une
 * modale ne peut pas rendre un booleen. C'est vrai de `confirm()` — mais Livewire ne consomme
 * pas sa valeur de retour : il pose `el.__livewire_confirm = (action, instead) => …` et
 * l'appelle avec DEUX RAPPELS. Une modale asynchrone s'y branche sans rien changer au reste.
 *
 * Ce test lit la source. Le comportement — la modale s'ouvre, le refus n'execute rien, la
 * confirmation appelle le rappel — se mesure dans un navigateur :
 * `tools/visual-qa/verif-confirmation.mjs`.
 */
class WireConfirmPasseParLaModaleTest extends TestCase
{
    use RefreshDatabase;

    private function interception(): string
    {
        return (string) file_get_contents(resource_path('js/confirmation-livewire.js'));
    }

    /**
     * LE FILET : sans modale montee, on rend la main a Livewire.
     *
     * `layouts/guest` ne monte pas `<x-ui.confirmation />`. Aucune page invitee ne porte
     * `wire:confirm` aujourd'hui — mais le jour ou l'une en portera, un bouton qui ne ferait
     * PLUS RIEN serait pire que la boite grise : l'utilisateur clique, rien ne bouge, et
     * rien ne le dit. Verifie en navigateur par `tools/visual-qa/verif-filet.mjs`.
     */
    public function test_le_filet_rend_la_main_quand_la_modale_manque(): void
    {
        $js = $this->interception();

        $this->assertStringContainsString('cancelable: true', $js);
        $this->assertStringContainsString('defaultPrevented', $js);

        // Et la modale doit DIRE qu'elle est la, sinon le filet se declenche toujours.
        $this->assertStringContainsString(
            '$event.preventDefault()',
            (string) file_get_contents(resource_path('views/components/ui/confirmation.blade.php')),
        );
    }

    public function test_l_interception_existe_et_est_chargee(): void
    {
        $js = $this->interception();

        $this->assertStringContainsString('__livewire_confirm', $js);
        $this->assertStringContainsString('brio-confirmer', $js);

        // Chargee : sans l'import, le fichier serait un module mort.
        $this->assertStringContainsString(
            "import './confirmation-livewire'",
            (string) file_get_contents(resource_path('js/app.js')),
        );
    }

    /**
     * TEMOIN — `wire:confirm.prompt` reste a Livewire.
     *
     * Cette variante demande de RETAPER un mot pour valider. L'avaler en silence ferait qu'une
     * confirmation forte se degraderait en simple oui/non, sans que rien ne le dise.
     */
    public function test_temoin_la_variante_prompt_est_laissee_a_livewire(): void
    {
        $this->assertStringContainsString("modifiers.includes('prompt')", $this->interception());
    }

    /**
     * ET AUCUNE VUE NE L'EMPLOIE — l'affirmation ecrite deux fois dans le depot, enfin MESUREE.
     *
     * Elle etait fausse : un `wire:confirm.prompt` vivait dans les reglages d'actions, et rien
     * ne le voyait — ce test-ci ne lisait que la source JS, et le detecteur de soumission plus
     * bas acceptait ce bouton (type="button", hors formulaire). Livewire rend cette variante par
     * `prompt()` : un navigateur qui bloque les dialogues rend le bouton inerte, sans rien dire.
     */
    public function test_aucune_vue_n_emploie_la_variante_prompt(): void
    {
        $coupables = [];

        foreach ($this->vues() as $chemin) {
            if (str_contains((string) file_get_contents($chemin), 'wire:confirm.prompt')) {
                $coupables[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $chemin);
            }
        }

        $this->assertSame([], $coupables, "Ces vues aboutissent a `prompt()`, la boite native :\n".implode("\n", $coupables));
    }

    /** TEMOIN — le meme balayage, sur `wire:confirm` nu, trouve bien des vues : il lit vraiment. */
    public function test_temoin_le_balayage_lit_vraiment_le_contenu_des_vues(): void
    {
        $porteuses = 0;

        foreach ($this->vues() as $chemin) {
            if (str_contains((string) file_get_contents($chemin), 'wire:confirm')) {
                $porteuses++;
            }
        }

        $this->assertGreaterThan(20, $porteuses, 'Le balayage ne lit rien : le test precedent mesurerait le vide.');
    }

    /**
     * LE DANGER EST LE DEFAUT, et dix confirmations disent explicitement le contraire.
     *
     * Sur cinquante, trente-neuf suppriment, retirent, suspendent ou annulent. Une modale
     * qui crie « Action irreversible » sur « Approuver ce document ? » apprend a cliquer sans
     * lire — et c'est ce qui rend la vraie alerte inutile.
     */
    public function test_les_confirmations_constructives_sont_marquees_douces(): void
    {
        $this->assertStringContainsString("modifiers.includes('doux')", $this->interception());

        $douces = 0;

        foreach ($this->vues() as $chemin) {
            $douces += substr_count((string) file_get_contents($chemin), 'wire:confirm.doux');
        }

        $this->assertSame(10, $douces, 'Le nombre de confirmations douces a change.');
    }

    /**
     * TEMOIN — « Programmer la suppression de votre compte » N'EST PAS douce.
     *
     * Son verbe l'est, son effet ne l'est pas. C'est exactement le cas qui interdit de
     * deviner le ton d'apres le verbe : « Annuler la suppression de votre compte », elle,
     * protege l'utilisateur et commence pourtant par « Annuler ».
     */
    public function test_temoin_programmer_une_suppression_reste_dangereux(): void
    {
        $vue = (string) file_get_contents(resource_path('views/livewire/client/gdpr-data-page.blade.php'));

        $this->assertMatchesRegularExpression(
            '/wire:confirm="[^"]*Programmer la suppression/u',
            $vue,
            'Cette confirmation ne doit PAS porter le modificateur doux.',
        );

        $this->assertMatchesRegularExpression(
            '/wire:confirm\.doux="[^"]*Annuler la suppression/u',
            $vue,
        );
    }

    /**
     * AUCUN BOUTON `wire:confirm` NE DOIT SOUMETTRE UN FORMULAIRE.
     *
     * C'est le seul point ou differer la reponse serait dangereux. Livewire appelle
     * `instead()` pour couper la propagation du clic ; appele APRES coup, cela ne coupe plus
     * rien — et un bouton `submit` dans un `<form>` enverrait son formulaire avant que
     * l'utilisateur n'ait repondu.
     *
     * Aucun des cinquante ne le fait aujourd'hui. Ce test le maintient.
     */
    public function test_aucune_confirmation_ne_soumet_un_formulaire(): void
    {
        $coupables = [];

        foreach ($this->vues() as $chemin) {
            $contenu = (string) file_get_contents($chemin);

            foreach ([...$this->positionsDesConfirmations($contenu)] as $position) {
                $debut = strrpos(substr($contenu, 0, $position), '<button');

                if ($debut === false) {
                    continue;
                }

                $balise = substr($contenu, $debut, (int) strpos($contenu, '>', $position) - $debut);
                $soumet = str_contains($balise, 'type="submit"') || ! str_contains($balise, 'type=');

                $avant = substr($contenu, 0, $debut);
                $dansUnFormulaire = strrpos($avant, '<form') > strrpos($avant, '</form>');

                if ($soumet && $dansUnFormulaire) {
                    $coupables[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $chemin);
                }
            }
        }

        $this->assertSame([], $coupables, 'Ces boutons doivent porter type="button" :
'.implode('
', $coupables));
    }

    /**
     * TEMOIN — le detecteur reconnait VRAIMENT un bouton de soumission.
     *
     * Sans lui, une recherche trop stricte rendrait un tableau vide en permanence : le test
     * precedent passerait en ne mesurant rien.
     */
    public function test_temoin_le_detecteur_de_soumission_fonctionne(): void
    {
        $faux = '<form><button wire:confirm="Sur ?" wire:click="go">Go</button></form>';

        $this->assertNotSame([], [...$this->positionsDesConfirmations($faux)]);

        $position = [...$this->positionsDesConfirmations($faux)][0];
        $debut = strrpos(substr($faux, 0, $position), '<button');
        $balise = substr($faux, (int) $debut, (int) strpos($faux, '>', $position) - (int) $debut);

        $this->assertFalse(str_contains($balise, 'type='), 'Ce bouton soumet par defaut.');
    }

    /** @return iterable<int, int> */
    private function positionsDesConfirmations(string $contenu): iterable
    {
        if (preg_match_all('/wire:confirm(\.[a-z]+)?=/', $contenu, $_, PREG_OFFSET_CAPTURE) === false) {
            return;
        }

        preg_match_all('/wire:confirm(\.[a-z]+)?=/', $contenu, $trouves, PREG_OFFSET_CAPTURE);

        foreach ($trouves[0] as $trouve) {
            yield (int) $trouve[1];
        }
    }

    /*
     * PAS DE SECOND DETECTEUR ICI. « Aucune vue n'appelle alert ni confirm » est deja
     * epingle par `PlusAucuneBoiteDuNavigateurTest`, avec ses deux temoins. En recopier
     * une version ici en donnerait deux a entretenir — et la copie etait deja plus
     * faible : elle neutralisait les blocs de commentaire mais pas les lignes `//`, et
     * accusait donc deux explications du defaut qu'elles decrivent.
     */

    /** @return array<int, string> */
    /**
     * DEUX AFFIRMATIONS DE CE FICHIER NE TENAIENT QUE DANS SES COMMENTAIRES.
     *
     * « Aucune page invitee ne porte wire:confirm » et le compte de cinquante vivaient en
     * prose. Un commentaire ne peut pas rougir : c'est exactement ainsi qu'une vue a pu employer
     * la variante `prompt` pendant que ce fichier affirmait le contraire, tout vert.
     */
    public function test_les_deux_affirmations_de_ce_fichier_sont_mesurees(): void
    {
        $confirmations = 0;
        $invitees = [];

        foreach ($this->vues() as $chemin) {
            $contenu = (string) file_get_contents($chemin);
            $confirmations += preg_match_all('/wire:confirm(\.[a-z]+)?=/', $contenu);

            // Une page invitee est une vue dont la coquille est `layouts.guest` : elle ne monte
            // pas la modale, donc un `wire:confirm` y retomberait sur la boite du navigateur.
            if (preg_match('/wire:confirm(\.[a-z]+)?=/', $contenu) === 1
                && preg_match('/layouts\.guest|x-guest-layout/', $contenu) === 1) {
                $invitees[] = str_replace(str_replace(chr(92), '/', resource_path('views')).'/', '', str_replace(chr(92), '/', $chemin));
            }
        }

        $this->assertSame([], $invitees, 'Une page invitee porte `wire:confirm` : la modale n’y est pas montee.');

        $this->assertSame(
            50,
            $confirmations,
            "Le compte de `wire:confirm` a change : ce fichier en annonce cinquante.\n"
            .'Mets a jour ses trois docblocks EN MEME TEMPS que ce nombre, sinon ils rementiront.'
        );
    }

    private function vues(): array
    {
        $trouvees = [];

        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterateur as $fichier) {
            if ($fichier->isFile() && str_ends_with($fichier->getFilename(), '.blade.php')) {
                $trouvees[] = $fichier->getPathname();
            }
        }

        return $trouvees;
    }
}
