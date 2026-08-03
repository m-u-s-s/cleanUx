/**
 * Skia est une dépendance NATIVE sur une pile récente (Expo 56 / React Native 0.85).
 *
 * Ce test ne prouve pas qu'elle rend correctement sur un appareil — il prouve qu'elle se charge et
 * expose les primitives dont le fond a besoin. C'est ce qui suffit à trancher entre des gouttes
 * procédurales et un repli en texture, AVANT d'écrire la couche qui en dépend.
 */
describe('disponibilité de Skia', () => {
  it('le module se charge et expose les primitives du fond', () => {
    const skia = require('@shopify/react-native-skia');

    // Le canvas et le dégradé portent le fond nuit ; le cercle porte les gouttes.
    expect(typeof skia.Canvas).toBe('function');
    expect(typeof skia.Circle).toBe('function');
    expect(typeof skia.LinearGradient).toBe('function');
    expect(typeof skia.RadialGradient).toBe('function');
  });
});
