import React, { useEffect } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withSpring,
  withDelay,
  withTiming,
} from 'react-native-reanimated';
import { Button } from './Button';
import { colors, spacing, typography, radius } from '@/theme';

interface Props {
  visible: boolean;
  message: string;
  onDismiss: () => void;
}

export function SuccessOverlay({ visible, message, onDismiss }: Props) {
  const scale = useSharedValue(0);
  const opacity = useSharedValue(0);

  useEffect(() => {
    if (visible) {
      opacity.value = withTiming(1, { duration: 200 });
      scale.value = withDelay(100, withSpring(1, { damping: 12, stiffness: 150 }));
    } else {
      opacity.value = withTiming(0, { duration: 150 });
      scale.value = withTiming(0, { duration: 150 });
    }
  }, [visible]);

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
      <Animated.View style={[styles.card, cardStyle]}>
        <Text style={styles.check}>✓</Text>
        <Text style={styles.title}>Confirmé !</Text>
        <Text style={styles.message}>{message}</Text>
        <Button label="OK" onPress={onDismiss} fullWidth />
      </Animated.View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  overlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 100,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: radius.xl ?? 20,
    padding: spacing.xl,
    alignItems: 'center',
    width: '80%',
  },
  check: {
    fontSize: 48,
    color: colors.success[500],
    marginBottom: spacing.md,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginBottom: spacing.xs,
  },
  message: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
    textAlign: 'center',
    marginBottom: spacing.lg,
  },
});
