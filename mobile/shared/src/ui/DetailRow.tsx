import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, spacing, typography } from '@/theme';

export type DetailRowProps = {
  label: string;
  value: string;
  testID?: string;
};

/**
 * A label/value row used inside detail cards (booking, invoice, dispute…).
 * Accessible: exposed as a single `text` element reading "label: value".
 */
export function DetailRow({ label, value, testID }: DetailRowProps) {
  return (
    <View
      style={styles.row}
      accessible
      accessibilityRole="text"
      accessibilityLabel={`${label}: ${value}`}
      testID={testID}
    >
      <Text style={styles.label}>{label}</Text>
      <Text style={styles.value}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: spacing.sm,
  },
  label: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
  },
  value: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
    flex: 1,
    textAlign: 'right',
    marginLeft: spacing.sm,
  },
});
