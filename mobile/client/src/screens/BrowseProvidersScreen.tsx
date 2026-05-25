import React, { useState } from 'react';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { Screen, TextInput, Badge, Avatar, Skeleton, EmptyState } from '@/ui';
import { useBrowseProviders } from '@/booking';
import type { Provider } from '@/booking';
import { colors, spacing, typography, radius, shadows } from '@/theme';

export function BrowseProvidersScreen() {
  const [trade, setTrade] = useState('');
  const [postalCode, setPostalCode] = useState('');
  const { data, isLoading, refetch, isRefetching } = useBrowseProviders({
    trade: trade || undefined,
    postalCode: postalCode || undefined,
  });

  return (
    <Screen>
      <Text style={styles.title}>Explorer les prestataires</Text>
      <View style={styles.filters}>
        <TextInput
          label="Métier"
          value={trade}
          onChangeText={setTrade}
          placeholder="nettoyage, peinture…"
        />
        <TextInput
          label="Code postal"
          value={postalCode}
          onChangeText={setPostalCode}
          placeholder="75001"
          keyboardType="numeric"
        />
      </View>
      {isLoading ? (
        <View style={styles.skeletons}>
          {[1, 2, 3].map(i => <Skeleton key={i} width="100%" height={80} />)}
        </View>
      ) : (
        <FlatList
          data={data?.data ?? []}
          keyExtractor={item => String(item.id)}
          renderItem={({ item }) => <ProviderCard provider={item} />}
          contentContainerStyle={styles.list}
          ListEmptyComponent={<EmptyState title="Aucun prestataire trouvé" message="Essayez avec d'autres critères de recherche." />}
          onRefresh={refetch}
          refreshing={isRefetching}
        />
      )}
    </Screen>
  );
}

function ProviderCard({ provider }: { provider: Provider }) {
  return (
    <View style={styles.card}>
      <Avatar name={provider.name} imageUri={provider.avatar_url} size={48} />
      <View style={styles.cardContent}>
        <Text style={styles.providerName}>{provider.name}</Text>
        <View style={styles.cardMeta}>
          <Text style={styles.rating}>⭐ {provider.rating_avg.toFixed(1)} ({provider.review_count})</Text>
          {provider.distance_km != null && (
            <Text style={styles.distance}>{provider.distance_km.toFixed(1)} km</Text>
          )}
        </View>
        <View style={styles.trades}>
          {provider.trades.slice(0, 3).map(t => (
            <Badge key={t} label={t} variant="brand" />
          ))}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginBottom: spacing.md,
  },
  filters: { gap: spacing.sm, marginBottom: spacing.md },
  skeletons: { gap: spacing.sm },
  list: { gap: spacing.sm, paddingBottom: spacing.xl },
  card: {
    flexDirection: 'row',
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.xs,
    gap: spacing.sm,
  },
  cardContent: { flex: 1 },
  providerName: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  cardMeta: { flexDirection: 'row', gap: spacing.sm, marginTop: 2 },
  rating: { fontSize: typography.fontSize.xs, color: colors.surface[600] },
  distance: { fontSize: typography.fontSize.xs, color: colors.surface[400] },
  trades: {
    flexDirection: 'row',
    gap: spacing.xs,
    marginTop: spacing.xs,
    flexWrap: 'wrap',
  },
});
