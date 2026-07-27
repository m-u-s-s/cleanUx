/**
 * Le dashboard doit survivre à un runtime où le module natif de carte est absent — même motif
 * que shared/src/push/availability.ts pour expo-notifications sous Expo Go Android.
 */
describe('loadMapModule', () => {
  beforeEach(() => jest.resetModules());

  it('renvoie les composants de carte quand le module est disponible', () => {
    const { loadMapModule } = require('@/maps/module');
    const mod = loadMapModule();

    expect(mod).not.toBeNull();
    expect(mod!.MapView).toBeDefined();
    expect(mod!.Marker).toBeDefined();
    expect(mod!.Callout).toBeDefined();
  });

  it('renvoie null quand le module natif est introuvable', () => {
    jest.doMock('react-native-maps', () => {
      throw new Error('native module react-native-maps is not available');
    });

    const { loadMapModule } = require('@/maps/module');
    expect(loadMapModule()).toBeNull();
  });
});
