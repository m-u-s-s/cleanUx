import React, { useState, useCallback } from 'react';
import { View, FlatList, Text, StyleSheet, RefreshControl } from 'react-native';
import Animated, { FadeIn } from 'react-native-reanimated';
import { Screen, TextInput, Badge, Avatar, Skeleton, EmptyState, AnimatedListItem, useEntree } from '@/ui';
import { useBrowseProviders } from '@/booking';
import type { Provider } from '@/booking';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import { useTraduction } from '@/i18n';

const PROVIDER_CARD_HEIGHT = 100;

export function BrowseProvidersScreen() {
  const { t: tr } = useTraduction();
  const [trade, setTrade] = useState('');
  const [postalCode, setPostalCode] = useState('');
  const themeColors = useThemeColors();
  const { data, isLoading, refetch, isRefetching } = useBrowseProviders({
    trade: trade || undefined,
    postalCode: postalCode || undefined,
  });

  // Pas de transition quand l’utilisateur a réduit les mouvements.
  const entree = useEntree(FadeIn.duration(280));

  /*
   * N'AVOIR RIEN CHERCHÉ N'EST PAS N'AVOIR RIEN TROUVÉ.
   *
   * `useBrowseProviders` ne se déclenche QUE si un métier ou un code postal est saisi — et c'est
   * voulu : sans filtre, la requête ramènerait l'annuaire entier. Mais la liste, elle, rendait son
   * état vide dès l'ouverture, si bien que l'écran s'ouvrait sur « Aucun prestataire trouvé ».
   * Relevé à l'écran : on arrive sur « Explorer », on lit qu'il n'y a personne, et on en conclut
   * que la plateforme est vide. La requête n'avait simplement jamais été lancée.
   */
  const uneRechercheEstLancee = trade !== '' || postalCode !== '';

  const renderProviderCard = useCallback(({ item, index }: { item: Provider; index: number }) => (
    <AnimatedListItem index={index}>
      <ProviderCard provider={item} />
    </AnimatedListItem>
  ), []);

  const getItemLayout = useCallback((_: any, index: number) => ({
    length: PROVIDER_CARD_HEIGHT,
    offset: PROVIDER_CARD_HEIGHT * index,
    index,
  }), []);

  return (
    <Screen>
      <Text
        style={[styles.title, { color: themeColors.text }]}
        accessibilityRole="header"
      >
        Explorer les prestataires
      </Text>
      <View style={styles.filters}>
        <TextInput
          label={tr('browse_providers.metier')}
          value={trade}
          onChangeText={setTrade}
          placeholder={tr('browse_providers.nettoyage_peinture')}
        />
        <TextInput
          label={tr('browse_providers.code_postal')}
          value={postalCode}
          onChangeText={setPostalCode}
          placeholder="1000"
          keyboardType="numeric"
        />
      </View>
      {isLoading ? (
        <View style={styles.skeletons}>
          {[1, 2, 3].map(i => <Skeleton key={i} width="100%" height={80} />)}
        </View>
      ) : (
        <Animated.View entering={entree} style={{ flex: 1 }}>
          <FlatList
            data={data?.data ?? []}
            keyExtractor={item => String(item.id)}
            renderItem={renderProviderCard}
            getItemLayout={getItemLayout}
            contentContainerStyle={styles.list}
            accessibilityLabel={tr('browse_providers.liste_des_prestataires')}
            ListEmptyComponent={uneRechercheEstLancee ? (
              <EmptyState title={tr('browse_providers.aucun_prestataire_trouve')} message="Essayez avec d'autres critères de recherche." icon="search-outline" />
            ) : (
              <EmptyState title={tr('browse_providers.trouvez_un_prestataire')} message="Renseignez un métier ou un code postal pour lancer la recherche." icon="search-outline" />
            )}
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
          {provider.rating_avg != null ? (
            <Text style={[styles.rating, { color: themeColors.textSecondary }]}>⭐ {provider.rating_avg.toFixed(1)} ({provider.review_count})</Text>
          ) : (
            // Pas encore noté : on le dit. « ⭐ 0.0 » se lirait comme une mauvaise note.
            <Text style={[styles.rating, { color: themeColors.textSecondary }]}>{tr('browse_providers.nouveau_prestataire')}</Text>
          )}
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
