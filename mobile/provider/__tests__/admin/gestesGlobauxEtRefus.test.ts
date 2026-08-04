/**
 * Les gestes GLOBAUX et les REFUS MOTIVÉS arrivent jusqu'à l'écran.
 *
 * POURQUOI CE FICHIER EXISTE. Le serveur a gagné trois choses en portant les modules : des actions
 * qui ne portent sur aucune ligne (purger un cache, relancer une file, simuler un matching), un
 * refus de suppression qui DIT pourquoi, et un refus propre aux comptes en lecture seule.
 *
 * Les trois traversent l'API et s'arrêtaient au mobile. Une action globale sans bouton est une
 * action qui n'existe pas ; un refus sans motif renvoie l'administrateur à la version web pour
 * apprendre ce que le serveur vient de lui dire.
 */
import fs from 'fs';
import path from 'path';

const RACINE = path.join(__dirname, '..', '..');

const lire = (relatif: string) => fs.readFileSync(path.join(RACINE, relatif), 'utf8');

describe('actions globales', () => {
  it('le descripteur mobile porte les actions globales', () => {
    // Sans le champ, la réponse du serveur est plus riche que le type : l'oubli compile.
    expect(lire('src/admin/console/types.ts')).toContain('global_actions');
  });

  it('un hook les envoie sur la route sans identifiant de ligne', () => {
    const hooks = lire('src/admin/console/hooks.ts');

    /*
     * La route globale est `/actions/{action}` — SANS identifiant. Réutiliser le hook par ligne
     * avec un identifiant inventé viserait une ligne au hasard.
     */
    expect(hooks).toContain('/actions/');
    expect(hooks).toMatch(/useResourceGlobalAction/);
  });

  it('la liste rend un bouton par action globale', () => {
    const liste = lire('src/admin/console/ResourceListScreen.tsx');

    expect(liste).toContain('global_actions');
    expect(liste).toContain('useResourceGlobalAction');
  });

  it('une action globale à paramètres passe par la même feuille de saisie', () => {
    const liste = lire('src/admin/console/ResourceListScreen.tsx');

    // « Simuler le matching » demande une mission ; sans feuille, le geste part sans son paramètre.
    expect(liste).toContain('ActionInputSheet');
  });
});

describe('refus motivés', () => {
  it('le refus de suppression affiche les raisons du serveur', () => {
    const hooks = lire('src/admin/console/hooks.ts');

    /*
     * Le serveur répond 409 avec la LISTE des raisons — « 3 zones rattachées », « 12 missions en
     * cours ». Les remplacer par « Une erreur est survenue » perd la seule information qui permet
     * d'agir, et renvoie l'administrateur au poste de travail pour l'apprendre.
     */
    expect(hooks).toContain('delete_refused');
    expect(hooks).toContain('reasons');
  });

  it('le refus lecture seule est traduit', () => {
    // Le code existe côté catalogue mais manquait à la console : deux portes, une seule traduite.
    expect(lire('src/admin/console/hooks.ts')).toContain('forbidden_readonly');
  });
});
