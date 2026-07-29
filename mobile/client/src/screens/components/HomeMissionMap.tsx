import React, { useMemo } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { OsmMap, loadMapModule, isMapRenderable } from '@/ui';
import { useTrackingSession, useTrackingTrail, useLiveTracking } from '@/tracking';
import { colors, spacing, typography, radius } from '@/theme';

/**
 * Carte de la mission en cours, sur l'accueil client.
 *
 * Le tableau de bord prestataire consacre son espace principal à une carte ; l'accueil client
 * n'en avait aucune. La différence de besoin demeure — un prestataire regarde ce qui l'entoure,
 * un client regarde SA mission — donc cette carte ne montre qu'une chose : où en est le
 * prestataire qui vient chez lui.
 *
 * Elle ne s'affiche que pendant une mission démarrée : sans session de suivi, il n'y a
 * simplement rien à situer. Les réservations, elles, ne portent pas de coordonnées — seule la
 * session de suivi en fournit.
 *
 * Deux gardes reprises du tableau de bord prestataire, où leur absence avait fait planter
 * l'écran : le module natif peut manquer, et sur Android Google Maps LÈVE une exception sans clé
 * dans le manifeste plutôt que de dégrader. On vérifie donc avant de monter, et on retombe sur
 * un fond OpenStreetMap en WebView — qui n'exige aucune clé.
 */
export function HomeMissionMap({ bookingId }: { bookingId: number }) {
  const { data: session } = useTrackingSession(bookingId);
  const { data: trail } = useTrackingTrail(bookingId);
  const { position: livePosition } = useLiveTracking(bookingId);

  const current = livePosition ?? (trail && trail.length > 0 ? trail[trail.length - 1] : null);

  const mapModule = useMemo(() => (isMapRenderable() ? loadMapModule() : null), []);

  if (!current) {
    return null;
  }

  const eta = session?.eta_minutes;

  return (
    <View style={styles.wrap} testID="home-mission-map">
      {mapModule ? (
        <mapModule.MapView
          style={styles.map}
          initialRegion={{
            latitude: current.latitude,
            longitude: current.longitude,
            latitudeDelta: 0.02,
            longitudeDelta: 0.02,
          }}
          pointerEvents="none"
        >
          <mapModule.Marker
            coordinate={{ latitude: current.latitude, longitude: current.longitude }}
            title="Votre prestataire"
          />
        </mapModule.MapView>
      ) : (
        // Sans clé Google Maps, monter react-native-maps ferait planter l'écran d'accueil —
        // c'est-à-dire l'entrée de l'application. Le fond OpenStreetMap n'en demande aucune.
        <OsmMap
          markers={[{
            id: bookingId,
            latitude: current.latitude,
            longitude: current.longitude,
            title: 'Votre prestataire',
          }]}
          position={{ latitude: current.latitude, longitude: current.longitude }}
          fallbackCenter={{ latitude: current.latitude, longitude: current.longitude, zoom: 14 }}
          onMarkerPress={() => undefined}
          testID="home-mission-map-osm"
        />
      )}

      {eta !== undefined && eta !== null ? (
        <View style={styles.etaPill} pointerEvents="none">
          <Text style={styles.etaText}>Arrivée dans ~{eta} min</Text>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { height: 180, borderRadius: radius.md, overflow: 'hidden' },
  map: { flex: 1 },
  // Pastille flottante, comme la pastille de présence du tableau de bord prestataire.
  etaPill: {
    position: 'absolute',
    left: spacing.sm,
    bottom: spacing.sm,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
    borderRadius: radius.pill,
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: colors.surface[200],
  },
  etaText: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.mode.tool.ink,
  },
});
