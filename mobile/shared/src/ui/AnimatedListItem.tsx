import React, { useEffect } from 'react';
import Animated, { useSharedValue, useAnimatedStyle, withDelay, withTiming } from 'react-native-reanimated';
import { animation } from '@/theme';
import { useReducedMotion } from './a11y';

interface Props { index: number; children: React.ReactNode; }

export function AnimatedListItem({ index, children }: Props) {
  const reducedMotion = useReducedMotion();
  const opacity = useSharedValue(reducedMotion ? 1 : 0);
  const translateY = useSharedValue(reducedMotion ? 0 : 12);

  useEffect(() => {
    if (reducedMotion) return;
    const delay = Math.min(index * 50, 500);
    opacity.value = withDelay(delay, withTiming(1, { duration: animation.duration.base }));
    translateY.value = withDelay(delay, withTiming(0, { duration: animation.duration.base }));
  }, [reducedMotion]);

  const style = useAnimatedStyle(() => ({
    opacity: opacity.value,
    transform: [{ translateY: translateY.value }],
  }));

  if (reducedMotion) return <>{children}</>;

  return <Animated.View style={style}>{children}</Animated.View>;
}
