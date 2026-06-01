import React, { useState } from 'react';
import { View, FlatList, Text, Pressable, StyleSheet } from 'react-native';
import { Screen, TextInput, Button, Avatar, Badge, Skeleton, EmptyState } from '@/ui';
import { useBrowseProviders, useBooking } from '@/booking';
import type { Provider } from '@/booking';
import { colors, spacing, typography, radius } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { BookingStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<BookingStackParamList, 'BookingProviderSearch'>;

/**
 * SP2 palier 3 (mobile) — premium provider search opened FROM the booking wizard.
 *
 * This screen lives inside the BookingNavigator stack, so it shares the same
 * BookingProvider context as the wizard steps. When the client taps "Choisir"
 * on a provider, we dispatch SET_PREFERRED_PROVIDER (the provider `id` returned
 * by /search/providers is the provider USER id) and navigate back to the wizard
 * — the preferred provider is then pinned in BookingStepProvider.
 *
 * Reuses the existing useBrowseProviders hook (GET /search/providers) so we do
 * not reimplement a search engine. The backend stays the authoritative premium
 * gate: a non-favourite pick can still be refused server-side.
 */
export function BookingProviderSearchScreen({ navigation }: Props) {
  const { dispatch } = useBooking();
  const [trade, setTrade] = useState('');
  const [postalCode, setPostalCode] = useState('');

  const { data, isLoading } = useBrowseProviders({
    trade: trade || undefined,
    postalCode: postalCode || undefined,
  });

  const choose = (providerUserId: number) => {
    dispatch({ type: 'SET_PREFERRED_PROVIDER', preferredProviderUserId: providerUserId });
    navigation.goBack();
  };

  const renderCard = ({ item }: { item: Provider }) => (
    <View style={styles.card}>
      <Avatar name={item.name} imageUri={item.avatar_url} size={44} accessibilityLabel={item.name} />
      <View style={styles.cardContent}>
        <Text style={styles.providerName}>{item.name}</Text>
        <View style={styles.cardMeta}>
          <Text style={styles.rating}>
            {'⭐'} {item.rating_avg.toFixed(1)} ({item.review_count})
          </Text>
          {item.distance_km != null ? (
            <Text style={styles.distance}>{item.distance_km.toFixed(1)} km</Text>
          ) : null}
        </View>
        <View style={styles.trades}>
          {item.trades.slice(0, 3).map((t) => (
            <Badge key={t} label={t} variant="brand" />
          ))}
        </View>
      </View>
      <Pressable
        testID={`choose-provider-${item.id}`}
        accessibilityRole="button"
        onPress={() => choose(item.id)}
        style={styles.chooseBtn}
      >
        <Text style={styles.chooseLabel}>Choisir</Text>
      </Pressable>
    </View>
  );

  return (
    <Screen>
      <Text style={styles.title} accessibilityRole="header">
        Choisir un prestataire
      </Text>
      <Text style={styles.subtitle}>
        Sélectionnez le prestataire qui interviendra. Filtrez par métier ou code postal.
      </Text>

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
          {[1, 2, 3].map((i) => (
            <Skeleton key={i} width="100%" height={80} />
          ))}
        </View>
      ) : (
        <FlatList
          data={data?.data ?? []}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderCard}
          contentContainerStyle={styles.list}
          accessibilityLabel="Liste des prestataires"
          ListEmptyComponent={
            <EmptyState
              title="Aucun prestataire trouvé"
              message="Saisissez un métier ou un code postal pour lancer la recherche."
              icon="search-outline"
            />
          }
        />
      )}

      <View style={styles.footer}>
        <Button
          label="Annuler"
          variant="ghost"
          onPress={() => navigation.goBack()}
          fullWidth
        />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginBottom: spacing.xs,
  },
  subtitle: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
    marginBottom: spacing.md,
  },
  filters: { gap: spacing.sm, marginBottom: spacing.md },
  skeletons: { gap: spacing.sm },
  list: { gap: spacing.sm, paddingBottom: spacing.xl },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.surface[200],
    backgroundColor: colors.surface[50],
    padding: spacing.md,
    gap: spacing.sm,
  },
  cardContent: { flex: 1 },
  providerName: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  cardMeta: { flexDirection: 'row', gap: spacing.sm, marginTop: 2 },
  rating: { fontSize: typography.fontSize.xs, color: colors.surface[500] },
  distance: { fontSize: typography.fontSize.xs, color: colors.surface[400] },
  trades: {
    flexDirection: 'row',
    gap: spacing.xs,
    marginTop: spacing.xs,
    flexWrap: 'wrap',
  },
  chooseBtn: {
    borderRadius: radius.sm,
    backgroundColor: colors.brand[500],
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.md,
  },
  chooseLabel: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[50],
  },
  footer: {
    marginTop: spacing.sm,
    marginBottom: spacing.lg,
  },
});
