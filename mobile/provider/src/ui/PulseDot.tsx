import React, { useEffect } from 'react';
import { View } from 'react-native';
import Animated, { useAnimatedStyle, useSharedValue, withRepeat, withTiming } from 'react-native-reanimated';
import { colors } from '@/theme';
import { useReducedMotion } from './a11y';

type Variant = 'success' | 'urgent' | 'primary';

interface PulseDotProps { variant?: Variant; size?: number; }

const variantColors: Record<Variant, string> = {
  success: colors.success[500],
  urgent: colors.danger[500],
  primary: colors.brand[500],
};

export function PulseDot({ variant = 'success', size = 8 }: PulseDotProps) {
  const reducedMotion = useReducedMotion();
  const opacity = useSharedValue(1);

  useEffect(() => {
    if (reducedMotion) return;
    opacity.value = withRepeat(withTiming(0.3, { duration: 900 }), -1, true);
  }, [reducedMotion]);

  const animStyle = useAnimatedStyle(() => ({ opacity: opacity.value }));
  const dotColor = variantColors[variant];

  if (reducedMotion) {
    return <View style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: dotColor }} />;
  }

  return (
    <Animated.View style={[{ width: size, height: size, borderRadius: size / 2, backgroundColor: dotColor }, animStyle]} />
  );
}
