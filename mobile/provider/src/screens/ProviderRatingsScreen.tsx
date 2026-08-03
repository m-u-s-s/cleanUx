import React from 'react';
import { FlatList, View, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Skeleton, Avatar, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Rating {
  id: number;
  client_name?: string;
  overall?: number;
  comment?: string;
  created_at: string;
}

export function ProviderRatingsScreen() {
  const styles = stylesFor(useThemeColors());

  const { data, isLoading, refetch, isRefetching } = useQuery<Rating[]>({
    queryKey: ['provider', 'ratings'],
    queryFn: async () => (await apiClient.get('/provider/ratings/me')).data.data ?? [],
  });

  return (
    <Screen scroll>
      <Text style={styles.title}>Avis reçus</Text>
      {isLoading ? (
        <Skeleton width="100%" height={200} />
      ) : (
        <FlatList
          data={data ?? []}
          scrollEnabled={false}
          keyExtractor={item => String(item.id)}
          renderItem={({ item }) => (
            <View style={styles.card}>
              <View style={styles.cardHeader}>
                <Avatar name={item.client_name ?? 'Client'} size={36} />
                <View style={styles.cardMeta}>
                  <Text style={styles.clientName}>{item.client_name ?? 'Client'}</Text>
                  <Text style={styles.date}>
                    {new Date(item.created_at).toLocaleDateString()}
                  </Text>
                </View>
                <Text style={styles.stars}>
                  {'★'.repeat(item.overall ?? 0)}{'☆'.repeat(5 - (item.overall ?? 0))}
                </Text>
              </View>
              {item.comment != null && (
                <Text style={styles.comment}>{item.comment}</Text>
              )}
            </View>
          )}
          onRefresh={refetch}
          refreshing={isRefetching}
          ListEmptyComponent={<EmptyState title="Aucun avis" message="Vos avis clients apparaîtront ici après vos missions." />}
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
    backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.sm,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  cardMeta: { flex: 1 },
  clientName: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: t.text,
  },
  date: {
    fontSize: typography.fontSize.xs,
    color: t.textMuted,
  },
  stars: {
    fontSize: typography.fontSize.lg,
    color: colors.accent.amber,
  },
  comment: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    marginTop: spacing.sm,
  },
  empty: {
    color: t.textMuted,
    textAlign: 'center',
    marginTop: spacing.xl,
  },
});
