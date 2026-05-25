import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, spacing, typography } from '@/theme';

interface DividerProps {
  label?: string;
}

export function Divider({ label }: DividerProps) {
  if (!label) {
    return <View style={styles.line} />;
  }

  return (
    <View style={styles.row}>
      <View style={styles.line} />
      <Text style={styles.label}>{label}</Text>
      <View style={styles.line} />
    </View>
  );
}

const styles = StyleSheet.create({
  line: {
    flex: 1,
    height: StyleSheet.hairlineWidth,
    backgroundColor: colors.surface[200],
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    columnGap: spacing.sm,
  },
  label: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[400],
  },
});
