import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { PulseDot, Badge } from '@/ui';
import { usePresence } from '@/presence';
import type { PresenceStatus } from '@/presence/types';
import { colors, spacing, typography, radius } from '@/theme';

const statusLabels: Record<PresenceStatus, string> = {
  online: 'En ligne',
  busy: 'Occupé',
  on_break: 'En pause',
  offline: 'Hors ligne',
};

const statusVariants: Record<PresenceStatus, 'success' | 'urgent' | 'primary'> = {
  online: 'success',
  busy: 'urgent',
  on_break: 'primary',
  offline: 'primary',
};

export function PresenceToggle() {
  const { status, goOnline, setPresenceStatus } = usePresence();
  const statuses: PresenceStatus[] = ['online', 'busy', 'on_break', 'offline'];

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        {status !== 'offline' && <PulseDot variant={statusVariants[status]} />}
        <Text style={styles.label}>{statusLabels[status]}</Text>
      </View>
      <View style={styles.buttons}>
        {statuses.map(s => (
          <TouchableOpacity
            key={s}
            style={[styles.btn, status === s && styles.btnActive]}
            onPress={() =>
              s === 'online' && status === 'offline' ? goOnline() : setPresenceStatus(s)
            }
          >
            <Text style={[styles.btnText, status === s && styles.btnTextActive]}>
              {statusLabels[s]}
            </Text>
          </TouchableOpacity>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: spacing.md,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    marginBottom: spacing.sm,
  },
  label: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  buttons: {
    flexDirection: 'row',
    gap: spacing.xs,
  },
  btn: {
    flex: 1,
    paddingVertical: spacing.sm,
    borderRadius: radius.sm,
    backgroundColor: colors.surface[100],
    alignItems: 'center',
  },
  btnActive: {
    backgroundColor: colors.brand[500],
  },
  btnText: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[600],
  },
  btnTextActive: {
    color: '#fff',
    fontWeight: typography.fontWeight.semibold,
  },
});
