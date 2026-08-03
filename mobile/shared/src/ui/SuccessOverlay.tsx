import React, { useEffect } from 'react';
import { View, Text, StyleSheet, Dimensions } from 'react-native';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withSpring,
  withDelay,
  withTiming,
  Easing,
} from 'react-native-reanimated';
import { Button } from './Button';
import { colors, spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useReducedMotion } from './a11y';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');
const CONFETTI_COLORS = [
  colors.accent.amber,
  colors.brand[500],
  colors.accent.cyan,
  colors.success[500],
  colors.accent.violet,
];

interface ConfettiPieceProps { index: number }

function ConfettiPiece({ index }: ConfettiPieceProps) {
  const styles = stylesFor(useThemeColors());

  const x = useSharedValue(SCREEN_WIDTH / 2);
  const y = useSharedValue(-20);
  const rotation = useSharedValue(0);
  const opacity = useSharedValue(1);
  const color = CONFETTI_COLORS[index % CONFETTI_COLORS.length];
  const isRound = (index % 2 === 0);

  useEffect(() => {
    const targetX = Math.random() * SCREEN_WIDTH;
    const targetY = SCREEN_HEIGHT * (0.3 + Math.random() * 0.5);
    const delay = index * 30;
    const direction = Math.random() > 0.5 ? 1 : -1;

    x.value = withDelay(delay, withTiming(targetX, { duration: 1200, easing: Easing.out(Easing.quad) }));
    y.value = withDelay(delay, withTiming(targetY, { duration: 1200, easing: Easing.in(Easing.quad) }));
    rotation.value = withDelay(delay, withTiming(360 * direction, { duration: 1200 }));
    opacity.value = withDelay(delay + 800, withTiming(0, { duration: 400 }));
  }, []);

  const style = useAnimatedStyle(() => ({
    position: 'absolute',
    left: x.value,
    top: y.value,
    width: 8,
    height: 8,
    borderRadius: isRound ? 4 : 1,
    backgroundColor: color,
    transform: [{ rotate: `${rotation.value}deg` }],
    opacity: opacity.value,
  }));

  return <Animated.View style={style} />;
}

interface Props {
  visible: boolean;
  message: string;
  onDismiss: () => void;
}

export function SuccessOverlay({ visible, message, onDismiss }: Props) {
  const styles = stylesFor(useThemeColors());

  const reducedMotion = useReducedMotion();
  const scale = useSharedValue(0);
  const opacity = useSharedValue(0);

  useEffect(() => {
    if (visible) {
      opacity.value = withTiming(1, { duration: reducedMotion ? 0 : 200 });
      scale.value = reducedMotion
        ? withTiming(1, { duration: 0 })
        : withDelay(100, withSpring(1, { damping: 12, stiffness: 150 }));
    } else {
      opacity.value = withTiming(0, { duration: reducedMotion ? 0 : 150 });
      scale.value = withTiming(0, { duration: reducedMotion ? 0 : 150 });
    }
  }, [visible, reducedMotion]);

  const cardStyle = useAnimatedStyle(() => ({
    transform: [{ scale: scale.value }],
    opacity: opacity.value,
  }));

  const overlayStyle = useAnimatedStyle(() => ({
    opacity: opacity.value,
  }));

  if (!visible) return null;

  return (
    <Animated.View style={[styles.overlay, overlayStyle]}>
      {/* Confetti particles — skipped when reduced motion is active */}
      {!reducedMotion && Array.from({ length: 30 }).map((_, i) => (
        <ConfettiPiece key={i} index={i} />
      ))}

      <Animated.View style={[styles.card, cardStyle]}>
        <Text style={styles.check}>✓</Text>
        <Text style={styles.title}>Confirmé !</Text>
        <Text style={styles.message}>{message}</Text>
        <Button label="Parfait" onPress={onDismiss} fullWidth />
      </Animated.View>
    </Animated.View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  overlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 100,
  },
  card: {
    backgroundColor: t.card,
    borderRadius: radius.xl ?? 20,
    padding: spacing.xl,
    alignItems: 'center',
    width: '80%',
    zIndex: 101,
  },
  check: {
    fontSize: 56,
    color: colors.success[500],
    marginBottom: spacing.md,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginBottom: spacing.xs,
  },
  message: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    textAlign: 'center',
    marginBottom: spacing.lg,
  },
});
