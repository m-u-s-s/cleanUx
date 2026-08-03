import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';

interface DividerProps {
  label?: string;
}

export function Divider({ label }: DividerProps) {
  const theme = useThemeColors();

  if (!label) {
    return <View style={[styles.line, { backgroundColor: theme.border }]} />;
  }

  return (
    <View style={styles.row}>
      <View style={[styles.line, { backgroundColor: theme.border }]} />
      <Text style={[styles.label, { color: theme.textMuted }]}>{label}</Text>
      <View style={[styles.line, { backgroundColor: theme.border }]} />
    </View>
  );
}

const styles = StyleSheet.create({
  line: {
    flex: 1,
    height: StyleSheet.hairlineWidth,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    columnGap: spacing.sm,
  },
  label: {
    fontSize: typography.fontSize.xs,
  },
});
