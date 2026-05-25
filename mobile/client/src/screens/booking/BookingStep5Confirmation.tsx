import React from 'react';
import { View, Text, Alert, StyleSheet } from 'react-native';
import { Screen, Button, Divider, ProgressBar } from '@/ui';
import { useBooking, useCreateBooking } from '@/booking';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { BookingStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<BookingStackParamList, 'BookingStep5'>;

export function BookingStep5Confirmation({ navigation }: Props) {
  const { state, dispatch } = useBooking();
  const createBooking = useCreateBooking();
  const themeColors = useThemeColors();

  const handleConfirm = async () => {
    if (!state.serviceId) return;
    try {
      await createBooking.mutateAsync({
        serviceId: state.serviceId,
        details: state.details,
        coordinates: state.coordinates,
        scheduling: state.scheduling,
      });
      dispatch({ type: 'RESET' });
      Alert.alert(
        'Réservation confirmée',
        'Votre demande a été envoyée. Un prestataire sera assigné bientôt.',
        [{ text: 'OK', onPress: () => navigation.getParent()?.goBack() }],
      );
    } catch (e: unknown) {
      const message = e instanceof Error ? e.message : 'Impossible de créer la réservation.';
      Alert.alert('Erreur', message);
    }
  };

  return (
    <Screen scroll>
      <ProgressBar step={5} totalSteps={5} />
      <Text style={styles.title}>Récapitulatif</Text>
      <View style={[styles.card, { backgroundColor: themeColors.card }]}>
        <Row label="Service" value={state.serviceName} />
        <Divider />
        <Row
          label="Adresse"
          value={`${state.coordinates.address}, ${state.coordinates.postalCode} ${state.coordinates.city}`}
        />
        <Divider />
        <Row
          label="Date"
          value={
            state.scheduling.isAsap
              ? 'Dès que possible'
              : `${state.scheduling.date} à ${state.scheduling.time}`
          }
        />
        {state.details.surface ? (
          <>
            <Divider />
            <Row label="Surface" value={`${state.details.surface} m²`} />
          </>
        ) : null}
        {state.details.comment ? (
          <>
            <Divider />
            <Row label="Commentaire" value={state.details.comment} />
          </>
        ) : null}
      </View>
      <Button
        label={createBooking.isPending ? 'Envoi…' : 'Confirmer la réservation'}
        onPress={handleConfirm}
        loading={createBooking.isPending}
        disabled={createBooking.isPending}
        fullWidth
        size="lg"
      />
    </Screen>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.row}>
      <Text style={styles.label}>{label}</Text>
      <Text style={styles.value}>{value}</Text>
    </View>
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
  card: {
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.soft,
    marginBottom: spacing.xl,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: spacing.sm,
  },
  label: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
  },
  value: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: colors.surface[900],
    flex: 1,
    textAlign: 'right',
    marginLeft: spacing.sm,
  },
});
