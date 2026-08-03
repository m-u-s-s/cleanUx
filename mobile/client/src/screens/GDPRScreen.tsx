import React from 'react';
import { Text, Alert, StyleSheet } from 'react-native';
import { useMutation } from '@tanstack/react-query';
import { Screen, Button, Divider } from '@/ui';
import { apiClient } from '@/api';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

export function GDPRScreen() {
  const styles = stylesFor(useThemeColors());

  const exportData = useMutation({
    mutationFn: () => apiClient.post('/client/gdpr/requests', { type: 'export' }),
  });
  const eraseData = useMutation({
    mutationFn: () => apiClient.post('/client/gdpr/requests', { type: 'erasure' }),
  });

  const handleExport = () => {
    exportData.mutate(undefined, {
      onSuccess: () => Alert.alert('Demande envoyée', 'Vous recevrez un email avec vos données.'),
    });
  };

  const handleErase = () => {
    Alert.alert('Suppression des données', 'Cette action est irréversible. Continuer ?', [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Supprimer',
        style: 'destructive',
        onPress: () =>
          eraseData.mutate(undefined, {
            onSuccess: () =>
              Alert.alert('Demande envoyée', 'Votre demande de suppression sera traitée sous 30 jours.'),
          }),
      },
    ]);
  };

  return (
    <Screen>
      <Text style={styles.title}>Mes données (RGPD)</Text>
      <Text style={styles.info}>
        Conformément au RGPD, vous pouvez exporter ou supprimer vos données personnelles.
      </Text>
      <Button
        label="Exporter mes données"
        onPress={handleExport}
        variant="secondary"
        fullWidth
        loading={exportData.isPending}
      />
      <Divider label="ou" />
      <Button
        label="Supprimer mon compte"
        onPress={handleErase}
        variant="danger"
        fullWidth
        loading={eraseData.isPending}
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginTop: spacing.md,
    marginBottom: spacing.sm,
  },
  info: { fontSize: typography.fontSize.sm, color: t.textSecondary, marginBottom: spacing.lg },
});
