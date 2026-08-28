import React, { useCallback } from 'react';
import { FlatList, View, Text, StyleSheet, TouchableOpacity, RefreshControl } from 'react-native';
import Animated, { FadeIn } from 'react-native-reanimated';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Badge, Skeleton, EmptyState, ErrorState, AnimatedListItem, a11y, useEntree } from '@/ui';
import { useBookings } from '@/booking';
import type { Booking } from '@/booking';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { formatAdresse, formatDateHeure, libelleStatut } from '@/lib/format';
import type { RootStackParamList } from '@/navigation/types';
import { useTraduction } from '@/i18n';

const BOOKING_CARD_HEIGHT = 120;

export function BookingsListScreen() {
  const { t: tr } = useTraduction();
  const { data: bookings, isLoading, isError, refetch, isRefetching } = useBookings();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const themeColors = useThemeColors();
  const styles = stylesFor(themeColors);

  // Pas de transition quand l’utilisateur a réduit les mouvements.
  const entree = useEntree(FadeIn.duration(280));

  const handleRefresh = useCallback(() => {
    refetch().then(() => {
      a11y.announce(`${bookings?.length ?? 0} réservations chargées`);
    });
  }, [refetch, bookings?.length]);

  const renderBookingCard = useCallback(({ item, index }: { item: Booking; index: number }) => (
    <AnimatedListItem index={index}>
      <BookingCard booking={item} />
    </AnimatedListItem>
  ), []);

  const getItemLayout = useCallback((_: any, index: number) => ({
    length: BOOKING_CARD_HEIGHT,
    offset: BOOKING_CARD_HEIGHT * index,
    index,
  }), []);

  if (isError) return <Screen><ErrorState message="Impossible de charger vos réservations." onRetry={refetch} /></Screen>;

  return (
    <Screen>
      <Text
        style={styles.title}
        accessibilityRole="header"
      >
        Mes réservations
      </Text>
      {isLoading ? (
        <View style={styles.skeletons}>
          {[1, 2, 3].map(i => <Skeleton key={i} width="100%" height={90} />)}
        </View>
      ) : (
        <Animated.View entering={entree} style={{ flex: 1 }}>
          <FlatList
            data={bookings ?? []}
            keyExtractor={item => String(item.id)}
            renderItem={renderBookingCard}
            getItemLayout={getItemLayout}
            contentContainerStyle={styles.list}
            accessibilityLabel={tr('bookings_list.liste_des_reservations')}
            ListEmptyComponent={<EmptyState title={tr('bookings_list.pas_encore_de_reservation')} message="Réservez votre premier service pour commencer." icon="calendar-outline" actionLabel="Réserver" onAction={() => navigation.navigate('EmbeddedModule', { path: '/commander', title: 'Commander' })} />}
            refreshControl={
              <RefreshControl
                refreshing={isRefetching}
                onRefresh={handleRefresh}
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

/**
 * L'état normalisé du serveur, avec repli sur le statut brut.
 *
 * `status` porte le vocabulaire du domaine (`en_route`, `sur_place`, `termine`…) ; `state` porte
 * les six valeurs que `libelleStatut` et la table ci-dessous savent lire. Les indexer par `status`
 * affichait le jargon au client et retombait sur la couleur neutre.
 */
const etatDe = (b: { state?: string; status: string }) => b.state ?? b.status;

const statusVariant: Record<string, 'success' | 'warning' | 'danger' | 'neutral' | 'brand'> = {
  pending: 'warning',
  confirmed: 'brand',
  in_progress: 'success',
  completed: 'success',
  cancelled: 'danger',
};

const BookingCard = React.memo(function BookingCard({ booking }: { booking: Booking }) {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const themeColors = useThemeColors();
  const styles = stylesFor(themeColors);

  return (
    <TouchableOpacity
      disabled={false}
      onPress={() => navigation.navigate('BookingDetail', { bookingId: booking.id })}
      activeOpacity={0.7}
    >
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Text style={styles.serviceName}>{booking.service_name}</Text>
          <Badge label={libelleStatut(etatDe(booking))} variant={statusVariant[etatDe(booking)] ?? 'neutral'} />
        </View>
        <Text style={styles.cardDate}>{formatDateHeure(booking.scheduled_date, booking.scheduled_time)}</Text>
        <Text style={styles.cardAddress}>{formatAdresse(booking.address, booking.city)}</Text>
        {booking.provider_name && (
          <Text style={styles.cardProvider}>Prestataire: {booking.provider_name}</Text>
        )}
        <Text style={styles.trackHint}>{tr('bookings_list.appuyer_pour_voir_le_detail')}</Text>
      </View>
    </TouchableOpacity>
  );
});

/*
 * LES COULEURS VIVENT DANS LES STYLES, PLUS A COTE. L'ecran figeait ses styles puis les
 * rattrapait a chaque balise — cinq fois — et `cardProvider`/`trackHint` n'avaient meme
 * pas de rattrapage : leur indigo fige rendait 4,47 sur le blanc et 3,88 sur la nuit.
 */
const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: { color: t.text,
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    marginBottom: spacing.md,
  },
  skeletons: { gap: spacing.sm },
  list: { gap: spacing.sm, paddingBottom: spacing.xl },
  card: { backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.xs,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  serviceName: { color: t.text,
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
  },
  cardDate: { color: t.textSecondary,
    fontSize: typography.fontSize.sm,
    marginTop: spacing.xs,
  },
  cardAddress: { color: t.textMuted,
    fontSize: typography.fontSize.xs,
    marginTop: 2,
  },
  cardProvider: {
    fontSize: typography.fontSize.xs,
    color: t.brandText,
    marginTop: spacing.xs,
  },
  trackHint: {
    fontSize: typography.fontSize.xs,
    color: t.brandText,
    marginTop: spacing.xs,
    fontWeight: typography.fontWeight.semibold,
  },
});
