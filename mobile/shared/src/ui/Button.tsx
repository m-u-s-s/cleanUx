import React from 'react';
import { Pressable, Text, ActivityIndicator, ViewStyle, TextStyle } from 'react-native';
import Animated, { useSharedValue, useAnimatedStyle, withTiming } from 'react-native-reanimated';
import { colors, radius, spacing, typography, animation } from '@/theme';
import { useThemeColors, type ThemeTokens } from '@/theme/useThemeColors';
import { useReducedMotion } from './a11y';

type Variant =
  | 'primary'
  | 'secondary'
  | 'ghost'
  | 'danger'
  | 'success'
  | 'outline'
  | 'link'
  | 'amber'
  | 'glass';
type Size = 'sm' | 'md' | 'lg';

interface ButtonProps {
  label: string;
  onPress: () => void;
  variant?: Variant;
  size?: Size;
  disabled?: boolean;
  loading?: boolean;
  fullWidth?: boolean;
  /**
   * Un identifiant de test, facultatif.
   *
   * `accessibilityLabel` porte déjà le libellé, mais deux boutons peuvent porter le MÊME libellé
   * dans une liste — « Approuver » sur chaque ligne. Un test qui presse par le texte choisit alors
   * le premier au hasard de l'ordre de rendu, et passe pour de mauvaises raisons.
   */
  testID?: string;
}

interface Habillage {
  bg: string;
  text: string;
  border?: string;
}

/**
 * Les huit variantes historiques, inchangées.
 *
 * Elles sont volontairement hors du thème : leurs couleurs sont SÉMANTIQUES — un bouton danger est
 * rouge sur n'importe quel fond, sinon ce n'est plus un bouton danger. Seuls `ghost` et `glass`
 * dépendent du fond, et sont donc résolus par la fonction ci-dessous.
 */
const variantStyles: Record<Exclude<Variant, 'glass'>, Habillage> = {
  primary: { bg: colors.brand[500], text: '#ffffff' },
  secondary: { bg: 'transparent', text: colors.brand[500], border: colors.brand[500] },
  ghost: { bg: 'transparent', text: colors.surface[600] },
  danger: { bg: colors.danger[500], text: '#ffffff' },
  success: { bg: colors.success[500], text: '#ffffff' },
  outline: { bg: 'transparent', text: colors.accent.amber, border: colors.accent.amber },
  link: { bg: 'transparent', text: colors.accent.amber },
  amber: { bg: colors.accent.amber, text: '#0f172a' },
};

/**
 * Résout l'habillage d'une variante pour le thème courant.
 *
 * `glass` EXISTE DÉSORMAIS DANS LES DEUX MODES.
 *
 * Elle retombait sur `secondary` en clair, et la raison était bonne : sans rien derrière à
 * filtrer, un voile translucide sur un aplat uni est indiscernable d'une surface opaque —
 * autant emprunter la variante qui existait déjà.
 *
 * Depuis que la toile claire porte ses auras, il y a quelque chose à filtrer. Le voile clair
 * est plus DENSE que le sombre (0,86 contre 0,10) : c'est le voile, pas le flou, qui garantit
 * le contraste du libellé — un flou mélange les pixels, il ne les fonce pas.
 */
function habillageDe(variant: Variant, theme: ThemeTokens): Habillage {
  if (variant === 'glass') {
    return { bg: theme.glassStrong, text: theme.textOnGlass, border: theme.glassBorder };
  }

  if (variant === 'ghost' && theme.isDark) {
    return { bg: 'transparent', text: theme.textSecondary };
  }

  return variantStyles[variant];
}

const sizeStyles: Record<Size, { paddingV: number; paddingH: number; fontSize: number; fontFamily: string }> = {
  sm: { paddingV: spacing.xs, paddingH: spacing.sm, fontSize: typography.fontSize.sm, fontFamily: typography.fontFamily.bodySemiBold },
  md: { paddingV: spacing.sm + 4, paddingH: spacing.md + 4, fontSize: typography.preset.bodyReadable.fontSize, fontFamily: typography.fontFamily.bodySemiBold },
  lg: { paddingV: spacing.sm + 6, paddingH: spacing.lg, fontSize: typography.fontSize.lg, fontFamily: typography.fontFamily.bodySemiBold },
};

export function Button({
  label,
  onPress,
  variant = 'primary',
  size = 'md',
  disabled,
  loading,
  fullWidth,
  testID,
}: ButtonProps) {
  const reducedMotion = useReducedMotion();
  const theme = useThemeColors();
  const scale = useSharedValue(1);
  const animStyle = useAnimatedStyle(() => ({ transform: [{ scale: scale.value }] }));

  const v = habillageDe(variant, theme);
  const s = sizeStyles[size];
  const isDisabled = disabled || loading;

  /*
   * L'état désactivé dépend du fond, contrairement aux variantes.
   *
   * `colors.surface[300]` est un gris CLAIR. Posé sur le fond nuit, un bouton désactivé devenait
   * plus lumineux que le bouton actif à côté — l'inverse exact de ce qu'un état désactivé doit
   * communiquer. Ces trois couleurs vivaient dans des ternaires, ce qui les avait fait échapper au
   * garde-fou anti-couleur-en-dur, corrigé depuis.
   */
  const eteintFond = theme.isDark ? 'rgba(232, 238, 252, 0.08)' : colors.surface[300];
  const eteintTexte = theme.isDark ? 'rgba(147, 164, 198, 0.55)' : colors.surface[400];

  const isSolidVariant = v.bg !== 'transparent';
  const containerStyle: ViewStyle = {
    backgroundColor: isDisabled && isSolidVariant ? eteintFond : v.bg,
    paddingVertical: s.paddingV,
    paddingHorizontal: s.paddingH,
    borderRadius: variant === 'link' ? 0 : radius.md,
    borderWidth: v.border ? 1 : 0,
    borderColor: isDisabled ? eteintFond : (v.border ?? 'transparent'),
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    ...(fullWidth ? ({ width: '100%' } as ViewStyle) : {}),
  };

  const textStyle: TextStyle = {
    color: isDisabled ? eteintTexte : v.text,
    fontSize: s.fontSize,
    fontWeight: typography.fontWeight.semibold,
    fontFamily: s.fontFamily,
    ...(variant === 'link' ? { textDecorationLine: 'underline' } : {}),
  };

  return (
    <Animated.View style={[animStyle, fullWidth ? { width: '100%' } : {}]}>
      <Pressable
        style={containerStyle}
        onPress={onPress}
        disabled={isDisabled}
        testID={testID}
        accessibilityRole="button"
        accessibilityState={{ disabled: !!isDisabled }}
        accessibilityLabel={label}
        onPressIn={() => {
          if (!isDisabled && !reducedMotion) {
            // Dynamic haptic — graceful no-op if expo-haptics is unavailable.
            // The catch below only covers require() failing. When the JS package
            // resolves but its native module is not linked into the build,
            // impactAsync() *rejects* instead, which a synchronous catch cannot
            // see — hence the explicit .catch().
            try {
              // eslint-disable-next-line @typescript-eslint/no-var-requires, @typescript-eslint/no-unsafe-assignment
              const H = require('expo-haptics') as { impactAsync: (style: unknown) => Promise<void>; ImpactFeedbackStyle: { Light: unknown } };
              void H.impactAsync(H.ImpactFeedbackStyle.Light).catch(() => {});
            } catch {}
            scale.value = withTiming(0.96, { duration: animation.duration.fast });
          }
        }}
        onPressOut={() => {
          scale.value = withTiming(1, { duration: animation.duration.fast });
        }}
      >
        {loading ? (
          <ActivityIndicator
            color={isDisabled ? eteintTexte : v.text}
            size="small"
            style={{ marginRight: spacing.xs }}
          />
        ) : null}
        <Text style={textStyle}>{label}</Text>
      </Pressable>
    </Animated.View>
  );
}
