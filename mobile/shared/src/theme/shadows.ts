import { ViewStyle } from 'react-native';

type Shadow = Pick<ViewStyle, 'shadowColor' | 'shadowOffset' | 'shadowOpacity' | 'shadowRadius' | 'elevation'>;

const shadow = (opacity: number, radius: number, offsetY: number, elevation: number): Shadow => ({
  shadowColor: '#312e81',
  shadowOffset: { width: 0, height: offsetY },
  shadowOpacity: opacity,
  shadowRadius: radius,
  // elevation is used by Android; always present so tests and cross-platform code can rely on it
  elevation,
});

export const shadows = {
  none: shadow(0, 0, 0, 0),
  xs: shadow(0.04, 1, 1, 1),
  sm: shadow(0.05, 2, 1, 2),
  soft: shadow(0.06, 6, 2, 3),
  md: shadow(0.08, 12, 4, 6),
  lg: shadow(0.12, 24, 12, 12),
} as const;
