import React, { useEffect, useState } from 'react';
import { View, Text, Alert, StyleSheet } from 'react-native';
import { useStripe } from '@stripe/stripe-react-native';
import { Screen, Button, KPICard } from '@/ui';
import { useBookingDetail } from '@/booking';
import { usePaymentIntent } from '@/payment';
import { colors, spacing, typography } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'PaymentCheckout'>;

export function PaymentCheckoutScreen({ route, navigation }: Props) {
  const { bookingId } = route.params;
  const { data: booking } = useBookingDetail(bookingId);
  const paymentIntent = usePaymentIntent(bookingId);
  const { initPaymentSheet, presentPaymentSheet } = useStripe();
  const [ready, setReady] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const { client_secret } = await paymentIntent.mutateAsync();
        const { error } = await initPaymentSheet({
          paymentIntentClientSecret: client_secret,
          merchantDisplayName: 'CleanUx',
          style: 'automatic',
          defaultBillingDetails: { email: '' },
        });
        if (!error) setReady(true);
      } catch {
        // silently fail — button stays disabled
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [bookingId]);

  const handlePay = async () => {
    const { error } = await presentPaymentSheet();
    if (error) {
      if (error.code !== 'Canceled') Alert.alert('Erreur paiement', error.message);
    } else {
      Alert.alert('Paiement confirmé', 'Merci ! Le prestataire sera notifié.', [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    }
  };

  return (
    <Screen>
      <Text style={styles.title}>Paiement</Text>
      {booking && (
        <View style={styles.summary}>
          <KPICard title="Service" value={booking.service_name} />
          {booking.total_price != null && (
            <KPICard title="Montant" value={`${booking.total_price} €`} tone="success" />
          )}
        </View>
      )}
      <Button
        label={ready ? 'Payer maintenant' : 'Chargement...'}
        onPress={handlePay}
        disabled={!ready}
        loading={!ready}
        fullWidth
        size="lg"
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginTop: spacing.md,
    marginBottom: spacing.lg,
  },
  summary: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginBottom: spacing.xl,
  },
});
