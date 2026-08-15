import React, { useCallback, useEffect, useRef } from 'react';
import { Alert, Modal, StyleSheet, Text, Vibration, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, Divider } from '@/ui';
import { spacing, typography, radius, colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { useAcceptOffer, useDeclineOffer, useServerCountdown } from './hooks';
import { jouerCarillonDOffre } from './sound';
import { AnneauDeDecompte } from './CountdownRing';
import type { MissionOffer } from './types';
import { formatDelai } from '@brio/shared/format';

interface Props {
  offer: MissionOffer;
  onDismiss: (assignmentId: number) => void;
}

/**
 * LA MODALE D'OFFRE — plein écran, par-dessus tout, vingt secondes.
 *
 * C'est le patron VTC et il ne se discute pas : une offre qui arrive dans une liste qu'il faut
 * penser à ouvrir n'est pas une offre, c'est une archive. Elle se pose donc AU-DESSUS de l'écran
 * courant, quel qu'il soit.
 *
 * LE COMPTE À REBOURS EST CELUI DU SERVEUR. `expires_at` est la seule référence : un décompte parti
 * de vingt à la réception du message afficherait encore six secondes sur une offre que le serveur a
 * déjà passée au suivant. Quand il atteint zéro, la modale se ferme d'elle-même — le serveur, lui,
 * a déjà escaladé.
 *
 * REFUSER NE DEMANDE PAS DE CONFIRMATION. Vingt secondes ne laissent pas la place à une boîte de
 * dialogue : la demander ferait expirer l'offre pendant qu'on la lit, ce qui compte comme un
 * silence et non comme un refus dans le taux d'acceptation.
 */
/**
 * La date de l'intervention, lisible d'un coup d'œil : « lun. 24 août à 10h00 ».
 *
 * Une chaîne ISO illisible vaut mieux qu'un plantage : si la date ne se parse pas, on ne montre
 * rien plutôt que « Invalid Date » sur une modale qui expire en vingt secondes.
 */
function formatQuand(iso: string): string {
  const date = new Date(iso);

  if (Number.isNaN(date.getTime())) {
    return '—';
  }

  return date
    .toLocaleString('fr-BE', {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    })
    .replace(':', 'h');
}

export function OfferModal({ offer, onDismiss }: Props) {
  const theme = useThemeColors();
  const styles = stylesFor(theme);
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const accept = useAcceptOffer();
  const decline = useDeclineOffer();
  const secondsLeft = useServerCountdown(offer.expires_at);
  const closedRef = useRef(false);

  /*
   * SON ET VIBRATION, ENSEMBLE — le téléphone est dans une poche, sur un tableau de bord, ou à côté
   * d'une perceuse. Chacun couvre l'angle mort de l'autre : le vibreur ne s'entend pas dans une
   * sacoche, le son ne se sent pas en mode silencieux.
   *
   * Quand l'offre arrive par NOTIFICATION, le système a déjà sonné. Mais au premier plan elle
   * arrive par le canal temps réel ou par sondage, sans notification et donc sans aucun son : c'est
   * le cas où le prestataire a l'application ouverte, et le plus susceptible d'accepter.
   */
  useEffect(() => {
    try {
      Vibration.vibrate([0, 400, 200, 400]);
    } catch {
      // Un appareil sans vibreur ne doit pas empêcher l'offre de s'afficher.
    }

    jouerCarillonDOffre();
  }, [offer.assignment_id]);

  useEffect(() => {
    if (secondsLeft <= 0 && offer.expires_at && !closedRef.current) {
      closedRef.current = true;
      onDismiss(offer.assignment_id);
    }
  }, [secondsLeft, offer.expires_at, offer.assignment_id, onDismiss]);

  const handleAccept = useCallback(() => {
    accept.mutate(offer.assignment_id, {
      onSuccess: () => {
        onDismiss(offer.assignment_id);
        navigation.navigate('MissionDetail', { missionId: offer.mission_id });
      },
      onError: () => {
        // 409 : quelqu'un a été plus rapide. Ce n'est PAS une panne, et le dire autrement ferait
        // croire à un bug de l'application.
        Alert.alert('Course déjà prise', 'Un autre professionnel a accepté avant vous.');
        onDismiss(offer.assignment_id);
      },
    });
  }, [accept, navigation, offer.assignment_id, offer.mission_id, onDismiss]);

  const handleDecline = useCallback(() => {
    decline.mutate(offer.assignment_id, {
      onSettled: () => onDismiss(offer.assignment_id),
    });
  }, [decline, offer.assignment_id, onDismiss]);

  const total = offer.ttl_seconds && offer.ttl_seconds > 0 ? offer.ttl_seconds : 20;
  const ratio = Math.max(0, Math.min(1, secondsLeft / total));

  return (
    <Modal
      visible
      animationType="slide"
      transparent
      testID="offer-modal"
      onRequestClose={() => onDismiss(offer.assignment_id)}
    >
      <View style={styles.backdrop}>
        <View style={styles.card}>
          <View style={styles.timerRow}>
            <AnneauDeDecompte ratio={ratio} secondes={secondsLeft} />
            {/*
              Un délai se LIT. « 1658 s pour répondre » — vu sur une offre planifiée — demande une
              division mentale ; sous la minute, les secondes restent la bonne unité, c'est là
              qu'elles pressent.
            */}
            <Text style={styles.timerText} testID="offer-countdown">
              {formatDelai(secondsLeft)} pour répondre
            </Text>
          </View>

          <Text style={styles.heading}>Nouvelle mission</Text>
          <Text style={styles.trade} testID="offer-trade">
            {offer.trade_name ?? offer.service_name ?? 'Intervention'}
          </Text>

          <View style={styles.details}>
            <View style={styles.row}>
              {/* Sur une course, dire DE QUELLE distance on parle. */}
              <Text style={styles.label}>{offer.is_ride ? 'Pour aller le chercher' : 'Distance'}</Text>
              <Text style={styles.value} testID="offer-distance">
                {offer.distance_km != null ? `${offer.distance_km} km` : '—'}
              </Text>
            </View>
            <Divider />
            {offer.is_ride && offer.ride_distance_km != null ? (
              <>
                <View style={styles.row}>
                  <Text style={styles.label}>Course</Text>
                  <Text style={styles.value} testID="offer-ride-distance">
                    {`${String(offer.ride_distance_km).replace('.', ',')} km`}
                    {offer.ride_duration_minutes != null ? ` · ${offer.ride_duration_minutes} min` : ''}
                  </Text>
                </View>
                <Divider />
              </>
            ) : null}
            {/* QUAND : sur une mission planifiée, la première question — et elle manquait. */}
            {offer.scheduled_at ? (
              <>
                <View style={styles.row}>
                  <Text style={styles.label}>Quand</Text>
                  <Text style={styles.value} testID="offer-scheduled-at">
                    {formatQuand(offer.scheduled_at)}
                  </Text>
                </View>
                <Divider />
              </>
            ) : null}
            <View style={styles.row}>
              <Text style={styles.label}>Secteur</Text>
              <Text style={styles.value}>{offer.approximate_address ?? '—'}</Text>
            </View>
            <Divider />
            <View style={styles.row}>
              <Text style={styles.label}>Rémunération</Text>
              <Text style={styles.value} testID="offer-payout">
                {offer.payout_cents != null
                  ? `${(offer.payout_cents / 100).toFixed(2).replace('.', ',')} €`
                  : 'À confirmer'}
              </Text>
            </View>
            {offer.estimated_duration_minutes != null && (
              <>
                <Divider />
                <View style={styles.row}>
                  <Text style={styles.label}>Durée estimée</Text>
                  <Text style={styles.value}>{offer.estimated_duration_minutes} min</Text>
                </View>
              </>
            )}
          </View>

          <View style={styles.actions}>
            <View style={styles.actionBtn}>
              <Button
                label="Accepter"
                onPress={handleAccept}
                fullWidth
                size="lg"
                loading={accept.isPending}
              />
            </View>
            <View style={styles.actionBtn}>
              <Button
                label="Refuser"
                onPress={handleDecline}
                variant="ghost"
                fullWidth
                size="lg"
              />
            </View>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    backdrop: {
      flex: 1,
      backgroundColor: 'rgba(0,0,0,0.72)',
      justifyContent: 'flex-end',
      padding: spacing.md,
      paddingBottom: spacing['2xl'],
    },
    card: {
      backgroundColor: t.card,
      borderRadius: radius.xl,
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: t.border,
      padding: spacing.lg,
    },
    timerRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      marginBottom: spacing.md,
    },
    timerText: {
      fontSize: typography.fontSize.xs,
      color: t.textSecondary,
      flex: 1,
    },
    heading: {
      fontSize: typography.fontSize.sm,
      color: t.textSecondary,
      textTransform: 'uppercase',
      letterSpacing: 1,
    },
    trade: {
      fontSize: typography.fontSize['2xl'],
      fontWeight: typography.fontWeight.bold,
      color: t.text,
      marginBottom: spacing.md,
    },
    details: {
      backgroundColor: t.cardSubtle,
      borderRadius: radius.md,
      padding: spacing.md,
      marginBottom: spacing.lg,
    },
    row: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      paddingVertical: spacing.sm,
    },
    label: { fontSize: typography.fontSize.sm, color: t.textSecondary },
    value: {
      fontSize: typography.fontSize.sm,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
      flex: 1,
      textAlign: 'right',
      marginLeft: spacing.sm,
    },
    actions: { flexDirection: 'row', gap: spacing.sm },
    actionBtn: { flex: 1 },
  });
