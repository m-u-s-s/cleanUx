import fs from 'fs';
import { fr } from '@/i18n/catalogues/fr';
import path from 'path';

/**
 * DEUX ÉCRANS COMPLETS QUE PERSONNE NE POUVAIT ATTEINDRE.
 *
 * Même famille que `company/reachability.test.ts` : on lit les fichiers de NAVIGATION, pas les
 * composants. Monter un écran directement dans un test prouve qu'il fonctionne, jamais qu'on peut
 * y arriver.
 *
 * - `WalletScreen` (251 lignes) : l'onglet « Revenus » montait `EarningsScreen`, en LECTURE SEULE.
 *   Le portefeuille lit les mêmes sources et ajoute `useWithdraw` → `POST /provider/wallet/withdraw`.
 *   L'écran, le hook et l'endpoint existaient tous les trois ; aucun navigateur ne montait l'écran.
 *   Un prestataire voyait son solde sans pouvoir le retirer.
 *
 * - `WalkthroughScreen` (143 lignes) : un carrousel de présentation avec son témoin de première
 *   ouverture, monté par aucun navigateur. Personne ne l'a jamais vu.
 */
const SRC = path.join(__dirname, '..', '..', 'src');

const lire = (rel: string) => fs.readFileSync(path.join(SRC, rel), 'utf8');

describe('Écrans branchés', () => {
  it('l’onglet « Revenus » monte le portefeuille, celui qui sait retirer', () => {
    const onglets = lire('navigation/TabNavigator.tsx');

    expect(onglets).toContain('WalletScreen');
    expect(onglets).toContain('component={WalletScreen}');

    // Le nom de route ne change PAS : tous les `navigate('Earnings')` existants doivent survivre.
    expect(onglets).toContain('name="Earnings"');
  });

  it('le portefeuille sait vraiment retirer — sinon le brancher ne servirait à rien', () => {
    /*
     * TÉMOIN POSITIF. Sans lui, ce fichier garderait un écran branché qu'on aurait pu vider de sa
     * seule raison d'être : la demande de versement.
     */
    const portefeuille = lire('screens/WalletScreen.tsx');

    expect(portefeuille).toContain('useWithdraw');
    // Le libellé vit désormais dans le catalogue : on vérifie la clé ET ce qu'elle rend.
    expect(portefeuille).toContain("tr('wallet.demander_un_versement')");
    expect(fr['wallet.demander_un_versement']).toBe('Demander un versement');
  });

  it('la présentation est montée au premier lancement', () => {
    const racine = lire('navigation/RootNavigator.tsx');

    expect(racine).toContain('hasCompletedWalkthrough');
    expect(racine).toContain('<WalkthroughScreen');
  });

  it('la présentation lit son témoin par un import STATIQUE', () => {
    /*
     * Elle chargeait `expo-secure-store` par `await import()`, dans un `try` dont le `catch`
     * conclut « déjà vue, on saute ». Si cet import échoue, le témoin répond TOUJOURS vrai et la
     * présentation ne peut plus jamais s'afficher — sans qu'aucune erreur ne remonte. C'était le
     * seul endroit du dépôt à employer cette forme ; tout le reste importe statiquement.
     */
    const presentation = lire('screens/WalkthroughScreen.tsx');

    expect(presentation).toContain("import * as SecureStore from 'expo-secure-store'");
    expect(presentation).not.toContain("await import('expo-secure-store')");
  });

  it('un échec d’écriture du témoin ne reste pas muet', () => {
    // Un `catch {}` vide est exactement ce qui a masqué le défaut ci-dessus.
    const presentation = lire('screens/WalkthroughScreen.tsx');

    expect(presentation).toContain('[walkthrough]');
  });
});
