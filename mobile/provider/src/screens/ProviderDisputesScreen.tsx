import React from 'react';
import { FlatList, View, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, Skeleton, EmptyState, ErrorState } from '@/ui';
import { apiClient } from '@/api';
import {spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Dispute {
  id: number;
  status: string;
  reason?: string;
  created_at: string;
}

export function ProviderDisputesScreen() {
  const styles = stylesFor(useThemeColors());

  const { data, isLoading, isError, refetch, isRefetching } = useQuery<Dispute[]>({
    queryKey: ['provider', 'disputes'],
    queryFn: async () => (await apiClient.get('/provider/disputes')).data.data ?? [],
  });

  if (isError) return <Screen><ErrorState message="Impossible de charger vos litiges." onRetry={refetch} /></Screen>;

  return (
    <Screen>
      <Text style={styles.title}>Litiges</Text>
      {isLoading ? (
        <Skeleton width="100%" height={80} />
      ) : (
        <FlatList
          data={data ?? []}
          keyExtractor={item => String(item.id)}
          renderItem={({ item }) => (
            <View style={styles.card}>
              <Text style={styles.cardTitle}>Litige #{item.id}</Text>
              <Badge
                label={item.status}
                variant={item.status === 'resolved' ? 'success' : 'warning'}
              />
            </View>
          )}
          onRefresh={refetch}
          refreshing={isRefetching}
          ListEmptyComponent={<EmptyState title="Aucun litige" message="Vous n'avez aucun litige en cours." />}
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
