import { Platform } from 'react-native';

/**
 * LA VOIX DE LA MARQUE, IDENTIQUE AU WEB.
 *
 * `display` porte Allura — les titres et les accroches. `body` porte Sora — les libellés,
 * les tableaux, les chiffres. La séparation n'est pas décorative : Allura est une cursive,
 * superbe en grand titre et illisible en libellé de colonne, et l'illisible n'est jamais
 * gracieux.
 *
 * ALLURA A UN ŒIL TRÈS PETIT : à taille égale elle paraît deux fois plus menue qu'une sans.
 * `displaySize` corrige ce décalage — un titre en Allura posé à la taille d'un titre en
 * Sora se perd sur l'écran. Le web applique la même correction via `clamp()`.
 *
 * Figtree et Space Grotesk restent déclarées : des écrans les nomment encore directement,
 * et les retirer les ferait tomber sur la police système sans que rien ne le signale.
 */
export const typography = {
  fontFamily: {
    body: 'Sora_400Regular',
    bodyMedium: 'Sora_500Medium',
    bodySemiBold: 'Sora_600SemiBold',
    bodyBold: 'Sora_700Bold',
    bodyExtraBold: 'Sora_800ExtraBold',
    display: 'Allura_400Regular',
    /** L'ancienne voix — conservée pour les écrans qui la nomment encore. */
    legacyBody: 'Figtree_400Regular',
    legacyDisplay: 'SpaceGrotesk_700Bold',
    mono: Platform.select({ ios: 'Menlo', android: 'monospace', default: 'monospace' }),
  },
  fontSize: { '2xs': 11, xs: 12, sm: 14, base: 16, lg: 18, xl: 20, '2xl': 24, '3xl': 30, '4xl': 36 },
  /** Les tailles d'un titre en Allura : ~1,55× la sans équivalente. */
  displaySize: { sm: 28, base: 34, lg: 42, xl: 52 },
  lineHeight: { tight: 1.25, normal: 1.5, relaxed: 1.75 },
  fontWeight: { normal: '400' as const, medium: '500' as const, semibold: '600' as const, bold: '700' as const, extraBold: '800' as const },
  letterSpacing: {
    tight: -0.5,
    normal: 0,
    wide: 1,
  },
  preset: {
    /** Un titre d'écran : Allura, grande, interligne serré — la cursive respire d'elle-même. */
    display: {
      fontFamily: 'Allura_400Regular',
      fontSize: 42,
      lineHeight: 48,
      letterSpacing: 0.2,
    },
    headline: { fontSize: 30, lineHeight: 36, letterSpacing: -0.5 },
    subhead: { fontSize: 12, letterSpacing: 1, textTransform: 'uppercase' as const },
    bodyReadable: { fontSize: 16, lineHeight: 26, maxWidth: 320 },
  },
} as const;
