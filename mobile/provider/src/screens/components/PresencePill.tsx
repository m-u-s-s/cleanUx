import React from 'react';
import { Text, TouchableOpacity, StyleSheet } from 'react-native';
import { PulseDot } from '@/ui';
import { usePresence, PRESENCE_LABELS, PRESENCE_VARIANTS } from '@/presence';
import { colors, spacing, typography, radius, shadows } from '@/theme';

/**
 * Affichage seul : la pilule n'écrit jamais le statut, le seul chemin d'écriture reste
 * PresenceToggle dans le sheet.
 *
 * Attention, un point d'écriture unique ne suffit PAS à garantir un affichage cohérent : ce
 * commentaire affirmait le contraire alors que `usePresence()` tenait un `useState` par appel,
 * si bien que la pilule et le toggle lisaient deux états indépendants et que la pilule ne
 * bougeait jamais. C'est la LECTURE qui doit être partagée — elle l'est désormais via l'entrée
 * de cache React Query `PRESENCE_QUERY_KEY` (cf. src/presence/hooks.ts).
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
