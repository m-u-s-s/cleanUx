/**
 * L'application doit descendre de GestureHandlerRootView.
 *
 * `@gorhom/bottom-sheet` s'appuie sur react-native-gesture-handler, dont les détecteurs lèvent au
 * montage si aucune vue racine ne les enveloppe :
 *
 *   GestureDetector must be used as a descendant of GestureHandlerRootView.
 *
 * L'application prestataire la posait depuis toujours ; l'application cliente ne l'avait pas,
 * faute d'écran gestuel — jusqu'à la feuille d'actions de l'accueil. Le défaut ne s'est vu qu'à
 * l'exécution sur l'appareil : ni TypeScript ni le bundler ne peuvent le détecter, et les tests
 * d'écran montent leurs composants isolément, donc hors de la racine.
 *
 * Ce test lit la racine plutôt que de la rendre : la monter demanderait de simuler la moitié des
 * fournisseurs de l'application — polices, Stripe, requêtes, authentification, temps réel — pour
 * vérifier une seule chose, qui est structurelle.
 */
import fs from 'fs';
import path from 'path';

const APP_ROOT = path.join(__dirname, '..', 'App.tsx');

describe('Racine de l’application', () => {
  const source = fs.readFileSync(APP_ROOT, 'utf8');

  it('importe GestureHandlerRootView', () => {
    expect(source).toContain("from 'react-native-gesture-handler'");
    expect(source).toMatch(/import\s*\{[^}]*GestureHandlerRootView[^}]*\}/);
  });

  it('enveloppe l’arbre entier, et non une branche', () => {
    const open = source.indexOf('<GestureHandlerRootView');
    const close = source.indexOf('</GestureHandlerRootView>');

    expect(open).toBeGreaterThan(-1);
    expect(close).toBeGreaterThan(open);

    // Tous les fournisseurs de l'application doivent se trouver À L'INTÉRIEUR : c'est la seule
    // position qui garantit que n'importe quel écran atteint la racine gestuelle.
    for (const provider of ['<ErrorBoundary>', '<QueryClientProvider', '<AuthProvider>', '<RealtimeProvider>']) {
      const at = source.indexOf(provider);
      expect(at).toBeGreaterThan(open);
      expect(at).toBeLessThan(close);
    }
  });
});
