import * as Location from 'expo-location';

/**
 * Relevé ponctuel de position, pris au moment où le prestataire scanne le code du client.
 *
 * Distinct de {@link useCurrentPosition}, à dessein. Celui-ci enveloppe le watcher continu : il
 * rend la DERNIÈRE position vue, d'une fraîcheur inconnue, sans précision ni indicateur de
 * simulation. Une preuve de présence a besoin de l'inverse — un relevé demandé exprès, à l'instant
 * du scan, accompagné de ce qui permet de le juger.
 */
export interface ScanPosition {
  lat: number;
  lng: number;
  /** Précision annoncée par l'appareil, en mètres. Le serveur s'en sert pour élargir le rayon toléré. */
  accuracy_m: number | null;
  /** Android signale les relevés produits par une application de position fictive. */
  mocked: boolean;
}

/**
 * Au-delà, on renonce au relevé.
 *
 * `getCurrentPositionAsync` n'offre AUCUNE option de délai (SDK 56) : sans cette course, un relevé
 * impossible — sous-sol, cage d'escalier, ciel bouché — laisserait le bouton tourner indéfiniment
 * et le prestataire bloqué devant la porte du client, sans rien à faire ni rien à comprendre.
 */
export const SCAN_POSITION_TIMEOUT_MS = 8000;

/**
 * Lit la position courante, ou rend `null`.
 *
 * `null` couvre indifféremment le refus de permission, l'échec matériel et le délai dépassé :
 * l'application ne décide pas de ce qu'il faut en conclure. C'est le serveur qui tranche, selon sa
 * configuration — lui seul sait si une confirmation sans position est acceptable, et il est le seul
 * des deux à ne pas être sur l'appareil de la personne contrôlée.
 */
export async function readScanPosition(
  timeoutMs: number = SCAN_POSITION_TIMEOUT_MS,
): Promise<ScanPosition | null> {
  try {
    const { status } = await Location.requestForegroundPermissionsAsync();
    if (status !== 'granted') {
      return null;
    }

    const located = await withTimeout(
      Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High }),
      timeoutMs,
    );

    if (!located) {
      return null;
    }

    return {
      lat: located.coords.latitude,
      lng: located.coords.longitude,
      accuracy_m: located.coords.accuracy ?? null,
      // Absent hors Android : une absence n'est pas un aveu.
      mocked: located.mocked === true,
    };
  } catch {
    return null;
  }
}

/**
 * Rend `null` si la promesse n'a pas abouti à temps.
 *
 * Le `catch` est indispensable : la promesse perdante continue de vivre après la course, et un
 * rejet sans preneur remonte en `unhandledRejection` — bruit dans Sentry pour un événement banal,
 * la localisation qui échoue.
 */
function withTimeout<T>(promise: Promise<T>, ms: number): Promise<T | null> {
  return new Promise((resolve) => {
    const timer = setTimeout(() => resolve(null), ms);

    promise
      .then((value) => {
        clearTimeout(timer);
        resolve(value);
      })
      .catch(() => {
        clearTimeout(timer);
        resolve(null);
      });
  });
}
