import React from 'react';
import { FlatList, View, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, Skeleton } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius } from '@/theme';

interface Dispute {
  id: number;
  status: string;
  reason?: string;
  created_at: string;
}

export function ProviderDisputesScreen() {
  const { data, isLoading } = useQuery<Dispute[]>({
    queryKey: ['provider', 'disputes'],
    queryFn: async () => (await apiClient.get('/provider/disputes')).data.data ?? [],
  });

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
          ListEmptyComponent={<Text style={styles.empty}>Aucun litige</Text>}
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
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: spacing.md,
    backgroundColor: '#fff',
    borderRadius: radius.md,
    marginBottom: spacing.sm,
  },
  cardTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  empty: {
    color: colors.surface[400],
    textAlign: 'center',
    marginTop: spacing.xl,
  },
});
