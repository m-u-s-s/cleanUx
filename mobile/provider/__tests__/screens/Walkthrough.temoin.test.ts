/**
 * LE TÉMOIN DE PREMIÈRE OUVERTURE DOIT POUVOIR RÉPONDRE « NON ».
 *
 * `hasCompletedWalkthrough()` chargeait `expo-secure-store` par `await import()`, dans un `try`
 * dont le `catch` rend `true` — « stockage indisponible, on saute la présentation ». Quand cet
 * import ne se prête pas à l'appel, la fonction répond donc TOUJOURS vrai : la présentation ne peut
 * plus jamais s'afficher, et rien ne le signale.
 *
 * Ce fichier remplace le magasin par un magasin VIDE, à l'inverse du défaut posé dans
 * `jest.setup.js` (« déjà vue »), pour vérifier les deux réponses et l'aller-retour entre elles.
 */
const mockMagasin = new Map<string, string>();

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (cle: string) => (mockMagasin.has(cle) ? mockMagasin.get(cle) : null)),
  setItemAsync: jest.fn(async (cle: string, valeur: string) => { mockMagasin.set(cle, valeur); }),
  deleteItemAsync: jest.fn(async (cle: string) => { mockMagasin.delete(cle); }),
}));

import * as SecureStore from 'expo-secure-store';
import { hasCompletedWalkthrough } from '@/screens/WalkthroughScreen';

const CLE = 'provider_walkthrough_completed';

describe('hasCompletedWalkthrough', () => {
  beforeEach(() => mockMagasin.clear());

  it('répond « non » tant que la présentation n’a pas été vue', async () => {
    await expect(hasCompletedWalkthrough()).resolves.toBe(false);
  });

  it('répond « oui » une fois le drapeau posé', async () => {
    // TÉMOIN POSITIF : c'est l'aller-retour qui compte. Une fonction qui répondrait toujours
    // « non » afficherait la présentation à CHAQUE lancement — l'autre moitié du même défaut.
    await SecureStore.setItemAsync(CLE, 'true');

    await expect(hasCompletedWalkthrough()).resolves.toBe(true);
  });

  it('ne prend pas une valeur quelconque pour un accord', async () => {
    await SecureStore.setItemAsync(CLE, 'peut-etre');

    await expect(hasCompletedWalkthrough()).resolves.toBe(false);
  });
});
