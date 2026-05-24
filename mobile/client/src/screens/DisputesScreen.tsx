import React from 'react';
import { FlatList, View, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, Skeleton } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius } from '@/theme';

export function DisputesScreen() {
  const { data, isLoading } = useQuery({
    queryKey: ['disputes'],
    queryFn: async () => {
      const res = await apiClient.get('/client/disputes');
      return res.data.data ?? [];
    },
  });

  return (
    <Screen>
      <Text style={styles.title}>Mes litiges</Text>
      {isLoading ? (
        <Skeleton width="100%" height={80} />
      ) : (
        <FlatList
          data={data ?? []}
          keyExtractor={(item: any) => String(item.id)}
          renderItem={({ item }: { item: any }) => (
            <View style={styles.card}>
              <View style={styles.cardHeader}>
                <Text style={styles.cardTitle}>Litige #{item.id}</Text>
                <Badge
                  label={item.status}
                  variant={item.status === 'resolved' ? 'success' : 'warning'}
                />
              </View>
              <Text style={styles.cardDesc}>{item.reason ?? item.description ?? ''}</Text>
            </View>
          )}
          ListEmptyComponent={<Text style={styles.empty}>Aucun litige en cours</Text>}
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
    padding: spacing.md,
    backgroundColor: '#fff',
    borderRadius: radius.md,
    marginBottom: spacing.sm,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.xs,
  },
  cardTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  cardDesc: { fontSize: typography.fontSize.xs, color: colors.surface[500], marginTop: spacing.xs },
  empty: { color: colors.surface[400], textAlign: 'center', marginTop: spacing.xl },
});
