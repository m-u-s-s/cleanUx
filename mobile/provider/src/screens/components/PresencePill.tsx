import React from 'react';
import { Text, TouchableOpacity, StyleSheet } from 'react-native';
import { PulseDot } from '@/ui';
import { usePresence } from '@/presence';
import type { PresenceStatus } from '@/presence/types';
import { colors, spacing, typography, radius, shadows } from '@/theme';

const labels: Record<PresenceStatus, string> = {
  online: 'En ligne',
  busy: 'Occupé',
  on_break: 'En pause',
  offline: 'Hors ligne',
};

const variants: Record<PresenceStatus, 'success' | 'urgent' | 'primary'> = {
  online: 'success',
  busy: 'urgent',
  on_break: 'primary',
  offline: 'primary',
};

/**
 * Affichage seul : la pilule n'écrit jamais le statut. Le seul chemin d'écriture reste
 * PresenceToggle, dans le sheet — un point d'écriture unique, donc pas d'état divergent.
 */
export function PresencePill({ onPress }: { onPress: () => void }) {
  const { status } = usePresence();

  return (
    <TouchableOpacity style={styles.pill} onPress={onPress} testID="presence-pill" accessibilityRole="button">
      {status !== 'offline' && <PulseDot variant={variants[status]} />}
      <Text style={styles.label}>{labels[status]}</Text>
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
