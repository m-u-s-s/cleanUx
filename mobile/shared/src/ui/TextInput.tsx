import React, { forwardRef, useEffect, useState } from 'react';
import {
  View,
  TextInput as RNTextInput,
  TextInputProps as RNInputProps,
  Text,
  StyleSheet,
} from 'react-native';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withTiming,
  withSequence,
} from 'react-native-reanimated';
import { colors, radius, spacing, typography, animation } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { useDuree } from './a11y';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface TextInputProps extends Omit<RNInputProps, 'style'> {
  label: string;
  error?: string;
}

export const TextInput = forwardRef<RNTextInput, TextInputProps>(
  ({ label, error, value, onFocus, onBlur, ...props }, ref) => {
    const styles = stylesFor(useThemeColors());
    const [focused, setFocused] = useState(false);
    const hasValue = !!value && value.length > 0;
    const isFloating = focused || hasValue;

    const labelY = useSharedValue(isFloating ? -10 : 12);
    const labelScale = useSharedValue(isFloating ? 0.8 : 1);
    const shakeX = useSharedValue(0);
    const borderWidth = useSharedValue(1);

    // Zero quand l'utilisateur a reduit les mouvements : l'etiquette et la bordure
    // se placent d'un coup, et le champ en erreur ne se secoue plus du tout.
    const bref = useDuree(animation.duration.fast);
    const secousse = useDuree(50);

    useEffect(() => {
      labelY.value = withTiming(isFloating ? -10 : 12, { duration: bref });
      labelScale.value = withTiming(isFloating ? 0.8 : 1, { duration: bref });
    }, [isFloating, bref]);

    useEffect(() => {
      borderWidth.value = withTiming(focused ? 2 : 1, { duration: bref });
    }, [focused, bref]);

    useEffect(() => {
      if (error && secousse > 0) {
        shakeX.value = withSequence(
          withTiming(5, { duration: secousse }),
          withTiming(-5, { duration: secousse }),
          withTiming(5, { duration: secousse }),
          withTiming(-5, { duration: secousse }),
          withTiming(0, { duration: secousse }),
        );
      }
    }, [error, secousse]);

    const labelStyle = useAnimatedStyle(() => ({
      transform: [{ translateY: labelY.value }, { scale: labelScale.value }],
    }));

    const containerShake = useAnimatedStyle(() => ({
      transform: [{ translateX: shakeX.value }],
    }));

    const borderStyle = useAnimatedStyle(() => ({
      borderWidth: borderWidth.value,
    }));

    return (
      <Animated.View style={[styles.container, containerShake]}>
        <Animated.View
          style={[
            styles.inputWrapper,
            borderStyle,
            focused && styles.focused,
            error ? styles.errorBorder : null,
          ]}
        >
          <Animated.Text
            style={[
              styles.label,
              labelStyle,
              focused && styles.labelFocused,
              error ? styles.labelError : null,
            ]}
          >
            {label}
          </Animated.Text>
          <RNTextInput
            ref={ref}
            {...props}
            value={value}
            style={styles.input}
            placeholderTextColor={colors.surface[400]}
            onFocus={e => {
              setFocused(true);
              onFocus?.(e);
            }}
            onBlur={e => {
              setFocused(false);
              onBlur?.(e);
            }}
          />
        </Animated.View>
        {error ? <Text style={styles.error}>{error}</Text> : null}
      </Animated.View>
    );
  },
);

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { gap: 2 },
  inputWrapper: {
    borderWidth: 1,
    borderColor: t.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.sm + 4,
    paddingTop: spacing.lg,
    paddingBottom: spacing.sm,
    backgroundColor: t.inputBg,
    position: 'relative',
  },
  label: {
    position: 'absolute',
    left: spacing.sm + 4,
    top: 0,
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    backgroundColor: 'transparent',
  },
  labelFocused: { color: colors.brand[500] },
  labelError: { color: colors.danger[500] },
  input: {
    fontSize: typography.fontSize.base,
    color: t.text,
    padding: 0,
    minHeight: 24,
  },
  focused: { borderColor: colors.brand[500] },
  errorBorder: { borderColor: colors.danger[500] },
  error: {
    fontSize: typography.fontSize.xs,
    color: colors.danger[500],
    marginLeft: spacing.xs,
  },
});
