import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';

export type DetailRowProps = {
  label: string;
  value: string;
  testID?: string;
};

/**
 * Une ligne libellé / valeur, dans les fiches de détail (réservation, facture, litige…).
 *
 * Accessible : exposée comme un seul élément `text` qui se lit « libellé : valeur ».
 *
 * Les couleurs viennent du thème et non de la feuille de style : `StyleSheet.create` est évalué
 * une fois au chargement du module et ne peut pas savoir s'il fait sombre.
 */
export function DetailRow({ label, value, testID }: DetailRowProps) {
  const theme = useThemeColors();

  return (
    <View
      style={styles.row}
      accessible
      accessibilityRole="text"
      accessibilityLabel={`${label}: ${value}`}
      testID={testID}
    >
      <Text style={[styles.label, { color: theme.textSecondary }]}>{label}</Text>
      <Text style={[styles.value, { color: theme.text }]}>{value}</Text>
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
  },
  value: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    flex: 1,
    textAlign: 'right',
    marginLeft: spacing.sm,
  },
});
