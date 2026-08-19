/**
 * LA FILE HORS-LIGNE — et les deux échecs qu'elle confondait.
 *
 * ── LE DÉFAUT MESURÉ ─────────────────────────────────────────────────────────────────────────
 *
 * `flush()` remettait dans la file TOUT ce qui n'avait pas abouti. Un refus définitif du serveur
 * — « cette tâche n'existe plus », « la mission est close » — repartait donc à chaque
 * reconnexion, pour toujours : la file ne se vidait jamais et grossissait à chaque geste manqué.
 *
 * Le réseau absent se retente : c'est la raison d'être de cette file. Le serveur qui refuse ne se
 * retente pas — insister ne rendra pas la mission ouverte.
 */
import AsyncStorage from '@react-native-async-storage/async-storage';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

import { offlineQueue } from '../offlineQueue';

beforeEach(async () => {
  await AsyncStorage.clear();
});

describe('La file hors-ligne', () => {
  /** LE TÉMOIN POSITIF : une action qui passe sort de la file. */
  it('vide ce qui a abouti', async () => {
    await offlineQueue.enqueue({ url: '/x', method: 'POST', body: {} });

    const bilan = await offlineQueue.flush(async () => true);

    expect(bilan.success).toBe(1);
    expect(await offlineQueue.getAll()).toHaveLength(0);
  });

  /** Le réseau qui manque : on garde, c'est exactement le cas prévu. */
  it('garde ce qui a échoué faute de réseau', async () => {
    await offlineQueue.enqueue({ url: '/x', method: 'POST', body: {} });

    const bilan = await offlineQueue.flush(async () => false);

    expect(bilan.failed).toBe(1);
    expect(await offlineQueue.getAll()).toHaveLength(1);
  });

  /** LE DÉFAUT CORRIGÉ : un refus du serveur sort de la file, et se raconte. */
  it('abandonne ce que le serveur refuse, au lieu de le rejouer sans fin', async () => {
    await offlineQueue.enqueue({ url: '/x', method: 'POST', body: {}, label: 'Cocher « vitres »' });

    const bilan = await offlineQueue.flush(async () => ({
      ok: false,
      permanent: true,
      reason: 'La mission est déjà close.',
    }));

    expect(bilan.dropped).toBe(1);
    expect(await offlineQueue.getAll()).toHaveLength(0);

    const abandons = await offlineQueue.abandons();
    expect(abandons).toHaveLength(1);
    expect(abandons[0]?.reason).toBe('La mission est déjà close.');
    expect(abandons[0]?.label).toBe('Cocher « vitres »');
  });

  /**
   * UNE ACTION TROP VIEILLE NE SE REJOUE PAS.
   *
   * Une case cochée hier soir a encore du sens ce matin. La même cochée il y a trois semaines
   * décrirait une mission close depuis longtemps : la rejouer réécrirait un passé que plus
   * personne ne regarde.
   */
  it('abandonne ce qui a plus de vingt-quatre heures, sans même l’envoyer', async () => {
    await offlineQueue.enqueue({ url: '/x', method: 'POST', body: {} });

    const envoi = jest.fn().mockResolvedValue(true);
    const dansTroisJours = Date.now() + 3 * 24 * 60 * 60 * 1000;

    const bilan = await offlineQueue.flush(envoi, dansTroisJours);

    expect(envoi).not.toHaveBeenCalled();
    expect(bilan.dropped).toBe(1);
    expect(await offlineQueue.getAll()).toHaveLength(0);
  });
});
