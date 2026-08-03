import React from 'react';
import { View, Text, Linking, Alert, StyleSheet } from 'react-native';
import { useMutation } from '@tanstack/react-query';
import { Screen, Button, Badge } from '@/ui';
import { useStripeConnectStatus } from '@/earnings';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

export function StripeOnboardingScreen() {
  const styles = stylesFor(useThemeColors());

  const { data: status, isLoading } = useStripeConnectStatus();
  const onboard = useMutation({
    mutationFn: async () => (await apiClient.post('/provider/stripe-connect/onboard')).data,
  });

  const handleOnboard = async () => {
    try {
      const res = await onboard.mutateAsync();
      if (res.url) await Linking.openURL(res.url);
    } catch (e: any) {
      Alert.alert('Erreur', e.message);
    }
  };

  return (
    <Screen>
      <Text style={styles.title}>Stripe Connect</Text>
      {status?.onboarded ? (
        <View style={styles.statusCard}>
          <Badge label="Onboarded" variant="success" />
          <Text style={styles.statusText}>
            Charges: {status.charges_enabled ? '✓' : '✗'} | Payouts: {status.payouts_enabled ? '✓' : '✗'}
          </Text>
          {status.requirements.length > 0 && (
            <Text style={styles.requirementsText}>{status.requirements.length} info(s) requise(s)</Text>
          )}
        </View>
      ) : (
        <View>
          <Text style={styles.info}>
            Connectez votre compte Stripe pour recevoir des paiements de vos missions.
          </Text>
          <Button
            label="Configurer Stripe"
            onPress={handleOnboard}
            fullWidth
            loading={onboard.isPending}
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
  statusCard: {
    backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.soft,
    gap: spacing.sm,
  },
  statusText: { fontSize: typography.fontSize.sm, color: t.textSecondary },
  requirementsText: { fontSize: typography.fontSize.xs, color: colors.warning[600] },
  info: { fontSize: typography.fontSize.sm, color: t.textSecondary, marginBottom: spacing.lg },
});
