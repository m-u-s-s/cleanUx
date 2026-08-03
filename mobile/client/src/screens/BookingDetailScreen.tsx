import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type {
  NativeStackNavigationProp,
  NativeStackScreenProps,
} from '@react-navigation/native-stack';
import { Screen, Button, Badge, Divider, DetailRow, EmptyState, ErrorState } from '@/ui';
import { useBookingDetail } from '@/booking';
import {spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'BookingDetail'>;

export function BookingDetailScreen({ route }: Props) {
  const styles = stylesFor(useThemeColors());

  const { bookingId } = route.params;
  const { data: booking, isLoading, isError, refetch } = useBookingDetail(bookingId);
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  if (isLoading) {
    return (
      <Screen>
        <Text style={styles.loading}>Chargement...</Text>
      </Screen>
    );
  }

  if (isError) {
    return (
      <Screen>
        <ErrorState
          message="Impossible de charger cette réservation."
          onRetry={() => void refetch()}
        />
      </Screen>
    );
  }

  if (!booking) {
    return (
      <Screen>
        <EmptyState
          title="Réservation introuvable"
          message="Cette réservation n'existe plus ou n'est pas accessible."
        />
      </Screen>
    );
  }

  const canStart = booking.status === 'confirmed';
  const canEnd = booking.status === 'in_progress';
  const canTrack = ['confirmed', 'in_progress'].includes(booking.status);
  const isCompleted = booking.status === 'completed';

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

      {booking.contract_covered ? (
        <View style={styles.badgeRow} testID="contract-coverage-badge">
          <Badge label="Couvert par votre contrat" variant="info" />
        </View>
      ) : null}

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
        {isCompleted && (
          <Button
            label="Évaluer la prestation"
            onPress={() => navigation.navigate('Rating', { bookingId })}
            variant="secondary"
            fullWidth
          />
        )}
        {isCompleted && (
          <Button
            label="Laisser un pourboire"
            onPress={() => navigation.navigate('Tips', { bookingId })}
            variant="secondary"
            fullWidth
          />
        )}
      </View>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  loading: {
    fontSize: typography.fontSize.base,
    color: t.textSecondary,
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
    color: t.text,
    flex: 1,
    marginRight: spacing.sm,
  },
  badgeRow: {
    flexDirection: 'row',
    marginTop: -spacing.sm,
    marginBottom: spacing.md,
  },
  card: {
    backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.soft,
    marginBottom: spacing.lg,
  },
  actions: {
    gap: spacing.sm,
  },
});
