import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type {
  NativeStackNavigationProp,
  NativeStackScreenProps,
} from '@react-navigation/native-stack';
import { Screen, Button, Badge, Divider, DetailRow, EmptyState, ErrorState } from '@/ui';
import { useBookingDetail } from '@/booking';
import { useCompletionCode } from '@/tracking';
import type { CompletionCode } from '@/tracking';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { libelleStatut, formatDateHeure } from '@/lib/format';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'BookingDetail'>;

/**
 * Ce qu'on montre au client quand le code de fin est refusé.
 *
 * Le message du SERVEUR d'abord : lui seul sait pourquoi il refuse, et il le dit en français.
 * Ensuite le code HTTP, traduit. JAMAIS `error.message` : c'est le texte interne de la
 * bibliothèque HTTP, en anglais — « Request failed with status code 404 » s'affichait tel quel.
 */
function messageDeRefus(erreur: any): string {
  const duServeur = erreur?.response?.data?.message;

  if (typeof duServeur === 'string' && duServeur.trim() !== '') {
    return duServeur;
  }

  switch (erreur?.response?.status) {
    case 404:
      return 'Aucun code n’est disponible pour le moment : le prestataire n’a pas encore démarré l’intervention.';
    case 403:
      return 'Ce code ne vous est pas destiné.';
    case 429:
      return 'Trop de demandes coup sur coup. Réessayez dans une minute.';
    default:
      return 'Impossible d’obtenir le code pour le moment. Réessayez dans un instant.';
  }
}

export function BookingDetailScreen({ route }: Props) {
  const styles = stylesFor(useThemeColors());

  const { bookingId } = route.params;
  const { data: booking, isLoading, isError, refetch } = useBookingDetail(bookingId);
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  /*
   * LE CODE DE FIN, DEMANDABLE DEPUIS « MES RENDEZ-VOUS ».
   *
   * Le prestataire ne peut pas clôturer sans six chiffres que le client seul détient. Ils
   * n'existaient nulle part dans cet écran : le client devait espérer un SMS, que le plafond de
   * cinq envois par heure et par numéro bloque justement au moment où l'on en a besoin.
   *
   * Les hooks sont déclarés AVANT les retours anticipés de chargement et d'erreur : placés après,
   * ils n'existeraient pas au premier rendu puis apparaîtraient au second, ce que React refuse.
   */
  const demandeCode = useCompletionCode(bookingId);
  const [codeDeFin, setCodeDeFin] = React.useState<CompletionCode | null>(null);
  const [refusCode, setRefusCode] = React.useState<string | null>(null);

  const demanderLeCode = () => {
    setRefusCode(null);
    demandeCode.mutate(undefined, {
      onSuccess: setCodeDeFin,
      /*
       * LE REFUS DU SERVEUR EST AFFICHÉ QUAND IL EST LISIBLE — jamais celui d'axios.
       *
       * Le repli était `e.message`, c'est-à-dire le texte interne de la bibliothèque HTTP. Quand
       * le serveur répond 404 sans corps, le client lisait « Request failed with status code
       * 404 » — en rouge, en anglais, au milieu d'une app française. Vu à l'écran en déroulant le
       * parcours.
       *
       * On garde le message du serveur, qui sait pourquoi il refuse, et on traduit le silence.
       */
      onError: (e: any) => setRefusCode(messageDeRefus(e)),
    });
  };

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
        <Badge label={libelleStatut(booking.status)} variant={statusVariant} />
      </View>

      {booking.contract_covered ? (
        <View style={styles.badgeRow} testID="contract-coverage-badge">
          <Badge label="Couvert par votre contrat" variant="info" />
        </View>
      ) : null}

      <View style={styles.card}>
        <DetailRow
          label="Date"
          value={formatDateHeure(booking.scheduled_date, booking.scheduled_time)}
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

      {/*
        LE CODE DE FIN, ICI ET PAS AILLEURS.

        C'est cet écran que le client ouvre quand le prestataire lui demande ses six chiffres. La
        carte reste visible pendant toute la mission : le serveur, lui, sait si elle a démarré et
        refuse proprement sinon. Reconstruire cette condition ici serait faux — la réservation
        reste `confirme` pendant toute la durée de l'intervention.

        MAIS PAS SUR UNE DEMANDE QUE PERSONNE N'A ENCORE ACCEPTÉE. `pending` signifie qu'aucun
        prestataire n'est assigné : il n'existe alors ni mission, ni code, et le bouton ne pouvait
        que échouer. Ce n'est pas deviner la règle du serveur, c'est ne pas proposer un geste dont
        on sait qu'il n'a pas d'objet.
      */}
      {!isCompleted && booking.status !== 'cancelled' && booking.status !== 'pending' && (
        <View style={styles.card} testID="carte-code-de-fin">
          <Text style={styles.codeTitre}>Code de fin</Text>

          {codeDeFin ? (
            <>
              <Text style={styles.codeChiffres}>{codeDeFin.code}</Text>
              <Text style={styles.codeAide}>
                Donnez ce code au prestataire pour qu’il puisse clôturer la mission.
              </Text>
              <Button
                label="En générer un nouveau"
                onPress={demanderLeCode}
                loading={demandeCode.isPending}
                variant="secondary"
                fullWidth
              />
            </>
          ) : (
            <>
              <Text style={styles.codeAide}>
                Le prestataire a besoin de six chiffres pour clôturer. Affichez-les au moment où il
                vous les demande : ils ne restent valables que vingt minutes.
              </Text>
              <Button
                label="Afficher mon code de fin"
                onPress={demanderLeCode}
                loading={demandeCode.isPending}
                fullWidth
              />
            </>
          )}

          {refusCode ? <Text style={styles.codeRefus}>{refusCode}</Text> : null}
        </View>
      )}

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
  codeTitre: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    marginBottom: spacing.xs,
  },
  codeChiffres: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    letterSpacing: 8,
    color: t.text,
    marginVertical: spacing.sm,
  },
  codeAide: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    marginBottom: spacing.sm,
  },
  codeRefus: {
    fontSize: typography.fontSize.sm,
    color: colors.danger[600],
    marginTop: spacing.sm,
  },
});
