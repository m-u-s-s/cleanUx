import React, { useEffect } from 'react';
import { View, ViewStyle } from 'react-native';
import Animated, { useAnimatedStyle, useSharedValue, withRepeat, withTiming, Easing } from 'react-native-reanimated';
import { radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { useReducedMotion } from './a11y';

interface SkeletonProps { width: number | string; height: number; borderRadius?: number; }

export function Skeleton({ width, height, borderRadius = radius.sm }: SkeletonProps) {
  const reducedMotion = useReducedMotion();
  const shimmer = useSharedValue(0.4);

  useEffect(() => {
    if (reducedMotion) return;
    shimmer.value = withRepeat(withTiming(1, { duration: 1000, easing: Easing.inOut(Easing.ease) }), -1, true);
  }, [reducedMotion]);

  const theme = useThemeColors();

  const animStyle = useAnimatedStyle(() => ({
    opacity: shimmer.value,
  }));

  const style: ViewStyle = {
    width: width as any,
    height,
    borderRadius,
    backgroundColor: theme.border,
  };

  if (reducedMotion) {
    return <View style={[style, { opacity: 0.7 }]} />;
  }

  return <Animated.View style={[style, animStyle]} />;
}
