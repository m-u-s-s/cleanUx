import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Screen, Button, Badge } from '@/ui';
import { apiClient } from '@/api';
import {spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

export function KYCScreen() {
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
      <Text style={styles.title}>Vérification d'identité</Text>
      {status?.verified ? (
        <View style={styles.card}>
          <Badge label="Vérifié" variant="success" />
          <Text style={styles.info}>Votre identité est confirmée.</Text>
        </View>
      ) : (
        <View style={styles.card}>
          <Badge label={status?.status ?? 'Non vérifié'} variant="warning" />
          <Text style={styles.info}>
            Complétez la vérification pour recevoir des missions.
          </Text>
          <Button
            label="Lancer la vérification"
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
