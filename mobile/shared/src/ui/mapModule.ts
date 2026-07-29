import type React from 'react';
import { Platform } from 'react-native';
import Constants from 'expo-constants';

export interface MapModule {
  MapView: React.ComponentType<any>;
  Marker: React.ComponentType<any>;
  Callout: React.ComponentType<any>;
  /**
   * Tracé d'un trajet. Facultatif à dessein : il n'est employé que par l'écran de suivi, et son
   * absence ne doit pas faire échouer le chargement pour les écrans qui n'en ont pas besoin.
   */
  Polyline?: React.ComponentType<any>;
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

    if (!MapView || !maps?.Marker || !maps?.Callout) return null;

    return { MapView, Marker: maps.Marker, Callout: maps.Callout, Polyline: maps.Polyline };
  } catch {
    return null;
  }
}

/**
 * La carte peut-elle réellement s'afficher sur cet appareil ?
 *
 * Charger le module ne suffit pas : sur Android, react-native-maps s'appuie sur Google Maps, qui
 * exige une clé dans le manifeste natif. Sans elle, le rendu ne dégrade pas — il LÈVE :
 *
 *   IllegalStateException: API key not found. Check that
 *   <meta-data android:name="com.google.android.geo.API_KEY" ...> is in the <application> element
 *
 * et emporte l'écran, donc le tableau de bord entier, dont la carte est l'élément principal. On
 * vérifie donc la présence de la clé AVANT de monter le composant, plutôt que de découvrir son
 * absence par un crash. iOS n'est pas concerné : il s'appuie sur Apple Maps, sans clé.
 *
 * La clé est injectée par app.config.js depuis EXPO_PUBLIC_GOOGLE_MAPS_API_KEY, et n'est lisible
 * qu'après une reconstruction — c'est de la configuration native, pas du JavaScript.
 */
export function isMapRenderable(): boolean {
  if (Platform.OS !== 'android') {
    return true;
  }

  const key = (Constants.expoConfig as any)?.android?.config?.googleMaps?.apiKey;

  return isPlausibleGoogleApiKey(key);
}

/**
 * Une clé non vide ne suffit pas : un placeholder la contourne et laisse le crash revenir.
 *
 * Ce n'est pas théorique — le .env du backend porte `GOOGLE_MAPS_API_KEY=ta_cle_google_maps`, et
 * recopier cette valeur suffirait à faire replanter le tableau de bord. On vérifie donc la FORME
 * d'une clé Google : préfixe `AIza` et 39 caractères, ce que respectent toutes les clés émises.
 *
 * Le but n'est pas d'authentifier la clé — seul Google le peut — mais d'écarter ce qui n'en est
 * manifestement pas une, pour que le repli s'affiche plutôt qu'une exception native.
 */
function isPlausibleGoogleApiKey(key: unknown): boolean {
  return typeof key === 'string' && /^AIza[0-9A-Za-z_-]{35}$/.test(key);
}
