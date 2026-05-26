import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import NetInfo from '@react-native-community/netinfo';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withTiming,
} from 'react-native-reanimated';
import { colors, spacing, typography } from '@/theme';

// ── Types ─────────────────────────────────────────────────────────────────────

interface OfflineBannerProps {
  /** Override for testing */
  forceOffline?: boolean;
}

// ── Component ─────────────────────────────────────────────────────────────────

export function OfflineBanner({ forceOffline }: OfflineBannerProps) {
  const [isOffline, setIsOffline] = React.useState(false);
  const translateY = useSharedValue(-60);

  const visible = forceOffline ?? isOffline;

  React.useEffect(() => {
    const unsubscribe = NetInfo.addEventListener((state) => {
      const offline = !(state.isConnected && state.isInternetReachable !== false);
      setIsOffline(offline);
    });
    return unsubscribe;
  }, []);

  React.useEffect(() => {
    translateY.value = withTiming(visible ? 0 : -60, { duration: 300 });
  }, [visible, translateY]);

  const animStyle = useAnimatedStyle(() => ({
    transform: [{ translateY: translateY.value }],
  }));

  if (!visible) return null;

  return (
    <Animated.View style={[styles.wrapper, animStyle]} testID="offline-banner">
      <View style={styles.banner}>
        <Text style={styles.text} accessibilityRole="alert">
          Pas de connexion internet
        </Text>
      </View>
    </Animated.View>
  );
}

// ── Styles ────────────────────────────────────────────────────────────────────

const styles = StyleSheet.create({
  wrapper: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    zIndex: 999,
  },
  banner: {
    backgroundColor: colors.surface[800],
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.md,
    alignItems: 'center',
  },
  text: {
    color: '#ffffff',
    fontSize: typography.fontSize.sm,
    fontFamily: typography.fontFamily.bodyMedium,
    fontWeight: typography.fontWeight.medium,
  },
});
