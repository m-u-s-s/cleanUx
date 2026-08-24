/**
 * Le choix de thème se CONSERVE, et suit le compte.
 *
 * POURQUOI CE TEST EXISTE. `overrideMode` n'est qu'une variable de module : le mode choisi
 * disparaissait à la fermeture de l'application. Et l'API `/api/user/theme` existait — le web
 * l'appelle — sans qu'aucun écran natif ne la joigne.
 */
const mockPost = jest.fn().mockResolvedValue({ data: { ok: true } });

jest.mock('@react-native-async-storage/async-storage', () => ({
  __esModule: true,
  default: { getItem: jest.fn(), setItem: jest.fn() },
}));

jest.mock('../../../shared/src/api/client', () => ({ apiClient: { post: mockPost } }));

const CHEMIN = '../../../shared/src/theme/useColorScheme';

/**
 * Recharge le module ET son stockage ensemble. `jest.resetModules()` fabrique de NOUVELLES
 * instances : celle importée en tête du fichier n'est plus celle que le module utilise.
 */
function recharger() {
  jest.resetModules();
  const stockage = require('@react-native-async-storage/async-storage').default;
  stockage.getItem.mockReset();
  stockage.setItem.mockReset().mockResolvedValue(undefined);

  return { stockage, module: require(CHEMIN) };
}

describe('conservation du thème', () => {
  beforeEach(() => mockPost.mockClear());

  it('témoin — le module expose le crochet, le chargement, et lit bien le stockage', async () => {
    const { stockage, module } = recharger();
    stockage.getItem.mockResolvedValue('dark');

    // Sans ce témoin, un chemin faux rendrait `undefined` et les assertions suivantes
    // passeraient sur du néant.
    expect(typeof module.useColorScheme).toBe('function');
    expect(typeof module.chargerLeModeEnregistre).toBe('function');

    await module.chargerLeModeEnregistre();
    expect(stockage.getItem).toHaveBeenCalledWith('brio_theme_mode');
  });

  it('ne relit le stockage qu’une fois, même appelé par plusieurs écrans', async () => {
    const { stockage, module } = recharger();
    stockage.getItem.mockResolvedValue('light');

    await module.chargerLeModeEnregistre();
    await module.chargerLeModeEnregistre();
    await module.chargerLeModeEnregistre();

    expect(stockage.getItem).toHaveBeenCalledTimes(1);
  });

  it('ignore une valeur de stockage qui ne désigne aucun mode', async () => {
    const { stockage, module } = recharger();
    stockage.getItem.mockResolvedValue('nawak');

    // Ne doit ni lever, ni retenir la valeur : le mode reste celui du système.
    await expect(module.chargerLeModeEnregistre()).resolves.toBeUndefined();
  });

  it('survit à un stockage indisponible', async () => {
    const { stockage, module } = recharger();
    stockage.getItem.mockRejectedValue(new Error('stockage refusé'));

    await expect(module.chargerLeModeEnregistre()).resolves.toBeUndefined();
  });
});
