import React from 'react';
import { FlatList, View, Text, StyleSheet, Pressable } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, Skeleton, EmptyState, ErrorState } from '@/ui';
import { apiClient } from '@/api';
import {spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { useTraduction } from '@/i18n';

interface Dispute {
  id: number;
  status: string;
  reason?: string;
  created_at: string;
}

export function ProviderDisputesScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const { data, isLoading, isError, refetch, isRefetching } = useQuery<Dispute[]>({
    queryKey: ['provider', 'disputes'],
    queryFn: async () => (await apiClient.get('/provider/disputes')).data.data ?? [],
  });

  if (isError) return <Screen><ErrorState message="Impossible de charger vos litiges." onRetry={refetch} /></Screen>;

  return (
    <Screen>
      <Text style={styles.title}>{tr('provider_disputes.litiges')}</Text>
      {isLoading ? (
        <Skeleton width="100%" height={80} />
      ) : (
        <FlatList
          data={data ?? []}
          keyExtractor={item => String(item.id)}
          renderItem={({ item }) => (
            <Pressable
              style={styles.card}
              accessibilityRole="button"
              accessibilityLabel={`Ouvrir le litige ${item.id}`}
              onPress={() => navigation.navigate('ProviderDisputeDetail', { disputeId: item.id })}
            >
              <Text style={styles.cardTitle}>Litige #{item.id}</Text>
              <Badge
                label={item.status}
                variant={item.status === 'resolved' ? 'success' : 'warning'}
              />
            </Pressable>
          )}
          onRefresh={refetch}
          refreshing={isRefetching}
          ListEmptyComponent={<EmptyState title={tr('provider_disputes.aucun_litige')} message="Vous n'avez aucun litige en cours." />}
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
  card: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: spacing.md,
    backgroundColor: t.card,
    borderRadius: radius.md,
    marginBottom: spacing.sm,
  },
  cardTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  empty: {
    color: t.textMuted,
    textAlign: 'center',
    marginTop: spacing.xl,
  },
});
