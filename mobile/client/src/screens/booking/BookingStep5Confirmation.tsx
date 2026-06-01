import React, { useState } from 'react';
import { View, Text, Alert, StyleSheet } from 'react-native';
import { Screen, Button, Divider, ProgressBar, SuccessOverlay, a11y } from '@/ui';
import { useBooking, useCreateBooking } from '@/booking';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { BookingStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<BookingStackParamList, 'BookingStep5'>;

export function BookingStep5Confirmation({ navigation }: Props) {
  const { state, dispatch } = useBooking();
  const createBooking = useCreateBooking();
  const themeColors = useThemeColors();
  const [showSuccess, setShowSuccess] = useState(false);

  const [preferredUnavailable, setPreferredUnavailable] = useState(false);

  const submit = async (preferredProviderUserId: number | null) => {
    if (!state.serviceId) return;
    const res = await createBooking.mutateAsync({
      serviceId: state.serviceId,
      details: state.details,
      coordinates: state.coordinates,
      scheduling: state.scheduling,
      providerTypePreference: state.providerTypePreference,
      preferredProviderUserId,
      assignedProviderOrganizationId: state.assignedProviderOrganizationId,
    });
    dispatch({ type: 'RESET' });
    setShowSuccess(true);
    a11y.announce('Réservation confirmée avec succès');
    return res;
  };

  const handleConfirm = async () => {
    if (!state.serviceId) return;
    try {
      await submit(state.preferredProviderUserId);
    } catch (e: unknown) {
      // SP2 — preferred provider unavailable flow (parity with web). If the backend
      // signals the chosen provider can't take this slot and we did request one,
      // offer the "I'm in a hurry → auto-match" escape hatch instead of a raw error.
      const code = (e as { errorCode?: string } | undefined)?.errorCode;
      if (state.preferredProviderUserId && code === 'preferred_provider_unavailable') {
        setPreferredUnavailable(true);
        a11y.announce('Le prestataire choisi est indisponible');
        return;
      }
      const message = e instanceof Error ? e.message : 'Impossible de créer la réservation.';
      Alert.alert('Erreur', message);
    }
  };

  // "Je suis pressé" — drop the preferred provider and let the backend auto-match.
  const handleBookAnyProvider = async () => {
    dispatch({ type: 'SET_PREFERRED_PROVIDER', preferredProviderUserId: null });
    setPreferredUnavailable(false);
    try {
      await submit(null);
    } catch (e: unknown) {
      const message = e instanceof Error ? e.message : 'Impossible de créer la réservation.';
      Alert.alert('Erreur', message);
    }
  };

  const handleDismissSuccess = () => {
    setShowSuccess(false);
    navigation.getParent()?.goBack();
  };

  return (
    <>
      <Screen scroll>
        <ProgressBar step={6} totalSteps={6} />
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
          <Divider />
          <Row label="Type de prestataire" value={providerTypeLabel(state.providerTypePreference)} />
          {state.preferredProviderUserId ? (
            <>
              <Divider />
              <Row label="Prestataire choisi" value={`#${state.preferredProviderUserId}`} />
            </>
          ) : null}
          {state.assignedProviderOrganizationId ? (
            <>
              <Divider />
              <Row label="Société choisie" value={`#${state.assignedProviderOrganizationId}`} />
            </>
          ) : null}
        </View>

        {preferredUnavailable ? (
          <View style={styles.unavailableCard}>
            <Text style={styles.unavailableTitle}>
              Le prestataire choisi est indisponible pour ce créneau.
            </Text>
            <Text style={styles.unavailableText}>
              Vous pouvez patienter et réessayer un autre créneau, ou laisser notre système
              vous trouver le meilleur prestataire disponible.
            </Text>
            <Button
              label="Je suis pressé — n’importe quel prestataire disponible"
              onPress={handleBookAnyProvider}
              loading={createBooking.isPending}
              disabled={createBooking.isPending}
              fullWidth
            />
          </View>
        ) : null}

        <Button
          label={createBooking.isPending ? 'Envoi…' : 'Confirmer la réservation'}
          onPress={handleConfirm}
          loading={createBooking.isPending}
          disabled={createBooking.isPending}
          fullWidth
          size="lg"
        />
      </Screen>
      <SuccessOverlay
        visible={showSuccess}
        message="Votre demande a été envoyée. Un prestataire sera assigné bientôt."
        onDismiss={handleDismissSuccess}
      />
    </>
  );
}

function providerTypeLabel(pref: string): string {
  switch (pref) {
    case 'independent':
      return 'Indépendant';
    case 'company':
      return 'Société';
    default:
      return 'Peu importe';
  }
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
  unavailableCard: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.warning[500],
    backgroundColor: colors.warning[50],
    padding: spacing.md,
    gap: spacing.sm,
    marginBottom: spacing.lg,
  },
  unavailableTitle: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.bold,
    color: colors.warning[700],
  },
  unavailableText: {
    fontSize: typography.fontSize.sm,
    color: colors.warning[700],
  },
});
