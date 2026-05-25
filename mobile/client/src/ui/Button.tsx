import React from 'react';
import { Pressable, Text, ActivityIndicator, ViewStyle, TextStyle } from 'react-native';
import Animated, { useSharedValue, useAnimatedStyle, withTiming } from 'react-native-reanimated';
import { colors, radius, spacing, typography, animation } from '@/theme';

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
  md: { paddingV: spacing.sm + 2, paddingH: spacing.md, fontSize: typography.fontSize.base },
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
  const scale = useSharedValue(1);
  const animStyle = useAnimatedStyle(() => ({ transform: [{ scale: scale.value }] }));

  const v = variantStyles[variant];
  const s = sizeStyles[size];

  const containerStyle: ViewStyle = {
    backgroundColor: v.bg,
    paddingVertical: s.paddingV,
    paddingHorizontal: s.paddingH,
    borderRadius: radius.md,
    borderWidth: v.border ? 1 : 0,
    borderColor: v.border,
    opacity: disabled ? 0.5 : 1,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    ...(fullWidth ? ({ width: '100%' } as ViewStyle) : {}),
  };

  const textStyle: TextStyle = {
    color: v.text,
    fontSize: s.fontSize,
    fontWeight: typography.fontWeight.semibold,
  };

  return (
    <Animated.View style={[animStyle, fullWidth ? { width: '100%' } : {}]}>
      <Pressable
        style={containerStyle}
        onPress={onPress}
        disabled={disabled || loading}
        onPressIn={() => { scale.value = withTiming(0.96, { duration: animation.duration.fast }); }}
        onPressOut={() => { scale.value = withTiming(1, { duration: animation.duration.fast }); }}
      >
        {loading ? (
          <ActivityIndicator
            color={v.text}
            size="small"
            style={{ marginRight: spacing.xs }}
          />
        ) : null}
        <Text style={textStyle}>{label}</Text>
      </Pressable>
    </Animated.View>
  );
}
