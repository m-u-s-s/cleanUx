import React, { useState } from 'react';
import { View, FlatList, Text, StyleSheet, RefreshControl } from 'react-native';
import Animated, { FadeIn } from 'react-native-reanimated';
import { Screen, TextInput, Badge, Avatar, Skeleton, EmptyState, AnimatedListItem } from '@/ui';
import { useBrowseProviders } from '@/booking';
import type { Provider } from '@/booking';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';

export function BrowseProvidersScreen() {
  const [trade, setTrade] = useState('');
  const [postalCode, setPostalCode] = useState('');
  const themeColors = useThemeColors();
  const { data, isLoading, refetch, isRefetching } = useBrowseProviders({
    trade: trade || undefined,
    postalCode: postalCode || undefined,
  });

  return (
    <Screen>
      <Text style={[styles.title, { color: themeColors.text }]}>Explorer les prestataires</Text>
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
        <Animated.View entering={FadeIn.duration(280)} style={{ flex: 1 }}>
          <FlatList
            data={data?.data ?? []}
            keyExtractor={item => String(item.id)}
            renderItem={({ item, index }) => (
              <AnimatedListItem index={index}>
                <ProviderCard provider={item} />
              </AnimatedListItem>
            )}
            contentContainerStyle={styles.list}
            ListEmptyComponent={<EmptyState title="Aucun prestataire trouvé" message="Essayez avec d'autres critères de recherche." />}
            refreshControl={
              <RefreshControl
                refreshing={isRefetching}
                onRefresh={refetch}
                tintColor={colors.brand[500]}
                colors={[colors.brand[500]]}
              />
            }
          />
        </Animated.View>
      )}
    </Screen>
  );
}

const ProviderCard = React.memo(function ProviderCard({ provider }: { provider: Provider }) {
  const themeColors = useThemeColors();
  return (
    <View style={[styles.card, { backgroundColor: themeColors.card }]}>
      <Avatar name={provider.name} imageUri={provider.avatar_url} size={48} accessibilityLabel={provider.name} />
      <View style={styles.cardContent}>
        <Text style={[styles.providerName, { color: themeColors.text }]}>{provider.name}</Text>
        <View style={styles.cardMeta}>
          <Text style={[styles.rating, { color: themeColors.textSecondary }]}>⭐ {provider.rating_avg.toFixed(1)} ({provider.review_count})</Text>
          {provider.distance_km != null && (
            <Text style={[styles.distance, { color: themeColors.textMuted }]}>{provider.distance_km.toFixed(1)} km</Text>
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
});

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    marginBottom: spacing.md,
  },
  filters: { gap: spacing.sm, marginBottom: spacing.md },
  skeletons: { gap: spacing.sm },
  list: { gap: spacing.sm, paddingBottom: spacing.xl },
  card: {
    flexDirection: 'row',
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.xs,
    gap: spacing.sm,
  },
  cardContent: { flex: 1 },
  providerName: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
  },
  cardMeta: { flexDirection: 'row', gap: spacing.sm, marginTop: 2 },
  rating: { fontSize: typography.fontSize.xs },
  distance: { fontSize: typography.fontSize.xs },
  trades: {
    flexDirection: 'row',
    gap: spacing.xs,
    marginTop: spacing.xs,
    flexWrap: 'wrap',
  },
});
