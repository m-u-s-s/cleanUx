<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * LE ROLE D'UN UTILISATEUR SE LIT PAR UNE PORTEE, JAMAIS PAR SA COLONNE.
 *
 * `users.role` n'est PAS la source unique : `HasUserTypeChecks` declare quatre portees qui
 * consultent DEUX signaux — `platform_role` et `role` — et acceptent `super_admin` la ou la
 * colonne seule ne connait que `admin`.
 *
 * CE QUE COUTAIT L'ECART, mesure : `QuoteRevisionArbiter` cherchait ses administrateurs par
 * `where('role', 'admin')`. Un admin marque par `platform_role` seul, ou un `super_admin`,
 * n'etait pas trouve — et la notification d'arbitrage partait a PERSONNE, sans erreur, sans
 * trace. `DisputesCenter` reecrivait la portee a la main avec des `orWhere` NON GROUPES : la
 * moindre condition ajoutee au-dessus aurait fuit sur le premier `or`.
 *
 * Rien ne pouvait le dire : la requete est valide, elle rend simplement moins de lignes.
 */
class LeRoleSeLitParUnePorteeTest extends TestCase
{
    /**
     * Les modeles qui ont LEUR PROPRE colonne `role`, sans rapport avec celle de `users`.
     *
     * `OrganizationMember::$role` vaut `owner`, `manager`, `member` — le role d'un membre DANS
     * une organisation. Le confondre avec le role de plateforme serait le defaut inverse.
     *
     * @var list<string>
     */
    private const AUTRES_MODELES = [
        'OrganizationMember',
        'organization_members',
        'TeamMember',
    ];

    public function test_aucun_code_ne_lit_la_colonne_role_a_la_main(): void
    {
        $fautes = [];

        foreach ($this->fichiersDeLApplication() as $chemin => $source) {
            foreach ($this->lignesFautives($source) as $ligne => $texte) {
                $fautes[] = $chemin.':'.$ligne.'  '.trim($texte);
            }
        }

        sort($fautes);

        $this->assertSame([], $fautes,
            'Le role se lit par `->admins()`, `->providers()`, `->clients()` ou `->companyClients()` — '
            .'ces portees consultent `platform_role` ET `role`, et connaissent `super_admin`.');
    }

    /**
     * TEMOIN. Sans lui, le test ci-dessus passerait au vert si le motif cessait de reconnaitre
     * la forme interdite — il mesurerait alors sa propre panne.
     */
    public function test_temoin_le_motif_reconnait_la_forme_interdite_et_epargne_les_autres_modeles(): void
    {
        $interdit = "return User::query()->where('role', 'admin')->get();";

        $this->assertCount(1, $this->lignesFautives($interdit),
            'Le motif ne reconnait plus la forme qu\'il doit interdire.');

        // Un membre d'organisation a SA propre colonne `role` : elle n'est pas concernee.
        $permis = "return OrganizationMember::query()->where('role', 'owner')->exists();";

        $this->assertSame([], $this->lignesFautives($permis),
            'Le motif confond le role d\'un membre d\'organisation avec celui d\'un utilisateur.');

        // Et une portee, evidemment, passe.
        $this->assertSame([], $this->lignesFautives('return User::query()->admins()->get();'));
    }

    public function test_temoin_les_portees_existent_et_consultent_les_deux_signaux(): void
    {
        $source = (string) file_get_contents(app_path('Models/Concerns/HasUserTypeChecks.php'));

        foreach (['scopeAdmins', 'scopeProviders', 'scopeClients', 'scopeCompanyClients'] as $portee) {
            $this->assertStringContainsString($portee, $source,
                $portee.' a disparu : le test ci-dessus interdirait une forme sans offrir de remplacement.');
        }

        $this->assertStringContainsString('platform_role', $source,
            'Les portees ne consultent plus `platform_role` : elles ne valent alors pas mieux que la colonne.');
    }

    /**
     * Les lignes qui lisent la colonne a la main, hors commentaires.
     *
     * LE MOTIF EST ETROIT A DESSEIN. Son premier jet accusait le role d'un participant de
     * discussion, celui d'un parcours d'integration, celui d'une recompense de parrainage —
     * et les filtres ou un administrateur CHOISIT le role a chercher, qui sont legitimes.
     * Un garde-fou qui crie a tort finit ignore.
     *
     * On ne retient donc que la forme decidable : une valeur LITTERALE de role de plateforme,
     * ou une constante de `User`. Une variable, une propriete, la constante d'un autre modele
     * ne sont pas concernees.
     *
     * @return array<int, string>
     */
    private function lignesFautives(string $source): array
    {
        $fautives = [];

        $motif = '/where\(\s*[\'"]role[\'"]\s*,\s*(?:'
            .'[\'"](?:admin|super_admin|employe|client|entreprise)[\'"]'  // litteral de plateforme
            .'|User::ROLE_[A-Z_]+'                                        // constante de User
            .')\s*[,)]/';

        foreach (preg_split('/\R/', $source) ?: [] as $i => $texte) {
            $nu = ltrim($texte);

            // UN COMMENTAIRE QUI DECRIT LE DEFAUT LE CITE FORCEMENT. Sans cette porte, ce test
            // accuserait les explications qu'on ecrit pour l'eviter — c'est deja arrive ici.
            if ($nu === '' || str_starts_with($nu, '//') || str_starts_with($nu, '*') || str_starts_with($nu, '/*')) {
                continue;
            }

            if (preg_match($motif, $texte) !== 1) {
                continue;
            }

            // Un autre modele nomme sur la MEME ligne : ce n'est pas le role de plateforme.
            foreach (self::AUTRES_MODELES as $autre) {
                if (str_contains($texte, $autre)) {
                    continue 2;
                }
            }

            $fautives[$i + 1] = $texte;
        }

        return $fautives;
    }

    /** @return array<string, string> */
    private function fichiersDeLApplication(): array
    {
        $fichiers = [];
        $base = app_path();

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

        foreach ($it as $f) {
            if (! $f->isFile() || $f->getExtension() !== 'php') {
                continue;
            }

            $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($f->getPathname(), strlen($base) + 1));

            // Les portees elles-memes lisent la colonne : c'est leur travail.
            if (str_contains($rel, 'HasUserTypeChecks')) {
                continue;
            }

            $fichiers[$rel] = (string) file_get_contents($f->getPathname());
        }

        return $fichiers;
    }
}
