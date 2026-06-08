import React, { useCallback, useEffect, useState } from 'react';
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
  const [error, setError] = useState<string | null>(null);
  const [initializing, setInitializing] = useState(true);

  // L8 — surface init failures (PaymentIntent creation / sheet init) with a retry instead of
  // leaving the button on a permanent "Chargement..." spinner.
  const initialize = useCallback(async () => {
    setInitializing(true);
    setError(null);
    setReady(false);
    try {
      const { client_secret } = await paymentIntent.mutateAsync();
      const { error: sheetError } = await initPaymentSheet({
        paymentIntentClientSecret: client_secret,
        merchantDisplayName: 'CleanUx',
        style: 'automatic',
        defaultBillingDetails: { email: '' },
      });
      if (sheetError) {
        setError(sheetError.message ?? 'Impossible de préparer le paiement.');
      } else {
        setReady(true);
      }
    } catch (e: any) {
      setError(e?.message ?? 'Impossible de préparer le paiement. Vérifiez votre connexion.');
    } finally {
      setInitializing(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [bookingId]);

  useEffect(() => {
    void initialize();
  }, [initialize]);

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
      {error ? (
        <View>
          <Text style={styles.error} accessibilityRole="alert">{error}</Text>
          <Button label="Réessayer" onPress={() => void initialize()} fullWidth size="lg" />
        </View>
      ) : (
        <Button
          label={ready ? 'Payer maintenant' : 'Chargement...'}
          onPress={handlePay}
          disabled={!ready}
          loading={initializing}
          fullWidth
          size="lg"
        />
      )}
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
  error: {
    color: colors.danger?.[600] ?? '#dc2626',
    fontSize: typography.fontSize.sm,
    marginBottom: spacing.md,
    textAlign: 'center',
  },
});
