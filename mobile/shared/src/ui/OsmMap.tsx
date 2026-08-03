import React, { useCallback, useMemo } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, spacing, typography } from '../theme';
import { useThemeColors } from '../theme/useThemeColors';
import type { ThemeTokens } from '../theme/useThemeColors';

/**
 * Carte OpenStreetMap rendue dans une WebView, via Leaflet.
 *
 * Pourquoi ce détour plutôt qu'une carte native : sur Android, react-native-maps s'appuie sur
 * Google Maps, qui exige une clé, une facturation, et lève sans elle. Leaflet sur tuiles OSM ne
 * demande aucune clé, et react-native-webview est DÉJÀ installé (ajouté pour le captcha) — donc
 * aucune dépendance nouvelle, aucune reconstruction native.
 *
 * Le require de la WebView est paresseux et rattrapable, comme partout ailleurs ici : le module
 * touche un binaire natif et lève dès l'import quand celui-ci manque.
 *
 * TUILES : par défaut le serveur public d'OpenStreetMap, dont la politique d'usage interdit le
 * trafic massif ou commercial. Convient au développement ; en production, pointer
 * EXPO_PUBLIC_MAP_TILE_URL vers un fournisseur dédié (MapTiler, Stadia, Protomaps…).
 */
export interface OsmMarker {
  id: number;
  latitude: number;
  longitude: number;
  title: string;
  subtitle?: string | null;
  detail?: string | null;
}

interface OsmMapProps {
  markers: OsmMarker[];
  position: { latitude: number; longitude: number } | null;
  /**
   * Trajet parcouru, tracé comme une ligne. Sur un écran de suivi c'est l'essentiel : sans lui,
   * le repli n'afficherait qu'un point et perdrait ce que la carte sert à montrer.
   */
  trail?: { latitude: number; longitude: number }[];
  /** Centre initial quand aucune position ni mission n'est connue. */
  fallbackCenter: { latitude: number; longitude: number; zoom: number };
  onMarkerPress: (id: number) => void;
  testID?: string;
}

const TILE_URL =
  process.env.EXPO_PUBLIC_MAP_TILE_URL || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

const ATTRIBUTION = '&copy; OpenStreetMap';

function loadWebView(): any | null {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    return require('react-native-webview');
  } catch {
    return null;
  }
}

/**
 * Le HTML est construit à partir de données déjà sérialisées en JSON, jamais par concaténation
 * de chaînes brutes : un nom de client contenant une apostrophe ou une balise casserait sinon le
 * script, voire y injecterait du code.
 */
function buildHtml(props: {
  markers: OsmMarker[];
  position: OsmMapProps['position'];
  center: OsmMapProps['fallbackCenter'];
  trail: { latitude: number; longitude: number }[];
}): string {
  const payload = JSON.stringify(props).replace(/</g, '\\u003c');

  return `<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
      html, body, #map { margin: 0; padding: 0; height: 100%; width: 100%; background: #F7F8FB; }
      .cx-popup { font: 13px -apple-system, Roboto, sans-serif; }
      .cx-popup b { display: block; font-size: 14px; }
      .cx-popup small { color: #64748b; }
      .cx-me {
        width: 16px; height: 16px; border-radius: 50%;
        background: #4f46e5; border: 3px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.2);
      }
    </style>
  </head>
  <body>
    <div id="map"></div>
    <script>
      var data = ${payload};
      var map = L.map('map', { zoomControl: false, attributionControl: true })
        .setView([data.center.latitude, data.center.longitude], data.center.zoom);

      L.tileLayer(${JSON.stringify(TILE_URL)}, { attribution: ${JSON.stringify(ATTRIBUTION)}, maxZoom: 19 }).addTo(map);

      var bounds = [];

      data.markers.forEach(function (m) {
        var marker = L.marker([m.latitude, m.longitude]).addTo(map);
        var html = '<div class="cx-popup"><b></b><span></span><br><small></small></div>';
        var node = document.createElement('div');
        node.innerHTML = html;
        // textContent, jamais innerHTML : les libellés viennent de saisies clients.
        node.querySelector('b').textContent = m.title || '';
        node.querySelector('span').textContent = m.subtitle || '';
        node.querySelector('small').textContent = m.detail || '';
        node.style.cursor = 'pointer';
        node.addEventListener('click', function () {
          window.ReactNativeWebView && window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'marker', id: m.id }));
        });
        marker.bindPopup(node);
        bounds.push([m.latitude, m.longitude]);
      });

      // Le trajet d'abord, pour qu'il passe SOUS les marqueurs plutôt que par-dessus.
      if (data.trail && data.trail.length > 1) {
        var line = data.trail.map(function (p) { return [p.latitude, p.longitude]; });
        L.polyline(line, { color: ${JSON.stringify(colors.brand[500])}, weight: 4, opacity: 0.85 }).addTo(map);
        line.forEach(function (pt) { bounds.push(pt); });
      }

      if (data.position) {
        L.marker([data.position.latitude, data.position.longitude], {
          icon: L.divIcon({ className: '', html: '<div class="cx-me"></div>', iconSize: [16, 16] }),
        }).addTo(map);
        bounds.push([data.position.latitude, data.position.longitude]);
      }

      // Cadrage sur l'ensemble des points connus, plutôt qu'un centre arbitraire.
      if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
      } else if (bounds.length === 1) {
        map.setView(bounds[0], 14);
      }
    </script>
  </body>
</html>`;
}

export function OsmMap({ markers, position, trail, fallbackCenter, onMarkerPress, testID }: OsmMapProps) {
  const styles = stylesFor(useThemeColors());

  const WebView = useMemo(() => loadWebView()?.WebView ?? null, []);

  // La position change à chaque tick GPS ; recharger le HTML à chaque fois rechargerait la carte
  // entière. On ne dépend donc que des coordonnées arrondies : le point bouge par pas visibles,
  // pas à chaque frémissement du capteur.
  const coarsePosition = position
    ? { latitude: Number(position.latitude.toFixed(4)), longitude: Number(position.longitude.toFixed(4)) }
    : null;

  const html = useMemo(
    () => buildHtml({ markers, position: coarsePosition, center: fallbackCenter, trail: trail ?? [] }),
    // Le trajet ne dépend que de sa longueur : redessiner à chaque point rechargerait la carte.
    [markers, coarsePosition?.latitude, coarsePosition?.longitude, fallbackCenter, trail?.length],
  );

  const handleMessage = useCallback(
    (event: { nativeEvent: { data: string } }) => {
      try {
        const payload = JSON.parse(event.nativeEvent.data) as { type: string; id: number };
        if (payload.type === 'marker' && typeof payload.id === 'number') {
          onMarkerPress(payload.id);
        }
      } catch {
        // Un message illisible ne doit pas casser l'écran.
      }
    },
    [onMarkerPress],
  );

  if (!WebView) {
    return (
      <View style={styles.fallback} testID="osm-map-unavailable">
        <Text style={styles.fallbackText}>Carte indisponible sur cet appareil.</Text>
      </View>
    );
  }

  return (
    <WebView
      testID={testID}
      style={styles.map}
      source={{ html, baseUrl: 'https://cleanux.local' }}
      originWhitelist={['*']}
      javaScriptEnabled
      domStorageEnabled
      onMessage={handleMessage}
    />
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  map: { flex: 1, backgroundColor: t.bg },
  fallback: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: spacing.lg },
  fallbackText: { fontSize: typography.fontSize.sm, color: t.textSecondary, textAlign: 'center' },
});
