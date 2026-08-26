import React, { useState, useCallback, useEffect, useMemo } from 'react';
import { View, Text, StyleSheet, Platform } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Button, Badge } from '@/ui';
import { useMissionDetail } from '@/missions';
import { useGpsWatcher, useStartTracking, useMarkInMission, haversineMeters, formatDistance } from '@/tracking';
import type { TrackingSession } from '@/tracking/hooks';
import { OsmMap } from '@/maps';
import type { OsmMarker } from '@/maps';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { formatAdresse } from '@brio/shared/format';

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
  const [session, setSession] = useState<TrackingSession | null>(null);
  const markInMission = useMarkInMission(sessionId);
  const [currentPos, setCurrentPos] = useState<Position | null>(null);
  const [distanceMeters, setDistanceMeters] = useState<number | null>(null);
  const [etaMinutes, setEtaMinutes] = useState<number | null>(null);
  const [isNearDestination, setIsNearDestination] = useState(false);

  useEffect(() => {
    startTracking.mutate(undefined, {
      onSuccess: (ouverte) => {
        setSessionId(ouverte.id);
        setSession(ouverte);
      },
    });
  }, []);

  /*
   * Le tracé et la destination viennent de la SESSION quand elle les porte, de la mission sinon.
   *
   * L'ordre compte : sur une course, la session sait que la destination est devenue le point de
   * dépose, alors que la mission continue de désigner le lieu de prise en charge. Lire la mission
   * d'abord afficherait un drapeau à l'endroit qu'on vient de quitter.
   */
  const destinationMarker: OsmMarker | null = useMemo(() => {
    const lat = session?.destination?.lat ?? mission?.latitude ?? null;
    const lng = session?.destination?.lng ?? mission?.longitude ?? null;

    if (lat === null || lng === null) return null;

    return {
      id: 1,
      latitude: Number(lat),
      longitude: Number(lng),
      title: 'Destination',
      subtitle: mission ? formatAdresse(mission.address, mission.city) : null,
    };
  }, [session, mission]);

  const routeTrail = useMemo(
    () => (session?.route?.points ?? []).map((p) => ({ latitude: p.lat, longitude: p.lng })),
    [session],
  );

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

  /*
   * CET ÉCRAN REGARDE, IL N'ÉMET PLUS.
   *
   * Il était le SEUL endroit de l'application où les relevés partaient — derrière un bouton
   * « Suivi GPS » que le prestataire devait penser à presser et garder ouvert, en conduisant. Le
   * relevé vit désormais dans `TripTrackingHost`, monté avec les onglets : il suit la mission, pas
   * l'écran affiché.
   *
   * L'observateur reste ici pour la distance et l'estimation montrées AU PRESTATAIRE. Il n'envoie
   * plus : deux émetteurs sur la même session doubleraient les points sans rien apprendre de plus.
   */
  useGpsWatcher(
    true,
    useCallback(
      (pos) => {
        setCurrentPos(pos);
        updateDistance(pos);
      },
      [updateDistance],
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
            {formatAdresse(mission.address, mission.city)}
          </Text>
          <Text style={styles.missionService}>{mission.service_name}</Text>
        </View>
      )}

      {/*
        LA CARTE, ENFIN.

        Cet écran affichait deux nombres à cinq décimales là où un conducteur attend un trait qui
        lui dit où aller. `OsmMap` est déjà partagé avec l'application cliente : tuiles
        OpenStreetMap, aucune clé à facturer, et il sait tracer une polyligne — ce qui est
        exactement ce qu'il fallait pour montrer la route vers le point d'arrivée.

        La destination vient de la SESSION, pas de la mission : sur une course, elle bascule du
        point de prise en charge vers le point de dépose au moment où le client monte, et c'est ce
        mouvement-là qu'il faut suivre.
      */}
      <View style={styles.mapFrame}>
        <OsmMap
          markers={destinationMarker ? [destinationMarker] : []}
          position={currentPos ? { latitude: currentPos.latitude, longitude: currentPos.longitude } : null}
          trail={routeTrail}
          fallbackCenter={{ latitude: 50.8467, longitude: 4.3525, zoom: 12 }}
          onMarkerPress={() => {}}
          testID="tracking-map"
        />
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
  missionService: { fontSize: typography.fontSize.sm, color: t.brandText, marginTop: 2 },
  /*
   * `overflow: hidden` avec un rayon : sans lui, les tuiles de la carte débordent des coins
   * arrondis sur Android, et le cadre paraît cassé alors qu'il ne l'est pas.
   */
  mapFrame: {
    height: 240,
    borderRadius: radius.md,
    overflow: 'hidden',
    marginBottom: spacing.md,
    borderWidth: 1,
    borderColor: t.border,
    backgroundColor: t.inputBg,
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
