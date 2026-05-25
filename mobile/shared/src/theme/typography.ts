import { Platform } from 'react-native';

export const typography = {
  fontFamily: {
    body: 'Figtree_400Regular',
    bodyMedium: 'Figtree_500Medium',
    bodySemiBold: 'Figtree_600SemiBold',
    bodyBold: 'Figtree_700Bold',
    display: 'SpaceGrotesk_700Bold',
    mono: Platform.select({ ios: 'Menlo', android: 'monospace', default: 'monospace' }),
  },
  fontSize: { '2xs': 11, xs: 12, sm: 14, base: 16, lg: 18, xl: 20, '2xl': 24, '3xl': 30, '4xl': 36 },
  lineHeight: { tight: 1.25, normal: 1.5, relaxed: 1.75 },
  fontWeight: { normal: '400' as const, medium: '500' as const, semibold: '600' as const, bold: '700' as const },
} as const;
