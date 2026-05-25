import React, { useEffect } from 'react';
import { View, ViewStyle } from 'react-native';
import Animated, { useAnimatedStyle, useSharedValue, withRepeat, withTiming, Easing } from 'react-native-reanimated';
import { colors, radius } from '@/theme';
import { useReducedMotion } from './a11y';

interface SkeletonProps { width: number | string; height: number; borderRadius?: number; }

export function Skeleton({ width, height, borderRadius = radius.sm }: SkeletonProps) {
  const reducedMotion = useReducedMotion();
  const shimmer = useSharedValue(0.4);

  useEffect(() => {
    if (reducedMotion) return;
    shimmer.value = withRepeat(withTiming(1, { duration: 1000, easing: Easing.inOut(Easing.ease) }), -1, true);
  }, [reducedMotion]);

  const animStyle = useAnimatedStyle(() => ({
    opacity: shimmer.value,
  }));

  const style: ViewStyle = {
    width: width as any,
    height,
    borderRadius,
    backgroundColor: colors.surface[200],
  };

  if (reducedMotion) {
    return <View style={[style, { opacity: 0.7 }]} />;
  }

  return <Animated.View style={[style, animStyle]} />;
}
