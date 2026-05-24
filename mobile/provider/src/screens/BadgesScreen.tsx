import React from 'react';
import { FlatList, View, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, Skeleton } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius, shadows } from '@/theme';

interface ProviderBadge {
  id: number;
  name: string;
  tier: string;
  description: string;
  awarded_at: string | null;
}

export function BadgesScreen() {
  const { data: badges, isLoading } = useQuery<ProviderBadge[]>({
    queryKey: ['badges'],
    queryFn: async () => (await apiClient.get('/provider/badges')).data.data ?? [],
  });

  return (
    <Screen scroll>
      <Text style={styles.title}>Mes badges</Text>
      {isLoading ? (
        <Skeleton width="100%" height={200} />
      ) : (
        <FlatList
          data={badges ?? []}
          scrollEnabled={false}
          keyExtractor={i => String(i.id)}
          numColumns={2}
          columnWrapperStyle={styles.row}
          renderItem={({ item }) => (
            <View style={[styles.card, !item.awarded_at && styles.locked]}>
              <Text style={styles.badgeName}>{item.name}</Text>
              <Badge label={item.tier} variant={item.awarded_at ? 'success' : 'neutral'} />
              <Text style={styles.badgeDesc}>{item.description}</Text>
              {item.awarded_at && (
                <Text style={styles.date}>
                  Obtenu le {new Date(item.awarded_at).toLocaleDateString()}
                </Text>
              )}
            </View>
          )}
          ListEmptyComponent={<Text style={styles.empty}>Aucun badge</Text>}
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
  row: { gap: spacing.sm, marginBottom: spacing.sm },
  card: {
    flex: 1,
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.xs,
    alignItems: 'center',
  },
  locked: { opacity: 0.5 },
  badgeName: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
    marginBottom: spacing.xs,
  },
  badgeDesc: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[500],
    textAlign: 'center',
    marginTop: spacing.xs,
  },
  date: {
    fontSize: 10,
    color: colors.success[600],
    marginTop: spacing.xs,
  },
  empty: {
    color: colors.surface[400],
    textAlign: 'center',
    marginTop: spacing.xl,
  },
});
