import React from 'react';
import { Text, TouchableOpacity, StyleSheet } from 'react-native';
import { PulseDot } from '@/ui';
import { usePresence, PRESENCE_LABELS, PRESENCE_VARIANTS } from '@/presence';
import { colors, spacing, typography, radius, shadows } from '@/theme';

/**
 * Affichage seul : la pilule n'écrit jamais le statut. Le seul chemin d'écriture reste
 * PresenceToggle, dans le sheet — un point d'écriture unique, donc pas d'état divergent.
 */
export function PresencePill({ onPress }: { onPress: () => void }) {
  const { status } = usePresence();

  return (
    <TouchableOpacity
      style={styles.pill}
      onPress={onPress}
      testID="presence-pill"
      accessibilityRole="button"
      accessibilityLabel={`Statut de présence : ${PRESENCE_LABELS[status]}. Toucher pour ouvrir les actions.`}
    >
      {status !== 'offline' && <PulseDot variant={PRESENCE_VARIANTS[status]} />}
      <Text style={styles.label}>{PRESENCE_LABELS[status]}</Text>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    alignSelf: 'center',
    backgroundColor: '#fff',
    borderRadius: radius.pill,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    ...shadows.xs,
  },
  label: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.semibold, color: colors.surface[900] },
});
