import React, { useState } from 'react';
import { View, Text, Alert, StyleSheet } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useNavigation } from '@react-navigation/native';
import { Screen, Button, Badge, Divider, TextInput } from '@/ui';
import { useMissionDetail, useMissionLifecycle, missionStatusLabel } from '@/missions';
import { useArriveOnSite } from '@/tracking';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'MissionDetail'>;

export function MissionDetailScreen({ route }: Props) {
  const { missionId } = route.params;
  const { data: mission, isLoading } = useMissionDetail(missionId);
  const lifecycle = useMissionLifecycle(missionId);
  const arriveOnSite = useArriveOnSite(mission?.booking_id ?? null, missionId);
  const navigation = useNavigation<any>();
  const themeColors = useThemeColors();

  // Code de début, communiqué au client par SMS à l'arrivée du prestataire : c'est lui qui
  // atteste la présence sur place. Sans lui le serveur refuse le démarrage.
  //
  // Déclaré AVANT le retour anticipé de chargement : placé après, le hook n'existait pas au
  // premier rendu puis apparaissait au second, ce que React refuse — « Rendered more hooks than
  // during the previous render », et l'écran plantait.
  const [startCode, setStartCode] = useState('');

  if (isLoading || !mission) {
    return (
      <Screen>
        <Text style={styles.loading}>Chargement...</Text>
      </Screen>
    );
  }

  const handleAction = (action: 'start' | 'arrive' | 'complete', label: string) => {
    Alert.alert(label, `Confirmer "${label}" ?`, [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Confirmer', onPress: () => lifecycle.mutate(action) },
    ]);
  };

  /**
   * Annonce l'arrivée puis ouvre la preuve de présence.
   *
   * Idempotent de bout en bout : l'arrivée de la mission échoue en silence si elle a déjà eu
   * lieu, la session de suivi est réutilisée si elle existe. Le même geste sert donc à annoncer
   * l'arrivée ET à revenir au scanner depuis une mission déjà `arrived` — sans quoi quitter
   * l'écran laisserait le prestataire sans aucun chemin vers le code du client.
   */
  const handleArrival = () => {
    if (mission.booking_id == null) return;

    arriveOnSite.mutate(undefined, {
      onSuccess: (session) => navigation.navigate('PresenceScan', { sessionId: session.id }),
      onError: (e: any) => Alert.alert('Impossible', e?.message ?? 'Réessayez.'),
    });
  };

  const badgeVariant =
    mission.status === 'completed'
      ? 'success'
      : mission.status === 'cancelled'
        ? 'danger'
        : 'brand';

  return (
    <Screen scroll>
      <View style={styles.header}>
        <Text style={styles.title}>{mission.service_name}</Text>
        <Badge label={missionStatusLabel(mission.status)} variant={badgeVariant} />
      </View>
      <View style={[styles.card, { backgroundColor: themeColors.card }]}>
        <DetailRow label="Client" value={mission.client_name} />
        <Divider />
        <DetailRow label="Adresse" value={`${mission.address}, ${mission.city}`} />
        <Divider />
        <DetailRow
          label="Date"
          value={`${mission.scheduled_date} à ${mission.scheduled_time}`}
        />
        {mission.total_price != null && (
          <>
            <Divider />
            <DetailRow label="Prix" value={`${mission.total_price} €`} />
          </>
        )}
      </View>
      <View style={styles.actions}>
        {mission.status === 'assigned' && (
          <Button
            label="En route"
            onPress={() => handleAction('start', 'En route')}
            fullWidth
          />
        )}
        {mission.status === 'en_route' && (
          <>
            {/* Le suivi vit sur la RÉSERVATION, pas sur la mission : c'est elle qui porte la
                session GPS partagée avec le client. */}
            {mission.booking_id != null && (
              <Button
                label="Suivi GPS"
                onPress={() => navigation.navigate('MissionTracking', {
                  missionId,
                  bookingId: mission.booking_id as number,
                })}
                variant="secondary"
                fullWidth
              />
            )}
            {/* La géo-barrière fait basculer la session toute seule à 150 m : c'est une
                proximité, pas une présence. Ce geste-ci ouvre la preuve à scanner chez le
                client. */}
            <Button
              label="Je suis arrivé"
              onPress={handleArrival}
              loading={arriveOnSite.isPending}
              fullWidth
            />
          </>
        )}
        {mission.status === 'arrived' && (
          <>
            {/* Le scan reste atteignable après l'arrivée : le prestataire qui quitte l'écran
                doit pouvoir y revenir tant que la présence n'est pas confirmée. */}
            <Button
              label="Confirmer ma présence"
              onPress={handleArrival}
              loading={arriveOnSite.isPending}
              fullWidth
            />
            <TextInput
              label="Code de début (donné au client par SMS)"
              value={startCode}
              onChangeText={setStartCode}
              keyboardType="number-pad"
              maxLength={6}
              placeholder="000000"
            />
            {/* `begin`, PAS `start` : `start` appelle setEnRoute côté serveur, et depuis
                `arrived` cette transition est invalide — l'ancien bouton recevait un 422. */}
            <Button
              label="Démarrer mission"
              onPress={() =>
                startCode.length === 6
                  ? lifecycle.mutate({ action: 'begin', code: startCode })
                  : Alert.alert('Code requis', 'Demandez au client le code à six chiffres reçu par SMS.')
              }
              fullWidth
            />
          </>
        )}
        {/* `started`, PAS `in_progress` : ce dernier n'existe dans aucun statut du backend
            (MissionStatus), si bien qu'une mission démarrée n'affichait AUCUNE action — le
            prestataire ne pouvait ni ouvrir la mission terrain ni la clôturer. */}
        {mission.status === 'started' && (
          <>
            <Button
              label="Mission terrain"
              onPress={() => navigation.navigate('MissionField', { missionId })}
              fullWidth
            />
            <Button
              label="Mission terminée"
              onPress={() => handleAction('complete', 'Terminer')}
              variant="danger"
              fullWidth
            />
          </>
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
  },
  card: {
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
