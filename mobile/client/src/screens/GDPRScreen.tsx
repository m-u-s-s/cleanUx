import React from 'react';
import { Text, Alert, StyleSheet } from 'react-native';
import { useMutation } from '@tanstack/react-query';
import { Screen, Button, Divider } from '@/ui';
import { apiClient } from '@/api';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

export function GDPRScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const exportData = useMutation({
    mutationFn: () => apiClient.post('/client/gdpr/requests', { type: 'export' }),
  });
  const eraseData = useMutation({
    mutationFn: () => apiClient.post('/client/gdpr/requests', { type: 'erasure' }),
  });

  const handleExport = () => {
    exportData.mutate(undefined, {
      onSuccess: () => Alert.alert(tr('g_d_p_r.demande_envoyee'), tr('g_d_p_r.vous_recevrez_un_email_avec')),
    });
  };

  const handleErase = () => {
    Alert.alert(tr('g_d_p_r.suppression_des_donnees'), tr('g_d_p_r.cette_action_est_irreversible_continuer'), [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Supprimer',
        style: 'destructive',
        onPress: () =>
          eraseData.mutate(undefined, {
            onSuccess: () =>
              Alert.alert(tr('g_d_p_r.demande_envoyee'), tr('g_d_p_r.votre_demande_de_suppression_sera')),
          }),
      },
    ]);
  };

  return (
    <Screen>
      <Text style={styles.title}>{tr('g_d_p_r.mes_donnees_rgpd')}</Text>
      <Text style={styles.info}>
        {tr('g_d_p_r.conformement_au_rgpd_vous_pouvez')}
      </Text>
      <Button
        label={tr('g_d_p_r.exporter_mes_donnees')}
        onPress={handleExport}
        variant="secondary"
        fullWidth
        loading={exportData.isPending}
      />
      <Divider label="ou" />
      <Button
        label={tr('g_d_p_r.supprimer_mon_compte')}
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
