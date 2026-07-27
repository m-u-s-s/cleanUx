import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Button } from '@/ui';
import { useMissionInbox } from '@/missions';
import { useGpsWatcher } from '@/tracking';
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
  const maps = useMemo(() => loadMapModule(), []);
  const mapRef = useRef<any>(null);
  const hasCenteredRef = useRef(false);
  const [position, setPosition] = useState<Position | null>(null);
  const { permission } = useGpsWatcher(
    true,
    useCallback((pos) => setPosition({ latitude: pos.latitude, longitude: pos.longitude }), []),
  );
  const { data: assignments, isError, refetch } = useMissionInbox();

  const located = (assignments ?? []).filter(a => a.latitude != null && a.longitude != null);
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

  // Recentrage UNE SEULE FOIS, à l'arrivée de la première position. `region` en prop
  // contrôlée recentrerait la carte à chaque tick GPS : l'utilisateur ne pourrait plus
  // ni déplacer ni zoomer, la vue lui sauterait des mains toutes les quelques secondes.
  useEffect(() => {
    if (!position || hasCenteredRef.current) return;
    hasCenteredRef.current = true;
    mapRef.current?.animateToRegion(
      { ...position, latitudeDelta: 0.08, longitudeDelta: 0.08 },
      500,
    );
  }, [position]);

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

  const { MapView } = maps;

  return (
    <View style={styles.container}>
      <MapView ref={mapRef} style={styles.map} testID="provider-map" initialRegion={region} />

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
  fallback: {
    flex: 1,
    backgroundColor: colors.surface[100],
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.md,
  },
  fallbackText: { fontSize: typography.fontSize.xs, color: colors.surface[500], textAlign: 'center' },
});
