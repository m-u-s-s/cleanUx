import { Easing, WithTimingConfig } from 'react-native-reanimated';
import { animation } from '@/theme';

export const fadeUpConfig: WithTimingConfig = {
  duration: animation.duration.base,
  easing: Easing.bezier(...animation.easing.default),
};

export const fadeInConfig: WithTimingConfig = {
  duration: animation.duration.fast,
  easing: Easing.out(Easing.ease),
};

export const shimmerConfig: WithTimingConfig = {
  duration: 1000,
  easing: Easing.inOut(Easing.ease),
};
