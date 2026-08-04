/**
 * Les ressources SECONDAIRES d'un module sont atteignables.
 *
 * POURQUOI CE FICHIER EXISTE. Cinq descripteurs servent des modèles dont la page web est un tableau
 * de bord : contrats et ordres de travail B2B, blocages de risque, signatures de contrat, jetons
 * d'API. Ils sont écrits, testés côté serveur — et l'annuaire mobile n'ouvre que la ressource
 * PRINCIPALE d'un module.
 *
 * C'est exactement le défaut que ce projet traque ailleurs : du travail correct que rien n'atteint.
 * Ni TypeScript ni Jest ne disent quoi que ce soit d'un écran qu'aucun chemin ne mène.
 */
import fs from 'fs';
import path from 'path';

const RACINE = path.join(__dirname, '..', '..');

const lire = (relatif: string) => fs.readFileSync(path.join(RACINE, relatif), 'utf8');

describe('ressources secondaires — joignabilité', () => {
  it('le type de module porte ses ressources secondaires', () => {
    // Sans le champ dans le type, le mobile ne peut pas lire ce que le serveur envoie — et
    // l'oubli passerait la compilation, l'objet étant simplement plus riche que déclaré.
    expect(lire('src/admin/types.ts')).toContain('resources');
  });

  it('l’annuaire ouvre le choix quand un module en porte plusieurs', () => {
    const annuaire = lire('src/admin/AdminDirectoryScreen.tsx');

    expect(annuaire).toContain('resources');
    expect(annuaire).toContain('AdminResourcePicker');
  });

  it('l’écran de choix est monté sur la pile racine', () => {
    const racine = lire('src/navigation/RootNavigator.tsx');

    // Une route inconnue fait tomber la navigation AU TOUCHER, pas à la compilation.
    expect(racine).toContain('name="AdminResourcePicker"');
    expect(racine).toContain('ResourcePickerScreen');
  });

  it('la route de choix est déclarée dans le typage de la pile', () => {
    expect(lire('src/navigation/types.ts')).toContain('AdminResourcePicker:');
  });

  it('l’écran de choix mène aux listes de ressources', () => {
    const ecran = lire('src/admin/console/ResourcePickerScreen.tsx');
    const racine = lire('src/navigation/RootNavigator.tsx');

    // Les deux moitiés : l'écran appelle la route, et la pile la monte.
    expect(ecran).toContain("navigate('AdminResourceList'");
    expect(racine).toContain('name="AdminResourceList"');
  });

  it('un module sans ressource secondaire garde son comportement', () => {
    const annuaire = lire('src/admin/AdminDirectoryScreen.tsx');

    /*
     * Soixante-dix modules n'ont qu'une ressource. Les faire passer par un écran de choix à une
     * seule entrée ajouterait un toucher à chaque ouverture — une régression pour tout le monde,
     * au bénéfice de cinq modules.
     */
    expect(annuaire).toContain('AdminResourceList');
  });
});
