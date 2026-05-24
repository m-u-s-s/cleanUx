import React, { useEffect } from 'react';
import Animated, { useAnimatedStyle, useSharedValue, withRepeat, withTiming } from 'react-native-reanimated';
import { colors } from '@/theme';

type Variant = 'success' | 'urgent' | 'primary';

interface PulseDotProps { variant?: Variant; size?: number; }

const variantColors: Record<Variant, string> = {
  success: colors.success[500],
  urgent: colors.danger[500],
  primary: colors.brand[500],
};

export function PulseDot({ variant = 'success', size = 8 }: PulseDotProps) {
  const opacity = useSharedValue(1);

  useEffect(() => {
    opacity.value = withRepeat(withTiming(0.3, { duration: 900 }), -1, true);
  }, []);

  const animStyle = useAnimatedStyle(() => ({ opacity: opacity.value }));
  const dotColor = variantColors[variant];

  return (
    <Animated.View style={[{ width: size, height: size, borderRadius: size / 2, backgroundColor: dotColor }, animStyle]} />
  );
}
