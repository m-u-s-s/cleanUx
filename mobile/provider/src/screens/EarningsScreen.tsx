import React, { useCallback } from 'react';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { Screen, KPICard, Badge, Button, Skeleton, Divider, ErrorState } from '@/ui';
import { useWalletBalance, useWalletTransactions, useStripeConnectStatus } from '@/earnings';
import { ApiError } from '@/api';
import { useNavigation } from '@react-navigation/native';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { ThemeTokens } from '@/theme/useThemeColors';

/**
 * The wallet endpoints abort 403 when the account has no provider_profiles row (an `employe`
 * user can exist without one). Saying so beats a generic failure — and beats what this screen
 * used to do, which was render "0.00 EUR" and "Aucune transaction" as if the data were real.
 */
function walletErrorMessage(error: unknown): string {
  if (error instanceof ApiError && error.status === 403) {
    return "Ce compte n'a pas de profil prestataire, aucun portefeuille n'y est rattaché.";
  }
  return 'Impossible de charger vos revenus.';
}

export function EarningsScreen() {
  const styles = stylesFor(useThemeColors());

  const balanceQuery = useWalletBalance();
  const txQuery = useWalletTransactions();
  const { data: stripe } = useStripeConnectStatus();
  const navigation = useNavigation<any>();

  const { data: balance, isLoading: loadingBalance } = balanceQuery;
  const { data: transactions, isLoading: loadingTx } = txQuery;

  const retryWallet = useCallback(() => {
    void balanceQuery.refetch();
    void txQuery.refetch();
  }, [balanceQuery, txQuery]);

  // Both wallet queries sit behind the same backend guard, so when both fail the whole wallet
  // is unavailable and there is nothing truthful left to show.
  if (balanceQuery.isError && txQuery.isError) {
    return (
      <Screen testID="earnings-screen">
        <Text style={styles.title}>Revenus</Text>
        <ErrorState message={walletErrorMessage(balanceQuery.error)} onRetry={retryWallet} />
      </Screen>
    );
  }

  return (
    <Screen scroll testID="earnings-screen">
      <Text style={styles.title}>Revenus</Text>

      {/* Only prompt for onboarding once we actually know it is missing — a failed status
          query left `stripe` undefined and showed the banner to onboarded providers. */}
      {stripe && !stripe.onboarded && (
        <View style={styles.onboardBanner}>
          <Text style={styles.onboardText}>Configurez Stripe pour recevoir vos paiements</Text>
          <Button label="Configurer" onPress={() => navigation.navigate('StripeOnboarding')} size="sm" />
        </View>
      )}

      {loadingBalance ? (
        <Skeleton width="100%" height={80} />
      ) : balanceQuery.isError ? (
        <ErrorState
          compact
          message="Solde indisponible."
          onRetry={() => void balanceQuery.refetch()}
        />
      ) : balance ? (
        <View style={styles.balanceRow}>
          <KPICard title="Disponible" value={`${balance.available.toFixed(2)} ${balance.currency}`} tone="success" />
          <KPICard title="En attente" value={`${balance.pending.toFixed(2)} ${balance.currency}`} />
        </View>
      ) : null}

      <Text style={styles.sectionTitle}>Transactions récentes</Text>
      {loadingTx ? (
        <Skeleton width="100%" height={200} />
      ) : txQuery.isError ? (
        <ErrorState
          compact
          message="Impossible de charger vos transactions."
          onRetry={() => void txQuery.refetch()}
        />
      ) : (
        <FlatList
          data={transactions ?? []}
          scrollEnabled={false}
          keyExtractor={(i) => String(i.id)}
          renderItem={({ item }) => (
            <View style={styles.txRow}>
              <View>
                <Text style={styles.txDesc}>{item.description}</Text>
                <Text style={styles.txDate}>{new Date(item.created_at).toLocaleDateString()}</Text>
              </View>
              <Text style={[styles.txAmount, item.amount >= 0 ? styles.positive : styles.negative]}>
                {item.amount >= 0 ? '+' : ''}{item.amount.toFixed(2)} {item.currency}
              </Text>
            </View>
          )}
          ItemSeparatorComponent={() => <Divider />}
          ListEmptyComponent={<Text style={styles.empty}>Aucune transaction</Text>}
        />
      )}
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginBottom: spacing.md,
  },
  onboardBanner: {
    backgroundColor: t.tint.warning,
    borderRadius: radius.md,
    padding: spacing.md,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.md,
  },
  onboardText: {
    fontSize: typography.fontSize.sm,
    color: colors.warning[700],
    flex: 1,
    marginRight: spacing.sm,
  },
  balanceRow: { flexDirection: 'row', gap: spacing.sm, marginBottom: spacing.lg },
  sectionTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    marginBottom: spacing.sm,
  },
  txRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.sm,
  },
  txDesc: { fontSize: typography.fontSize.sm, color: t.text },
  txDate: { fontSize: typography.fontSize.xs, color: t.textMuted, marginTop: 2 },
  txAmount: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.semibold },
  positive: { color: colors.success[600] },
  negative: { color: colors.danger[600] },
  empty: { color: t.textMuted, textAlign: 'center', marginTop: spacing.lg },
});
