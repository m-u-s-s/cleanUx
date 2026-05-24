import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type {
  NativeStackNavigationProp,
  NativeStackScreenProps,
} from '@react-navigation/native-stack';
import { Screen, Button, Badge, Divider } from '@/ui';
import { useBookingDetail } from '@/booking';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'BookingDetail'>;

export function BookingDetailScreen({ route }: Props) {
  const { bookingId } = route.params;
  const { data: booking, isLoading } = useBookingDetail(bookingId);
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  if (isLoading || !booking) {
    return (
      <Screen>
        <Text style={styles.loading}>Chargement...</Text>
      </Screen>
    );
  }

  const canStart = booking.status === 'confirmed';
  const canEnd = booking.status === 'in_progress';
  const canTrack = ['confirmed', 'in_progress'].includes(booking.status);

  const statusVariant =
    booking.status === 'completed'
      ? 'success'
      : booking.status === 'cancelled'
      ? 'danger'
      : 'brand';

  return (
    <Screen scroll>
      <View style={styles.header}>
        <Text style={styles.title}>{booking.service_name}</Text>
        <Badge label={booking.status} variant={statusVariant} />
      </View>

      <View style={styles.card}>
        <DetailRow
          label="Date"
          value={`${booking.scheduled_date} à ${booking.scheduled_time}`}
        />
        <Divider />
        <DetailRow
          label="Adresse"
          value={`${booking.address}, ${booking.city}`}
        />
        {booking.provider_name ? (
          <>
            <Divider />
            <DetailRow label="Prestataire" value={booking.provider_name} />
          </>
        ) : null}
        {booking.total_price != null ? (
          <>
            <Divider />
            <DetailRow label="Prix" value={`${booking.total_price} €`} />
          </>
        ) : null}
      </View>

      <View style={styles.actions}>
        {['pending', 'confirmed'].includes(booking.status) && booking.total_price != null && (
          <Button
            label={`Payer ${booking.total_price} €`}
            onPress={() => navigation.navigate('PaymentCheckout', { bookingId })}
            fullWidth
          />
        )}
        {canTrack && (
          <Button
            label="Suivre en direct"
            onPress={() => navigation.navigate('MissionTracking', { bookingId })}
            variant="secondary"
            fullWidth
          />
        )}
        {canStart && (
          <Button
            label="Scanner QR — Démarrer"
            onPress={() =>
              navigation.navigate('QRScan', { bookingId, action: 'start' })
            }
            fullWidth
          />
        )}
        {canEnd && (
          <Button
            label="Scanner QR — Terminer"
            onPress={() =>
              navigation.navigate('QRScan', { bookingId, action: 'end' })
            }
            variant="danger"
            fullWidth
          />
        )}
      </View>
    </Screen>
  );
}

function DetailRow({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text style={styles.rowValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  loading: {
    fontSize: typography.fontSize.base,
    color: colors.surface[500],
    textAlign: 'center',
    marginTop: spacing.xl,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.lg,
    marginTop: spacing.md,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    flex: 1,
    marginRight: spacing.sm,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.soft,
    marginBottom: spacing.lg,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: spacing.sm,
  },
  rowLabel: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
  },
  rowValue: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: colors.surface[900],
    flex: 1,
    textAlign: 'right',
    marginLeft: spacing.sm,
  },
  actions: {
    gap: spacing.sm,
  },
});
