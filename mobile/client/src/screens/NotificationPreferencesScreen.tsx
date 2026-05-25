import React, { useState } from 'react';
import { View, Text, Switch, StyleSheet, Alert } from 'react-native';
import { Screen, Button, Divider } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography } from '@/theme';

const CATEGORIES = [
  { key: 'transactional', label: 'Réservations & missions', description: 'Confirmations, rappels, statuts' },
  { key: 'chat', label: 'Messages', description: 'Nouveaux messages de prestataires' },
  { key: 'marketing', label: 'Promotions', description: 'Offres spéciales et réductions' },
  { key: 'reminder', label: 'Rappels', description: 'Missions à venir, évaluations en attente' },
  { key: 'system', label: 'Système', description: 'Mises à jour, maintenance' },
];

const CHANNELS = [
  { key: 'push', label: 'Push' },
  { key: 'email', label: 'Email' },
  { key: 'sms', label: 'SMS' },
];

export function NotificationPreferencesScreen() {
  const [prefs, setPrefs] = useState<Record<string, Record<string, boolean>>>(() => {
    const initial: Record<string, Record<string, boolean>> = {};
    CATEGORIES.forEach(c => {
      initial[c.key] = {} as Record<string, boolean>;
      CHANNELS.forEach(ch => {
        initial[c.key]![ch.key] = true;
      });
    });
    return initial;
  });
  const [saving, setSaving] = useState(false);

  const toggle = (category: string, channel: string) => {
    setPrefs(prev => {
      const catPrefs = prev[category] ?? {};
      return {
        ...prev,
        [category]: { ...catPrefs, [channel]: !catPrefs[channel] },
      };
    });
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      await apiClient.put('/notifications/preferences', { preferences: prefs });
      Alert.alert('Sauvegardé', 'Vos préférences ont été mises à jour.');
    } catch {
      Alert.alert('Erreur', 'Impossible de sauvegarder.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Screen scroll>
      <Text style={styles.title}>Préférences de notifications</Text>
      {CATEGORIES.map(cat => (
        <View key={cat.key} style={styles.category}>
          <Text style={styles.catLabel}>{cat.label}</Text>
          <Text style={styles.catDesc}>{cat.description}</Text>
          <View style={styles.channels}>
            {CHANNELS.map(ch => (
              <View key={ch.key} style={styles.channelRow}>
                <Text style={styles.channelLabel}>{ch.label}</Text>
                <Switch
                  value={prefs[cat.key]?.[ch.key] !== false}
                  onValueChange={() => toggle(cat.key, ch.key)}
                  trackColor={{ true: colors.brand[500] }}
                />
              </View>
            ))}
          </View>
          <Divider />
        </View>
      ))}
      <Button label="Sauvegarder" onPress={handleSave} fullWidth loading={saving} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginBottom: spacing.lg,
    marginTop: spacing.md,
  },
  category: { marginBottom: spacing.sm },
  catLabel: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  catDesc: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[500],
    marginBottom: spacing.sm,
  },
  channels: { gap: spacing.xs },
  channelRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.xs,
  },
  channelLabel: { fontSize: typography.fontSize.sm, color: colors.surface[700] },
});
