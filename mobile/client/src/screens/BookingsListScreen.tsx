import React from 'react';
import { FlatList, View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Badge, Skeleton } from '@/ui';
import { useBookings } from '@/booking';
import type { Booking } from '@/booking';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

export function BookingsListScreen() {
  const { data: bookings, isLoading, refetch } = useBookings();

  return (
    <Screen>
      <Text style={styles.title}>Mes réservations</Text>
      {isLoading ? (
        <View style={styles.skeletons}>
          {[1, 2, 3].map(i => <Skeleton key={i} width="100%" height={90} />)}
        </View>
      ) : (
        <FlatList
          data={bookings ?? []}
          keyExtractor={item => String(item.id)}
          renderItem={({ item }) => <BookingCard booking={item} />}
          contentContainerStyle={styles.list}
          ListEmptyComponent={<Text style={styles.empty}>Aucune réservation pour le moment</Text>}
          onRefresh={refetch}
          refreshing={isLoading}
        />
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

function BookingCard({ booking }: { booking: Booking }) {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  return (
    <TouchableOpacity
      disabled={false}
      onPress={() => navigation.navigate('BookingDetail', { bookingId: booking.id })}
      activeOpacity={0.7}
    >
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Text style={styles.serviceName}>{booking.service_name}</Text>
          <Badge label={booking.status} variant={statusVariant[booking.status] ?? 'neutral'} />
        </View>
        <Text style={styles.cardDate}>{booking.scheduled_date} à {booking.scheduled_time}</Text>
        <Text style={styles.cardAddress}>{booking.address}, {booking.city}</Text>
        {booking.provider_name && (
          <Text style={styles.cardProvider}>Prestataire: {booking.provider_name}</Text>
        )}
        <Text style={styles.trackHint}>Appuyer pour voir le détail</Text>
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginBottom: spacing.md,
  },
  skeletons: { gap: spacing.sm },
  list: { gap: spacing.sm, paddingBottom: spacing.xl },
  empty: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[400],
    textAlign: 'center',
    marginTop: spacing.xl,
  },
  card: {
    backgroundColor: '#fff',
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
    color: colors.surface[900],
  },
  cardDate: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[600],
    marginTop: spacing.xs,
  },
  cardAddress: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[400],
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
