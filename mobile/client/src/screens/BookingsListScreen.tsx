import React, { useCallback } from 'react';
import { FlatList, View, Text, StyleSheet, TouchableOpacity, RefreshControl } from 'react-native';
import Animated, { FadeIn } from 'react-native-reanimated';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Badge, Skeleton, EmptyState, ErrorState, AnimatedListItem, a11y } from '@/ui';
import { useBookings } from '@/booking';
import type { Booking } from '@/booking';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

const BOOKING_CARD_HEIGHT = 120;

export function BookingsListScreen() {
  const { data: bookings, isLoading, isError, refetch, isRefetching } = useBookings();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const themeColors = useThemeColors();

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
        style={[styles.title, { color: themeColors.text }]}
        accessibilityRole="header"
      >
        Mes réservations
      </Text>
      {isLoading ? (
        <View style={styles.skeletons}>
          {[1, 2, 3].map(i => <Skeleton key={i} width="100%" height={90} />)}
        </View>
      ) : (
        <Animated.View entering={FadeIn.duration(280)} style={{ flex: 1 }}>
          <FlatList
            data={bookings ?? []}
            keyExtractor={item => String(item.id)}
            renderItem={renderBookingCard}
            getItemLayout={getItemLayout}
            contentContainerStyle={styles.list}
            accessibilityLabel="Liste des réservations"
            ListEmptyComponent={<EmptyState title="Pas encore de réservation" message="Réservez votre premier service pour commencer." actionLabel="Réserver" onAction={() => navigation.navigate('BookingWizard')} />}
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

  return (
    <TouchableOpacity
      disabled={false}
      onPress={() => navigation.navigate('BookingDetail', { bookingId: booking.id })}
      activeOpacity={0.7}
    >
      <View style={[styles.card, { backgroundColor: themeColors.card }]}>
        <View style={styles.cardHeader}>
          <Text style={[styles.serviceName, { color: themeColors.text }]}>{booking.service_name}</Text>
          <Badge label={booking.status} variant={statusVariant[booking.status] ?? 'neutral'} />
        </View>
        <Text style={[styles.cardDate, { color: themeColors.textSecondary }]}>{booking.scheduled_date} à {booking.scheduled_time}</Text>
        <Text style={[styles.cardAddress, { color: themeColors.textMuted }]}>{booking.address}, {booking.city}</Text>
        {booking.provider_name && (
          <Text style={styles.cardProvider}>Prestataire: {booking.provider_name}</Text>
        )}
        <Text style={styles.trackHint}>Appuyer pour voir le détail</Text>
      </View>
    </TouchableOpacity>
  );
});

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    marginBottom: spacing.md,
  },
  skeletons: { gap: spacing.sm },
  list: { gap: spacing.sm, paddingBottom: spacing.xl },
  card: {
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.xs,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  serviceName: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
  },
  cardDate: {
    fontSize: typography.fontSize.sm,
    marginTop: spacing.xs,
  },
  cardAddress: {
    fontSize: typography.fontSize.xs,
    marginTop: 2,
  },
  cardProvider: {
    fontSize: typography.fontSize.xs,
    color: colors.brand[600],
    marginTop: spacing.xs,
  },
  trackHint: {
    fontSize: typography.fontSize.xs,
    color: colors.brand[500],
    marginTop: spacing.xs,
    fontWeight: typography.fontWeight.semibold,
  },
});
