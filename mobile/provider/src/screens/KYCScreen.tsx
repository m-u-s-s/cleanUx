import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Screen, Button, Badge } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius, shadows } from '@/theme';

export function KYCScreen() {
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

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginTop: spacing.md,
    marginBottom: spacing.lg,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: spacing.lg,
    ...shadows.soft,
    gap: spacing.md,
  },
  info: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
  },
});
