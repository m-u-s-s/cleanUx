import React from 'react';
import { View, FlatList, Text, StyleSheet, Alert } from 'react-native';
import { Screen, KPICard, Badge, Button, Skeleton, EmptyState } from '@/ui';
import { useLoyaltyAccount, useLoyaltyRewards, useRedeemReward } from '@/loyalty';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { ThemeTokens } from '@/theme/useThemeColors';

export function LoyaltyScreen() {
  const styles = stylesFor(useThemeColors());

  const { data: account, isLoading: loadingAccount } = useLoyaltyAccount();
  const { data: rewards, isLoading: loadingRewards, refetch: refetchRewards, isRefetching: isRefetchingRewards } = useLoyaltyRewards();
  const redeem = useRedeemReward();
  const themeColors = useThemeColors();

  const handleRedeem = (rewardId: number, rewardName: string) => {
    Alert.alert('Échanger cette récompense ?', rewardName, [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Échanger',
        onPress: () =>
          redeem.mutate(rewardId, {
            onSuccess: (result) =>
              Alert.alert(
                'Échange confirmé 🎉',
                result.voucher_code
                  ? `Votre code : ${result.voucher_code}`
                  : 'Votre récompense est en cours de traitement.',
              ),
            onError: () => Alert.alert('Échec', "L'échange n'a pas pu être effectué. Réessayez."),
          }),
      },
    ]);
  };

  return (
    <Screen scroll>
      <Text style={styles.title}>Programme fidélité</Text>
      {loadingAccount ? (
        <Skeleton width="100%" height={120} />
      ) : account ? (
        <View style={[styles.tierCard, { backgroundColor: themeColors.card }]}>
          {/* Pas encore de palier — l'état normal d'un compte neuf — se dit en toutes lettres. */}
          <Badge label={account.tier?.name ?? 'Aucun palier'} variant="brand" />
          <View style={styles.kpiRow}>
            <KPICard
              title="Points"
              value={account.redeemable_points ?? '—'}
              hint="Échangeables"
              tone="success"
            />
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
                onPress={() => handleRedeem(item.id, item.name)}
                disabled={(account?.redeemable_points ?? 0) < item.points_cost || redeem.isPending}
              />
            </View>
          )}
        />
      )}
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: t.text,
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
    color: t.text,
    marginBottom: spacing.sm,
  },
  rewardCard: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: t.border,
  },
  rewardInfo: { flex: 1 },
  rewardName: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: t.text,
  },
  rewardCost: { fontSize: typography.fontSize.xs, color: t.brandText },
});
