import React from 'react';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { Screen, KPICard, Badge, Button, Skeleton, Divider } from '@/ui';
import { useWalletBalance, useWalletTransactions, useStripeConnectStatus } from '@/earnings';
import { useNavigation } from '@react-navigation/native';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';

export function EarningsScreen() {
  const { data: balance, isLoading: loadingBalance } = useWalletBalance();
  const { data: transactions, isLoading: loadingTx } = useWalletTransactions();
  const { data: stripe } = useStripeConnectStatus();
  const navigation = useNavigation<any>();

  return (
    <Screen scroll testID="earnings-screen">
      <Text style={styles.title}>Revenus</Text>

      {!stripe?.onboarded && (
        <View style={styles.onboardBanner}>
          <Text style={styles.onboardText}>Configurez Stripe pour recevoir vos paiements</Text>
          <Button label="Configurer" onPress={() => navigation.navigate('StripeOnboarding')} size="sm" />
        </View>
      )}

      {loadingBalance ? (
        <Skeleton width="100%" height={80} />
      ) : balance ? (
        <View style={styles.balanceRow}>
          <KPICard title="Disponible" value={`${balance.available.toFixed(2)} ${balance.currency}`} tone="success" />
          <KPICard title="En attente" value={`${balance.pending.toFixed(2)} ${balance.currency}`} />
        </View>
      ) : null}

      <Text style={styles.sectionTitle}>Transactions récentes</Text>
      {loadingTx ? (
        <Skeleton width="100%" height={200} />
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

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginBottom: spacing.md,
  },
  onboardBanner: {
    backgroundColor: colors.warning[50],
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
    color: colors.surface[800],
    marginBottom: spacing.sm,
  },
  txRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.sm,
  },
  txDesc: { fontSize: typography.fontSize.sm, color: colors.surface[900] },
  txDate: { fontSize: typography.fontSize.xs, color: colors.surface[400], marginTop: 2 },
  txAmount: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.semibold },
  positive: { color: colors.success[600] },
  negative: { color: colors.danger[600] },
  empty: { color: colors.surface[400], textAlign: 'center', marginTop: spacing.lg },
});
