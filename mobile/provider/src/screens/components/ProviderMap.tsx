import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { Button } from '@/ui';
import { useMissionInbox } from '@/missions';
import { useGpsWatcher, distanceKmTo, formatDistance } from '@/tracking';
import { loadMapModule } from '@/maps';
import { colors, spacing, typography, radius } from '@/theme';

/** Repli d'échelle pays centré sur Bruxelles, marché principal du projet. */
export const FALLBACK_REGION = {
  latitude: 50.85,
  longitude: 4.35,
  latitudeDelta: 2,
  longitudeDelta: 2,
};

type Position = { latitude: number; longitude: number };

export function ProviderMap() {
  const navigation = useNavigation<any>();
  const maps = useMemo(() => loadMapModule(), []);
  const mapRef = useRef<any>(null);
  // `hasCenteredRef` : « on a déjà centré sur quelque chose » (position OU mission).
  // `hasCenteredOnPositionRef` : « on a déjà centré sur une vraie position GPS ». Deux
  // drapeaux distincts pour qu'une position GPS arrivant après un centrage sur mission
  // puisse quand même déclencher un recentrage — un centrage « mission » ne doit pas
  // empêcher le seul recentrage qui compte vraiment (la position réelle du prestataire).
  const hasCenteredRef = useRef(false);
  const hasCenteredOnPositionRef = useRef(false);
  const [position, setPosition] = useState<Position | null>(null);
  const { permission } = useGpsWatcher(
    true,
    useCallback((pos) => setPosition({ latitude: pos.latitude, longitude: pos.longitude }), []),
  );
  const { data: assignments, isError, refetch } = useMissionInbox();

  // Mémoïsé sur `assignments` : sans cela, `located` serait un tableau neuf à chaque
  // rendu, ce qui ferait tourner l'effet de centrage sur mission (dépendant de `located`)
  // à chaque rendu au lieu de seulement quand la liste change réellement.
  const located = useMemo(
    () => (assignments ?? []).filter(a => a.latitude != null && a.longitude != null),
    [assignments],
  );
  const unlocatedCount = (assignments ?? []).length - located.length;

  const region = useMemo(() => {
    if (position) return { ...position, latitudeDelta: 0.08, longitudeDelta: 0.08 };
    const first = located[0];
    if (first) {
      return {
        latitude: first.latitude as number,
        longitude: first.longitude as number,
        latitudeDelta: 0.08,
        longitudeDelta: 0.08,
      };
    }
    return FALLBACK_REGION;
  }, [position, located]);

  // `region` n'est utile qu'à titre de première approximation : passé en `initialRegion`,
  // il n'est honoré qu'au montage — or au tout premier rendu `position` vaut toujours
  // `null` et `assignments` toujours `undefined` (les deux résolvent de façon
  // asynchrone), donc `initialRegion` vaut en pratique TOUJOURS `FALLBACK_REGION`. Les
  // deux recentrages impératifs ci-dessous sont donc indispensables — ce n'est pas une
  // redondance avec `region` : c'est le seul moyen d'amener la carte sur une vraie
  // position ou une mission géolocalisée après le montage. `region` en PROP CONTRÔLÉE
  // recentrerait la carte à chaque tick GPS : l'utilisateur ne pourrait plus ni déplacer
  // ni zoomer, la vue lui sauterait des mains toutes les quelques secondes — d'où
  // `initialRegion` (fixe) plus `animateToRegion` (un-shot, impératif) pour chaque cas.

  // Recentrage sur la position GPS, UNE SEULE FOIS dès qu'elle arrive. Prioritaire sur le
  // centrage-mission ci-dessous : une vraie position gagne toujours, même si on avait déjà
  // centré sur une mission en l'absence de GPS.
  useEffect(() => {
    if (!position || hasCenteredOnPositionRef.current) return;
    hasCenteredOnPositionRef.current = true;
    hasCenteredRef.current = true;
    mapRef.current?.animateToRegion(
      { ...position, latitudeDelta: 0.08, longitudeDelta: 0.08 },
      500,
    );
  }, [position]);

  // Repli : tant qu'aucune position GPS n'est encore connue mais qu'une mission
  // géolocalisée existe, centrer dessus plutôt que de laisser le prestataire sur la vue
  // pays (FALLBACK_REGION) — le cas d'un GPS refusé mais d'une mission en attente. Ne
  // pose QUE `hasCenteredRef` (pas `hasCenteredOnPositionRef`) : si une position GPS
  // arrive ensuite, l'effet ci-dessus doit pouvoir recentrer une seconde fois.
  useEffect(() => {
    if (position || hasCenteredRef.current) return;
    const first = located[0];
    if (!first) return;
    hasCenteredRef.current = true;
    mapRef.current?.animateToRegion(
      {
        latitude: first.latitude as number,
        longitude: first.longitude as number,
        latitudeDelta: 0.08,
        longitudeDelta: 0.08,
      },
      500,
    );
  }, [position, located]);

  if (!maps) {
    return (
      <View style={styles.fallback} testID="map-fallback">
        <Text style={styles.fallbackText}>
          {position
            ? `Position : ${position.latitude.toFixed(5)}, ${position.longitude.toFixed(5)}`
            : 'Carte indisponible sur cet appareil.'}
        </Text>
      </View>
    );
  }

  const { MapView, Marker, Callout } = maps;

  return (
    <View style={styles.container}>
      <MapView ref={mapRef} style={styles.map} testID="provider-map" initialRegion={region}>
        {located.map(a => {
          // Distance vive depuis la position GPS actuelle jusqu'à la mission — recalculée
          // à chaque rendu (pas mémoïsée), sa dépendance `position` change de toute façon
          // à chaque tick GPS.
          const km = distanceKmTo(position, a);
          return (
            // Clé et testID sur `a.id` (l'identifiant de l'affectation) et non sur `booking_id` :
            // ce dernier est nullable côté API, donc deux missions dont la réservation n'est pas
            // résolue partageraient la clé `null` et l'une des deux disparaîtrait de la carte.
            <Marker
              key={a.id}
              testID={`mission-marker-${a.id}`}
              coordinate={{ latitude: a.latitude as number, longitude: a.longitude as number }}
            >
              {/* `mission_id`, PAS `booking_id` : MissionDetailScreen appelle
                  GET /provider/missions/{missionId}, lié au modèle Mission. Un bookings.id y
                  ouvre une mission sans rapport, ou répond 404 — que l'écran affiche en
                  « Chargement... » perpétuel, donc sans le moindre signe visible. */}
              <Callout onPress={() => navigation.navigate('MissionDetail', { missionId: a.mission_id })}>
                <View style={styles.callout}>
                  <Text style={styles.calloutService}>{a.service_name}</Text>
                  <Text style={styles.calloutClient}>{a.client_name}</Text>
                  {km != null && (
                    <Text style={styles.calloutDistance}>{formatDistance(km * 1000)}</Text>
                  )}
                </View>
              </Callout>
            </Marker>
          );
        })}
      </MapView>

      <View style={styles.overlay} pointerEvents="box-none">
        {permission === 'denied' && (
          <Text style={styles.notice} testID="map-permission-notice">
            Position indisponible — autorise l'accès à ta localisation pour te voir sur la carte.
          </Text>
        )}
        {unlocatedCount > 0 && (
          <Text style={styles.notice}>
            {unlocatedCount} mission{unlocatedCount > 1 ? 's' : ''} sans localisation
          </Text>
        )}
        {!isError && located.length === 0 && (
          <Text style={styles.notice}>Aucune mission en attente</Text>
        )}
        {isError && (
          <View style={styles.errorRow}>
            <Text style={styles.notice}>Missions non chargées.</Text>
            <Button label="Réessayer" onPress={() => void refetch()} size="sm" variant="secondary" />
          </View>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  map: { flex: 1 },
  overlay: { position: 'absolute', top: spacing.sm, left: spacing.sm, right: spacing.sm, gap: spacing.xs },
  notice: {
    alignSelf: 'flex-start',
    backgroundColor: '#fff',
    borderRadius: radius.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    fontSize: typography.fontSize.xs,
    color: colors.surface[700],
    overflow: 'hidden',
  },
  errorRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  callout: { minWidth: 160, padding: spacing.xs },
  calloutService: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.semibold, color: colors.surface[900] },
  calloutClient: { fontSize: typography.fontSize.xs, color: colors.surface[600], marginTop: 2 },
  calloutDistance: { fontSize: typography.fontSize.xs, color: colors.brand[600], marginTop: 2 },
  fallback: {
    flex: 1,
    backgroundColor: colors.surface[100],
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.md,
  },
  fallbackText: { fontSize: typography.fontSize.xs, color: colors.surface[500], textAlign: 'center' },
});
