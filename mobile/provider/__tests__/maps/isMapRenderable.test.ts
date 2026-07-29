/**
 * La carte n'est affichable sur Android qu'avec une VRAIE clé Google Maps.
 *
 * Sans clé, react-native-maps ne dégrade pas : il lève
 * « IllegalStateException: API key not found » et emporte le tableau de bord entier.
 *
 * Une simple vérification « chaîne non vide » ne suffit pas — le .env du backend porte
 * `GOOGLE_MAPS_API_KEY=ta_cle_google_maps`, et recopier ce placeholder la contournerait,
 * laissant le crash revenir. D'où le contrôle de forme testé ici.
 */
import { Platform } from 'react-native';
import Constants from 'expo-constants';
import { isMapRenderable } from '../../src/maps/module';

jest.mock('expo-constants', () => ({ __esModule: true, default: { expoConfig: {} } }));

function setKey(apiKey: unknown) {
  (Constants as any).expoConfig = apiKey === undefined
    ? {}
    : { android: { config: { googleMaps: { apiKey } } } };
}

const A_PLAUSIBLE_KEY = `AIza${'a'.repeat(35)}`;

describe('isMapRenderable', () => {
  afterEach(() => {
    Platform.OS = 'android';
  });

  it('accepte une clé à la forme attendue', () => {
    Platform.OS = 'android';
    setKey(A_PLAUSIBLE_KEY);

    expect(isMapRenderable()).toBe(true);
  });

  it('refuse une clé absente', () => {
    Platform.OS = 'android';
    setKey(undefined);

    expect(isMapRenderable()).toBe(false);
  });

  it('refuse une chaîne vide', () => {
    Platform.OS = 'android';
    setKey('');

    expect(isMapRenderable()).toBe(false);
  });

  /** Le cas qui a failli passer : le placeholder du .env backend. */
  it('refuse le placeholder du backend', () => {
    Platform.OS = 'android';
    setKey('ta_cle_google_maps');

    expect(isMapRenderable()).toBe(false);
  });

  /** iOS s'appuie sur Apple Maps : aucune clé n'y est requise. */
  it('ne bloque jamais iOS', () => {
    Platform.OS = 'ios';
    setKey(undefined);

    expect(isMapRenderable()).toBe(true);
  });
});
