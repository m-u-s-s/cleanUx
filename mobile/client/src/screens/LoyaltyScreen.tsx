import React from 'react';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { Screen, KPICard, Badge, Button, Skeleton, EmptyState } from '@/ui';
import { useLoyaltyAccount, useLoyaltyRewards } from '@/loyalty';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';

export function LoyaltyScreen() {
  const { data: account, isLoading: loadingAccount } = useLoyaltyAccount();
  const { data: rewards, isLoading: loadingRewards, refetch: refetchRewards, isRefetching: isRefetchingRewards } = useLoyaltyRewards();
  const themeColors = useThemeColors();

  return (
    <Screen scroll>
      <Text style={styles.title}>Programme fidélité</Text>
      {loadingAccount ? (
        <Skeleton width="100%" height={120} />
      ) : account ? (
        <View style={[styles.tierCard, { backgroundColor: themeColors.card }]}>
          <Badge label={account.tier.toUpperCase()} variant="brand" />
          <View style={styles.kpiRow}>
            <KPICard title="Points" value={account.redeemable_points} hint="Échangeables" tone="success" />
            <KPICard title="Ce mois" value={account.period_points} />
          </View>
        </View>
      ) : null}
      <Text style={styles.sectionTitle}>Récompenses disponibles</Text>
      {loadingRewards ? (
        <Skeleton width="100%" height={80} />
      ) : (
        <FlatList
          data={rewards ?? []}
          scrollEnabled={false}
          keyExtractor={item => String(item.id)}
          onRefresh={refetchRewards}
          refreshing={isRefetchingRewards}
          ListEmptyComponent={<EmptyState title="Aucune récompense" message="Les récompenses disponibles apparaîtront ici." icon="gift-outline" />}
          renderItem={({ item }) => (
            <View style={styles.rewardCard}>
              <View style={styles.rewardInfo}>
                <Text style={styles.rewardName}>{item.name}</Text>
                <Text style={styles.rewardCost}>{item.points_cost} pts</Text>
              </View>
              <Button
                label="Échanger"
                size="sm"
                onPress={() => {}}
                disabled={(account?.redeemable_points ?? 0) < item.points_cost}
              />
            </View>
          )}
        />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginTop: spacing.md,
    marginBottom: spacing.md,
  },
  tierCard: {
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.soft,
    marginBottom: spacing.lg,
  },
  kpiRow: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.sm },
  sectionTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[800],
    marginBottom: spacing.sm,
  },
  rewardCard: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.surface[200],
  },
  rewardInfo: { flex: 1 },
  rewardName: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: colors.surface[900],
  },
  rewardCost: { fontSize: typography.fontSize.xs, color: colors.brand[600] },
});
