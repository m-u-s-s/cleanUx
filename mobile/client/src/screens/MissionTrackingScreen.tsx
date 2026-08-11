import React, { useRef, useEffect, useMemo } from 'react';
import { View, Text, StyleSheet, Dimensions } from 'react-native';
import { Screen, Badge, Button, Skeleton, OsmMap, loadMapModule, isMapRenderable } from '@/ui';
import { useTrackingSession, useTrackingTrail, useLiveTracking } from '@/tracking';
import { useOnSiteTimeline } from '@/booking/onsite';
import { PresenceCodeCard } from '@/screens/components/PresenceCodeCard';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'MissionTracking'>;

/**
 * Le statut de session est un identifiant technique anglais ; l'application est en français.
 * Il s'affichait tel quel dans la pastille, faute de traduction.
 */
const STATUS_LABELS: Record<string, string> = {
  enroute: 'En route',
  arrived: 'Sur place',
  in_mission: 'En cours',
  ended: 'Terminée',
  cancelled: 'Annulée',
};

export function MissionTrackingScreen({ route, navigation }: Props) {
  const styles = stylesFor(useThemeColors());

  const { bookingId } = route.params;
  const { data: session, isLoading } = useTrackingSession(bookingId);
  const { data: trail } = useTrackingTrail(bookingId);
  /*
   * L'IDENTIFIANT DE MISSION, ET NON CELUI DE LA RÉSERVATION.
   *
   * Le canal temps réel est indexé sur la mission ; on s'y abonnait avec le numéro de réservation.
   * Tant que les deux compteurs coïncidaient l'erreur restait invisible — et dès qu'ils divergent,
   * on écoute l'intervention de quelqu'un d'autre, ce que l'autorisation de canal refuse en
   * silence. Le fil « sur place » est le seul endroit d'où le client obtient ce numéro.
   */
  const { data: filSurPlace } = useOnSiteTimeline(bookingId);
  const { position: livePos, eta: liveEta } = useLiveTracking(filSurPlace?.mission_id ?? null);
  const mapRef = useRef<any>(null);

  /**
   * Le module natif n'est chargé QUE si la carte peut réellement s'afficher. C'est la correction
   * centrale : `react-native-maps` était importé statiquement, donc monté quoi qu'il arrive. Sur
   * Android, sans clé Google Maps dans le manifeste, il ne dégrade pas — il lève
   * `IllegalStateException` et emporte l'écran entier.
   */
  const mapModule = useMemo(() => (isMapRenderable() ? loadMapModule() : null), []);

  // Par fraîcheur décroissante : le temps réel, puis la dernière position enregistrée par le
  // serveur — qui existe dès le premier relevé, quand la trace peut encore être vide.
  const lastPoint = trail && trail.length > 0 ? trail[trail.length - 1] : null;
  const currentPos = livePos ?? session?.provider ?? lastPoint;
  const etaMinutes = liveEta?.eta_minutes ?? session?.eta_minutes;
  // Deux moments demandent un code au client, dans cet ordre : prouver que le prestataire est
  // bien là, puis attester que le travail est fait. Le second n'a de sens qu'après le premier.
  const inMission = session?.status === 'in_mission';
  const awaitingPresence = inMission && !session.presence_confirmed_at;
  const awaitingCompletion = inMission && !!session.presence_confirmed_at;
  // La distance restante n'est pas portée par la session : elle est relevée point par point.
  const distanceKm = liveEta?.distance_km
    ?? (lastPoint?.distance_to_dest_m != null ? lastPoint.distance_to_dest_m / 1000 : undefined);

  // Initial region only — updates happen via animateToRegion
  const initialRegion = useMemo(() => {
    if (!currentPos) {
      return { latitude: 48.8566, longitude: 2.3522, latitudeDelta: 0.05, longitudeDelta: 0.05 };
    }
    return {
      latitude: currentPos.latitude,
      longitude: currentPos.longitude,
      latitudeDelta: 0.01,
      longitudeDelta: 0.01,
    };
  }, []); // intentionally empty — only for initial render

  // Smooth camera follow instead of prop re-render
  useEffect(() => {
    // Le repli OpenStreetMap n'expose pas d'API impérative : il se recadre seul sur ses points.
    // `animateToRegion` est une API impérative du module natif : on vérifie qu'elle existe avant
    // de l'appeler, plutôt que de supposer la forme d'un module chargé dynamiquement.
    if (currentPos && mapModule && typeof mapRef.current?.animateToRegion === 'function') {
      mapRef.current.animateToRegion(
        {
          latitude: currentPos.latitude,
          longitude: currentPos.longitude,
          latitudeDelta: 0.01,
          longitudeDelta: 0.01,
        },
        500,
      );
    }
  }, [currentPos?.latitude, currentPos?.longitude]);

  if (isLoading) {
    return (
      <Screen>
        <Skeleton width="100%" height={300} />
        <Skeleton width="100%" height={80} />
      </Screen>
    );
  }

  return (
    <View style={styles.container}>
      {mapModule ? (
        <mapModule.MapView
          ref={mapRef}
          style={styles.map}
          initialRegion={initialRegion}
          showsUserLocation
        >
          {currentPos && (
            <mapModule.Marker
              coordinate={{ latitude: currentPos.latitude, longitude: currentPos.longitude }}
              title="Prestataire"
              pinColor={colors.brand[500]}
            />
          )}
          {/* Le trajet parcouru : c'est lui qui rend le suivi lisible, pas le point seul. */}
          {mapModule.Polyline && trail && trail.length > 1 && (
            <mapModule.Polyline
              coordinates={trail.map(p => ({ latitude: p.latitude, longitude: p.longitude }))}
              strokeWidth={3}
              strokeColor={colors.brand[400]}
            />
          )}
        </mapModule.MapView>
      ) : (
        // Repli sans clé : OpenStreetMap en WebView, qui n'en exige aucune. Le trajet y est
        // tracé aussi — sur un écran de suivi, un point seul ne montrerait rien d'utile.
        <View style={styles.map}>
          <OsmMap
            markers={currentPos ? [{
              id: bookingId,
              latitude: currentPos.latitude,
              longitude: currentPos.longitude,
              title: 'Prestataire',
            }] : []}
            position={currentPos ? { latitude: currentPos.latitude, longitude: currentPos.longitude } : null}
            trail={trail?.map(p => ({ latitude: p.latitude, longitude: p.longitude }))}
            fallbackCenter={{
              latitude: initialRegion.latitude,
              longitude: initialRegion.longitude,
              zoom: 13,
            }}
            onMarkerPress={() => undefined}
            testID="mission-tracking-map-osm"
          />
        </View>
      )}

      {/*
        LE BAS DE L'ÉCRAN EST UNE PILE, PAS UN CHOIX.

        Le code de présence REMPLAÇAIT l'encart d'information — ce qui est juste, le trajet
        n'apprenant plus rien une fois la porte ouverte. Mais poser le lien vers le déroulé DANS
        l'encart le faisait disparaître exactement pendant l'intervention : le seul moment où ce
        lien sert. La pile garde les deux, dans l'ordre de ce qu'on cherche à cet instant.
      */}
      <View style={styles.basDEcran}>
        {awaitingPresence || awaitingCompletion ? (
          <PresenceCodeCard
            bookingId={bookingId}
            purpose={awaitingCompletion ? 'completion' : 'presence'}
          />
        ) : (
          <View style={styles.infoCard}>
            <View style={styles.infoRow}>
              <View>
                <Text style={styles.etaLabel}>ETA</Text>
                <Text style={styles.etaValue}>
                  {etaMinutes != null ? `${Math.round(etaMinutes)} min` : '—'}
                </Text>
              </View>
              <View>
                <Text style={styles.etaLabel}>Distance</Text>
                <Text style={styles.etaValue}>
                  {distanceKm != null ? `${distanceKm.toFixed(1)} km` : '—'}
                </Text>
              </View>
              {session?.status && (
                <Badge
                  label={STATUS_LABELS[session.status] ?? session.status}
                  variant={session.status === 'in_mission' ? 'success' : 'brand'}
                />
              )}
            </View>
          </View>
        )}
        <Button
          label="Voir le déroulé de l’intervention"
          onPress={() => navigation.navigate('OnSite', { bookingId })}
          variant="secondary"
          size="sm"
          fullWidth
        />
      </View>
    </View>
  );
}

const { height } = Dimensions.get('window');

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { flex: 1 },
  map: { flex: 1, minHeight: height * 0.6 },
  // Le pouce est en bas de l'écran : c'est là que vivent le code à montrer et le lien à suivre.
  basDEcran: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    padding: spacing.md,
    gap: spacing.sm,
  },
  /*
   * Plus positionnée elle-même : c'est `basDEcran` qui ancre désormais le bas de l'écran. Une
   * vue absolue imbriquée dans une autre se recale sur son parent et se serait superposée au lien
   * qui la suit.
   */
  infoCard: {
    backgroundColor: 'rgba(255, 255, 255, 0.92)',
    borderRadius: radius.xl,
    padding: spacing.lg,
    ...shadows.lg,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  etaLabel: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
  },
  etaValue: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginTop: 2,
  },
});
