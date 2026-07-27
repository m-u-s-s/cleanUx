import type React from 'react';

export interface MapModule {
  MapView: React.ComponentType<any>;
  Marker: React.ComponentType<any>;
  Callout: React.ComponentType<any>;
}

/**
 * Charge react-native-maps sans jamais laisser échapper d'exception.
 *
 * Même raisonnement que shared/src/push/availability.ts : un module natif absent du runtime
 * (Expo Go dépourvu, build mal configuré) doit dégrader l'écran, pas le faire planter. Le
 * require est délibérément paresseux pour que l'échec soit rattrapable.
 */
export function loadMapModule(): MapModule | null {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const maps = require('react-native-maps');
    const MapView = maps?.default ?? maps?.MapView;

    if (!MapView || !maps?.Marker) return null;

    return { MapView, Marker: maps.Marker, Callout: maps.Callout };
  } catch {
    return null;
  }
}
