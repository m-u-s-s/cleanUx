import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput as RNTextInput,
  TextInputProps as RNInputProps,
  StyleSheet,
} from 'react-native';
import { colors, radius, spacing, typography } from '@/theme';

interface TextInputProps extends Omit<RNInputProps, 'style'> {
  label: string;
  error?: string;
}

export function TextInput({ label, error, ...props }: TextInputProps) {
  const [focused, setFocused] = useState(false);

  return (
    <View style={styles.container}>
      <Text style={styles.label}>{label}</Text>
      <RNTextInput
        {...props}
        style={[
          styles.input,
          focused && styles.focused,
          error ? styles.errorBorder : null,
        ]}
        placeholderTextColor={colors.surface[400]}
        onFocus={e => {
          setFocused(true);
          props.onFocus?.(e);
        }}
        onBlur={e => {
          setFocused(false);
          props.onBlur?.(e);
        }}
      />
      {error ? <Text style={styles.error}>{error}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    rowGap: spacing.xs,
  },
  label: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: colors.surface[700],
  },
  input: {
    borderWidth: 1,
    borderColor: colors.surface[300],
    borderRadius: radius.md,
    paddingHorizontal: spacing.sm + 4,
    paddingVertical: spacing.sm + 2,
    fontSize: typography.fontSize.base,
    color: colors.surface[900],
    backgroundColor: colors.surface[50],
  },
  focused: {
    borderColor: colors.brand[500],
    borderWidth: 2,
  },
  errorBorder: {
    borderColor: colors.danger[500],
  },
  error: {
    fontSize: typography.fontSize.xs,
    color: colors.danger[500],
  },
});
