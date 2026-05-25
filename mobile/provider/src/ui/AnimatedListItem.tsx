import React, { useEffect } from 'react';
import Animated, { useSharedValue, useAnimatedStyle, withDelay, withTiming } from 'react-native-reanimated';
import { animation } from '@/theme';

interface Props { index: number; children: React.ReactNode; }

export function AnimatedListItem({ index, children }: Props) {
  const opacity = useSharedValue(0);
  const translateY = useSharedValue(12);

  useEffect(() => {
    const delay = Math.min(index * 50, 500);
    opacity.value = withDelay(delay, withTiming(1, { duration: animation.duration.base }));
    translateY.value = withDelay(delay, withTiming(0, { duration: animation.duration.base }));
  }, []);

  const style = useAnimatedStyle(() => ({
    opacity: opacity.value,
    transform: [{ translateY: translateY.value }],
  }));

  return <Animated.View style={style}>{children}</Animated.View>;
}
