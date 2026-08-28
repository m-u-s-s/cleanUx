import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Screen, Button, Badge } from '@/ui';
import { libelleStatutKyc } from '@brio/shared';
import { apiClient } from '@/api';
import {spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

export function KYCScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { data: status } = useQuery({
    queryKey: ['kyc'],
    queryFn: async () => (await apiClient.get('/provider/kyc/status')).data,
  });
  const start = useMutation({
    mutationFn: () => apiClient.post('/provider/kyc/start'),
  });

  return (
    <Screen>
      <Text style={styles.title}>{tr('k_y_c.verification_d_identite')}</Text>
      {status?.verified ? (
        <View style={styles.card}>
          <Badge label={tr('k_y_c.verifie')} variant="success" />
          <Text style={styles.info}>{tr('k_y_c.votre_identite_est_confirmee')}</Text>
        </View>
      ) : (
        <View style={styles.card}>
          {/* Le libellé, jamais la valeur brute : l'écran affichait « clear » en toutes lettres. */}
          <Badge label={libelleStatutKyc(status?.status)} variant="warning" />
          <Text style={styles.info}>
            {tr('k_y_c.completez_la_verification_pour_recevoir')}
          </Text>
          <Button
            label={tr('k_y_c.lancer_la_verification')}
            onPress={() => start.mutate()}
            loading={start.isPending}
            fullWidth
          />
        </View>
      )}
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginTop: spacing.md,
    marginBottom: spacing.lg,
  },
  card: {
    backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.lg,
    ...shadows.soft,
    gap: spacing.md,
  },
  info: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
  },
});
