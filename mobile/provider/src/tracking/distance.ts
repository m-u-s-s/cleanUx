/**
 * Distances géographiques, partagées par la carte du dashboard et la liste des missions.
 *
 * `distance_km` n'existe pas côté API : la distance est dérivée ici depuis la position GPS
 * vive, ce qui est plus juste qu'une distance calculée serveur depuis une position de
 * présence potentiellement périmée. L'implémentation vient de TrackingScreen, qui la
 * consomme désormais au lieu d'en garder une copie locale.
 */

const EARTH_RADIUS_METERS = 6371000;

export function haversineMeters(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const dLat = ((lat2 - lat1) * Math.PI) / 180;
  const dLon = ((lon2 - lon1) * Math.PI) / 180;
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLon / 2) *
      Math.sin(dLon / 2);
  return EARTH_RADIUS_METERS * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export function distanceKmTo(
  from: { latitude: number; longitude: number } | null,
  to: { latitude?: number | null; longitude?: number | null },
): number | null {
  if (!from) return null;
  if (to.latitude == null || to.longitude == null) return null;
  return haversineMeters(from.latitude, from.longitude, to.latitude, to.longitude) / 1000;
}

export function formatDistance(meters: number): string {
  if (meters >= 1000) return `${(meters / 1000).toFixed(1)} km`;
  return `${Math.round(meters)} m`;
}
