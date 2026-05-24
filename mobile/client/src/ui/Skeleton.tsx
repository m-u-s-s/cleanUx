import React, { useEffect } from 'react';
import { ViewStyle } from 'react-native';
import Animated, { useAnimatedStyle, useSharedValue, withRepeat, withTiming, Easing } from 'react-native-reanimated';
import { colors, radius } from '@/theme';

interface SkeletonProps { width: number | string; height: number; borderRadius?: number; }

export function Skeleton({ width, height, borderRadius = radius.sm }: SkeletonProps) {
  const shimmer = useSharedValue(0.4);

  useEffect(() => {
    shimmer.value = withRepeat(withTiming(1, { duration: 1000, easing: Easing.inOut(Easing.ease) }), -1, true);
  }, []);

  const animStyle = useAnimatedStyle(() => ({
    opacity: shimmer.value,
  }));

  const style: ViewStyle = {
    width: width as any,
    height,
    borderRadius,
    backgroundColor: colors.surface[200],
  };

  return <Animated.View style={[style, animStyle]} />;
}
