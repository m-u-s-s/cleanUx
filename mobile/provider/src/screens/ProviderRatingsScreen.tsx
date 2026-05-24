import React from 'react';
import { FlatList, View, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Skeleton, Avatar } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius } from '@/theme';

interface Rating {
  id: number;
  client_name?: string;
  overall?: number;
  comment?: string;
  created_at: string;
}

export function ProviderRatingsScreen() {
  const { data, isLoading } = useQuery<Rating[]>({
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
          ListEmptyComponent={<Text style={styles.empty}>Aucun avis</Text>}
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
  card: {
    backgroundColor: '#fff',
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
    color: colors.surface[900],
  },
  date: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[400],
  },
  stars: {
    fontSize: typography.fontSize.lg,
    color: colors.accent.amber,
  },
  comment: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[600],
    marginTop: spacing.sm,
  },
  empty: {
    color: colors.surface[400],
    textAlign: 'center',
    marginTop: spacing.xl,
  },
});
