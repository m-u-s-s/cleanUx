import React from 'react';
import { Pressable, Text, ActivityIndicator, ViewStyle, TextStyle } from 'react-native';
import Animated, { useSharedValue, useAnimatedStyle, withTiming } from 'react-native-reanimated';
import { colors, radius, spacing, typography, animation } from '@/theme';
import { useReducedMotion } from './a11y';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'sm' | 'md' | 'lg';

interface ButtonProps {
  label: string;
  onPress: () => void;
  variant?: Variant;
  size?: Size;
  disabled?: boolean;
  loading?: boolean;
  fullWidth?: boolean;
}

const variantStyles: Record<Variant, { bg: string; text: string; border?: string }> = {
  primary: { bg: colors.brand[500], text: '#ffffff' },
  secondary: { bg: 'transparent', text: colors.brand[500], border: colors.brand[500] },
  ghost: { bg: 'transparent', text: colors.surface[600] },
  danger: { bg: colors.danger[500], text: '#ffffff' },
};

const sizeStyles: Record<Size, { paddingV: number; paddingH: number; fontSize: number }> = {
  sm: { paddingV: spacing.xs, paddingH: spacing.sm, fontSize: typography.fontSize.sm },
  md: { paddingV: spacing.sm + 4, paddingH: spacing.md + 4, fontSize: typography.fontSize.base },
  lg: { paddingV: spacing.sm + 6, paddingH: spacing.lg, fontSize: typography.fontSize.lg },
};

export function Button({
  label,
  onPress,
  variant = 'primary',
  size = 'md',
  disabled,
  loading,
  fullWidth,
}: ButtonProps) {
  const reducedMotion = useReducedMotion();
  const scale = useSharedValue(1);
  const animStyle = useAnimatedStyle(() => ({ transform: [{ scale: scale.value }] }));

  const v = variantStyles[variant];
  const s = sizeStyles[size];
  const isDisabled = disabled || loading;

  const containerStyle: ViewStyle = {
    backgroundColor: isDisabled && variant === 'primary' ? colors.surface[300] : v.bg,
    paddingVertical: s.paddingV,
    paddingHorizontal: s.paddingH,
    borderRadius: radius.md,
    borderWidth: v.border ? 1 : 0,
    borderColor: isDisabled ? colors.surface[300] : v.border,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    ...(fullWidth ? ({ width: '100%' } as ViewStyle) : {}),
  };

  const textStyle: TextStyle = {
    color: isDisabled && variant === 'primary' ? colors.surface[500] : v.text,
    fontSize: s.fontSize,
    fontWeight: typography.fontWeight.semibold,
  };

  return (
    <Animated.View style={[animStyle, fullWidth ? { width: '100%' } : {}]}>
      <Pressable
        style={containerStyle}
        onPress={onPress}
        disabled={isDisabled}
        accessibilityRole="button"
        accessibilityState={{ disabled: !!isDisabled }}
        accessibilityLabel={label}
        onPressIn={() => {
          if (!isDisabled && !reducedMotion) {
            // Dynamic haptic — graceful no-op if expo-haptics not available (Expo Go / web)
            try {
              // eslint-disable-next-line @typescript-eslint/no-var-requires, @typescript-eslint/no-unsafe-assignment
              const H = require('expo-haptics') as { impactAsync: (style: unknown) => Promise<void>; ImpactFeedbackStyle: { Light: unknown } };
              void H.impactAsync(H.ImpactFeedbackStyle.Light);
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
            color={isDisabled ? colors.surface[500] : v.text}
            size="small"
            style={{ marginRight: spacing.xs }}
          />
        ) : null}
        <Text style={textStyle}>{label}</Text>
      </Pressable>
    </Animated.View>
  );
}
