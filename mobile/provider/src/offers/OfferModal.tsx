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
import type { MissionOffer } from './types';

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
export function OfferModal({ offer, onDismiss }: Props) {
  const theme = useThemeColors();
  const styles = stylesFor(theme);
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const accept = useAcceptOffer();
  const decline = useDeclineOffer();
  const secondsLeft = useServerCountdown(offer.expires_at);
  const closedRef = useRef(false);

  // La vibration remplace le regard : le téléphone est dans une poche, sur un tableau de bord, ou
  // à côté d'une perceuse. Une modale silencieuse expire sans que personne ne l'ait vue.
  useEffect(() => {
    try {
      Vibration.vibrate([0, 400, 200, 400]);
    } catch {
      // Un appareil sans vibreur ne doit pas empêcher l'offre de s'afficher.
    }
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
          <View style={styles.timerTrack}>
            <View style={[styles.timerBar, { width: `${ratio * 100}%` }]} testID="offer-timer-bar" />
          </View>
          <Text style={styles.timerText} testID="offer-countdown">
            {secondsLeft} s pour répondre
          </Text>

          <Text style={styles.heading}>Nouvelle mission</Text>
          <Text style={styles.trade} testID="offer-trade">
            {offer.trade_name ?? offer.service_name ?? 'Intervention'}
          </Text>

          <View style={styles.details}>
            <View style={styles.row}>
              <Text style={styles.label}>Distance</Text>
              <Text style={styles.value} testID="offer-distance">
                {offer.distance_km != null ? `${offer.distance_km} km` : '—'}
              </Text>
            </View>
            <Divider />
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
    timerTrack: {
      height: 6,
      backgroundColor: t.border,
      borderRadius: radius.pill,
      overflow: 'hidden',
      marginBottom: spacing.xs,
    },
    timerBar: { height: '100%', backgroundColor: colors.warning[500], borderRadius: radius.pill },
    timerText: {
      fontSize: typography.fontSize.xs,
      color: t.textSecondary,
      textAlign: 'right',
      marginBottom: spacing.md,
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
