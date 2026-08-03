import React, { useState, useCallback, useEffect } from 'react';
import { View, Text, StyleSheet, Platform } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Button, Badge } from '@/ui';
import { useMissionDetail } from '@/missions';
import { useGpsWatcher, useSendPing, useStartTracking, useMarkInMission, haversineMeters, formatDistance } from '@/tracking';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'MissionTracking'>;

interface Position {
  latitude: number;
  longitude: number;
  speed: number | null;
  heading: number | null;
}

const GEOFENCE_METERS = 150;

export function TrackingScreen({ route }: Props) {
  const styles = stylesFor(useThemeColors());

  // Deux identifiants distincts : le détail vient de la mission, la session de suivi de la
  // RÉSERVATION. Le même nombre était passé aux deux, ce qui n'aurait ouvert la bonne session
  // que par hasard.
  const { missionId, bookingId } = route.params;
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { data: mission } = useMissionDetail(missionId);

  const startTracking = useStartTracking(bookingId);
  const [sessionId, setSessionId] = useState<number | null>(null);
  const sendPing = useSendPing(sessionId);
  const markInMission = useMarkInMission(sessionId);
  const [currentPos, setCurrentPos] = useState<Position | null>(null);
  const [distanceMeters, setDistanceMeters] = useState<number | null>(null);
  const [etaMinutes, setEtaMinutes] = useState<number | null>(null);
  const [isNearDestination, setIsNearDestination] = useState(false);

  useEffect(() => {
    startTracking.mutate(undefined, {
      onSuccess: (session) => setSessionId(session.id),
    });
  }, []);

  const updateDistance = useCallback(
    (pos: Position) => {
      if (!mission?.latitude || !mission?.longitude) return;
      const dist = haversineMeters(
        pos.latitude,
        pos.longitude,
        mission.latitude,
        mission.longitude,
      );
      setDistanceMeters(dist);
      setIsNearDestination(dist <= GEOFENCE_METERS);
      const speedMps = pos.speed ?? 11.11;
      setEtaMinutes(Math.ceil(dist / (speedMps * 60)));
    },
    [mission],
  );

  useGpsWatcher(
    true,
    useCallback(
      (pos) => {
        setCurrentPos(pos);
        updateDistance(pos);
        if (sessionId !== null) {
          sendPing.mutate({
            latitude: pos.latitude,
            longitude: pos.longitude,
            speed: pos.speed ?? undefined,
            heading: pos.heading ?? undefined,
          });
        }
      },
      [sessionId, sendPing, updateDistance],
    ),
  );

  /**
   * Annonce le démarrage de l'intervention, puis enchaîne sur la preuve de présence.
   *
   * Ce bouton passait par le cycle de vie des `missions`, une table qu'aucun parcours ne
   * remplit — il ne pouvait donc pas aboutir. C'est la session de suivi qui porte réellement
   * l'état, et elle existe dès le départ du prestataire.
   *
   * La géo-barrière a déjà pu faire basculer la session en `arrived` toute seule : elle atteste
   * d'une proximité, pas d'une présence. Le scan qui suit, lui, exige les deux appareils au même
   * endroit.
   */
  const handleArrived = useCallback(() => {
    if (sessionId === null) return;

    markInMission.mutate(undefined, {
      onSuccess: () => navigation.navigate('PresenceScan', { sessionId }),
    });
  }, [markInMission, navigation, sessionId]);

  const formatSpeed = (mps: number | null): string => {
    if (mps === null) return '—';
    return `${Math.round(mps * 3.6)} km/h`;
  };

  return (
    <Screen scroll testID="tracking-screen">
      <View style={styles.header}>
        <Text style={styles.title}>En route</Text>
        {mission && <Badge label={mission.status} variant="brand" />}
      </View>

      {/* Destination */}
      {mission && (
        <View style={styles.destinationCard}>
          <Text style={styles.cardLabel}>Destination</Text>
          <Text style={styles.destinationAddress}>
            {mission.address}, {mission.city}
          </Text>
          <Text style={styles.missionService}>{mission.service_name}</Text>
        </View>
      )}

      {/* Map placeholder — real MapView requires expo-maps or react-native-maps */}
      <View style={styles.mapPlaceholder}>
        <Text style={styles.mapPlaceholderText}>
          {currentPos
            ? `Position: ${currentPos.latitude.toFixed(5)}, ${currentPos.longitude.toFixed(5)}`
            : 'Acquisition GPS...'}
        </Text>
      </View>

      {/* Stats row */}
      <View style={styles.statsRow}>
        <View style={styles.statCard}>
          <Text style={styles.statLabel}>Distance</Text>
          <Text style={styles.statValue}>
            {distanceMeters !== null ? formatDistance(distanceMeters) : '—'}
          </Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statLabel}>ETA</Text>
          <Text style={styles.statValue}>
            {etaMinutes !== null ? `${etaMinutes} min` : '—'}
          </Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statLabel}>Vitesse</Text>
          <Text style={styles.statValue}>{formatSpeed(currentPos?.speed ?? null)}</Text>
        </View>
      </View>

      {/* Arrived button — enabled when within geofence */}
      <View style={styles.actions}>
        <Button
          label={isNearDestination ? 'Je suis arrivé' : `Je suis arrivé (${distanceMeters !== null ? formatDistance(distanceMeters) : '?'} restants)`}
          onPress={handleArrived}
          fullWidth
          size="lg"
          disabled={sessionId === null}
          loading={markInMission.isPending}
          variant={isNearDestination ? 'primary' : 'secondary'}
        />
        {!isNearDestination && (
          <Text style={styles.geofenceHint}>
            Le bouton devient actif dans les {GEOFENCE_METERS} m de la destination
          </Text>
        )}
      </View>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: spacing.md,
    marginBottom: spacing.md,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  destinationCard: {
    backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.xs,
    marginBottom: spacing.md,
  },
  cardLabel: { fontSize: typography.fontSize.xs, color: t.textMuted, marginBottom: 2 },
  destinationAddress: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  missionService: { fontSize: typography.fontSize.sm, color: colors.brand[600], marginTop: 2 },
  mapPlaceholder: {
    height: 200,
    backgroundColor: t.inputBg,
    borderRadius: radius.md,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: spacing.md,
    borderWidth: 1,
    borderColor: t.border,
  },
  mapPlaceholderText: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    textAlign: 'center',
    paddingHorizontal: spacing.md,
  },
  statsRow: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginBottom: spacing.lg,
  },
  statCard: {
    flex: 1,
    backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.md,
    alignItems: 'center',
    ...shadows.xs,
  },
  statLabel: { fontSize: typography.fontSize.xs, color: t.textSecondary, marginBottom: 4 },
  statValue: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    fontVariant: ['tabular-nums'],
  },
  actions: { gap: spacing.sm },
  geofenceHint: {
    fontSize: typography.fontSize.xs,
    color: t.textMuted,
    textAlign: 'center',
  },
});
