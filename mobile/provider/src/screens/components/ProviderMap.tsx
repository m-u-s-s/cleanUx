import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useIsFocused, useNavigation } from '@react-navigation/native';
import { Button } from '@/ui';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';
import { useMissionInbox } from '@/missions';
import { useGpsWatcher, useSessionActive, distanceKmTo, formatDistance } from '@/tracking';
import { loadMapModule, isMapRenderable, OsmMap } from '@/maps';
import { colors, spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

/** Repli d'échelle pays centré sur Bruxelles, marché principal du projet. */
export const FALLBACK_REGION = {
  latitude: 50.85,
  longitude: 4.35,
  latitudeDelta: 2,
  longitudeDelta: 2,
};

type Position = { latitude: number; longitude: number };

export function ProviderMap() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const navigation = useNavigation<any>();
  const maps = useMemo(() => loadMapModule(), []);
  // Le module peut charger sans que la carte soit affichable : sur Android, Google Maps LÈVE
  // faute de clé dans le manifeste, emportant tout le tableau de bord. On le vérifie avant.
  const renderable = useMemo(() => isMapRenderable(), []);
  const mapRef = useRef<any>(null);
  // `hasCenteredRef` : « on a déjà centré sur quelque chose » (position OU mission).
  // `hasCenteredOnPositionRef` : « on a déjà centré sur une vraie position GPS ». Deux
  // drapeaux distincts pour qu'une position GPS arrivant après un centrage sur mission
  // puisse quand même déclencher un recentrage — un centrage « mission » ne doit pas
  // empêcher le seul recentrage qui compte vraiment (la position réelle du prestataire).
  const hasCenteredRef = useRef(false);
  const hasCenteredOnPositionRef = useRef(false);
  const [position, setPosition] = useState<Position | null>(null);
  // DashboardScreen est un onglet : React Navigation le garde monté une fois visité. Avec
  // `enabled` câblé en dur, le watcher (Accuracy.High, 5 s, 10 m) continuait de tourner pendant
  // que le prestataire regardait Missions, Revenus ou Profil — consommation entièrement
  // nouvelle, l'ancien tableau de bord n'utilisant aucun GPS.
  const isFocused = useIsFocused();
  const { permission } = useGpsWatcher(
    isFocused,
    useCallback((pos) => setPosition({ latitude: pos.latitude, longitude: pos.longitude }), []),
  );
  const { data: assignments, isError, refetch } = useMissionInbox();

  /*
   * LA ROUTE ACTIVE SUR LA CARTE D'ACCUEIL.
   *
   * Cette carte n'affichait que les MARQUEURS des missions en attente. Un prestataire déjà en route
   * y voyait des points sans trajet, et devait ouvrir un autre écran pour savoir par où passer —
   * alors que c'est l'écran qu'il a sous les yeux en conduisant.
   *
   * MÊME CLÉ DE CACHE que `TripTrackingHost` : deux requêtes distinctes pour la même chose feraient
   * battre l'API deux fois pour un seul besoin.
   */
  const { data: missionsActives } = useQuery<{ status: string; booking_id: number | null }[]>({
    queryKey: ['provider', 'missions', 'active'],
    queryFn: async () => {
      const res = await apiClient.get('/provider/missions/active');

      return res.data.data ?? res.data ?? [];
    },
    refetchInterval: 30000,
  });

  const enCours = (missionsActives ?? []).find(
    (m) => ['en_route', 'arrived', 'started', 'paused'].includes(m.status) && m.booking_id != null,
  );
  const { data: session } = useSessionActive(enCours?.booking_id ?? null);

  const route = useMemo(
    () => (session?.route?.points ?? []).map((p) => ({ latitude: p.lat, longitude: p.lng })),
    [session],
  );

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

  // Pas de carte Google exploitable — module absent, ou clé manquante sur Android. Plutôt qu'un
  // simple texte, on rend OpenStreetMap via WebView : gratuit, sans clé, et sans dépendance
  // nouvelle puisque react-native-webview est déjà là pour le captcha.
  if (!maps || !renderable) {
    return (
      <View style={styles.container}>
        <OsmMap
          testID="provider-map-osm"
          markers={located.map(a => ({
            id: a.mission_id,
            latitude: a.latitude as number,
            longitude: a.longitude as number,
            title: a.service_name ?? 'Mission',
            subtitle: a.client_name,
            detail: (() => {
              const km = distanceKmTo(position, a);
              return km != null ? formatDistance(km * 1000) : null;
            })(),
          }))}
          position={position}
          // La route PRÉVUE, quand une mission est en cours : c'est elle qui dit par où passer.
          trail={route.length > 1 ? route : undefined}
          fallbackCenter={{ latitude: FALLBACK_REGION.latitude, longitude: FALLBACK_REGION.longitude, zoom: 7 }}
          // mission_id, PAS booking_id — même raison que pour la carte native : l'écran de détail
          // appelle GET /provider/missions/{missionId}.
          onMarkerPress={(missionId) => navigation.navigate('MissionDetail', { missionId })}
        />

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
            <Text style={styles.notice}>{tr('provider_map.aucune_mission_en_attente')}</Text>
          )}
        </View>
      </View>
    );
  }

  const { MapView, Marker, Callout } = maps;

  return (
    <View style={styles.container}>
      <MapView ref={mapRef} style={styles.map} testID="provider-map" initialRegion={region}>
        {/* La route prévue de la mission en cours, tracée sous les marqueurs. */}
        {maps.Polyline && route.length > 1 && (
          <maps.Polyline
            coordinates={route}
            strokeWidth={5}
            strokeColor={colors.brand[600] ?? colors.brand[500]}
            testID="provider-map-route"
          />
        )}

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
          <Text style={styles.notice}>{tr('provider_map.aucune_mission_en_attente')}</Text>
        )}
        {isError && (
          <View style={styles.errorRow}>
            <Text style={styles.notice}>{tr('provider_map.missions_non_chargees')}</Text>
            <Button label={tr('provider_map.reessayer')} onPress={() => void refetch()} size="sm" variant="secondary" />
          </View>
        )}
      </View>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { flex: 1 },
  map: { flex: 1 },
  overlay: { position: 'absolute', top: spacing.sm, left: spacing.sm, right: spacing.sm, gap: spacing.xs },
  notice: {
    alignSelf: 'flex-start',
    backgroundColor: t.card,
    borderRadius: radius.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    fontSize: typography.fontSize.xs,
    color: t.text,
    overflow: 'hidden',
  },
  errorRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  callout: { minWidth: 160, padding: spacing.xs },
  calloutService: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.semibold, color: t.text },
  calloutClient: { fontSize: typography.fontSize.xs, color: t.textSecondary, marginTop: 2 },
  calloutDistance: { fontSize: typography.fontSize.xs, color: t.brandText, marginTop: 2 },
  fallback: {
    flex: 1,
    backgroundColor: t.inputBg,
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.md,
  },
  fallbackText: { fontSize: typography.fontSize.xs, color: t.textSecondary, textAlign: 'center' },
});
