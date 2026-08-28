import fs from 'fs';
import path from 'path';

/**
 * UN CARROUSEL DE PRÉSENTATION COMPLET QUE PERSONNE N'A JAMAIS VU.
 *
 * `OnboardingScreen` (145 lignes) et son témoin `hasCompletedOnboarding()` existaient depuis
 * toujours ; aucun navigateur ne les montait. L'application prestataire avait exactement le même
 * trou, réparé de cette façon.
 *
 * On lit les fichiers de NAVIGATION, pas le composant : monter un écran dans un test prouve qu'il
 * fonctionne, jamais qu'on peut y arriver.
 */
const SRC = path.join(__dirname, '..', 'src');

const lire = (relatif: string): string => fs.readFileSync(path.join(SRC, relatif), 'utf8');

describe('Écrans branchés', () => {
  it('la présentation est montée au premier lancement', () => {
    const racine = lire('navigation/RootNavigator.tsx');

    expect(racine).toContain('hasCompletedOnboarding');
    expect(racine).toContain('<OnboardingScreen');
  });

  /**
   * LE POINT QUI FAIT LA DIFFÉRENCE ENTRE BRANCHÉ ET VISIBLE.
   *
   * En `await import()` dans un `try` dont le `catch` conclut « déjà vue », un import qui échoue
   * rend TOUJOURS vrai : la présentation ne s'affiche alors jamais, et rien ne le signale.
   */
  it('la présentation lit son témoin par un import STATIQUE', () => {
    const presentation = lire('screens/OnboardingScreen.tsx');

    expect(presentation).toContain("import * as SecureStore from 'expo-secure-store'");
    expect(presentation).not.toContain("await import('expo-secure-store')");
  });

  /** LE TÉMOIN : le lecteur de fichiers lit bien quelque chose. */
  it('témoin : la mesure lit un fichier non vide', () => {
    expect(lire('navigation/RootNavigator.tsx').length).toBeGreaterThan(500);
  });

  /** Et l'écran n'est pas monté quand la présentation a déjà été vue. */
  it('la présentation ne s’affiche que si le témoin dit non', () => {
    const racine = lire('navigation/RootNavigator.tsx');

    expect(racine).toContain('presentationVue === false');
    expect(racine).toContain('presentationVue === undefined');
  });
});
