export { TripTrackingHost } from './TripTrackingHost';
export {
  // La seule LECTURE du module : sans elle, la route ne pouvait etre dessinee qu'au suivi.
  useSessionActive,
  useStartTracking,
  useSendPing,
  useMarkInMission,
  useArriveOnSite,
  useConfirmPresence,
  useCompleteByQr,
  usePushPosition,
  usePushEta,
  useGpsWatcher,
} from './hooks';
export { startBackgroundLocation, stopBackgroundLocation } from './useBackgroundLocation';
export { haversineMeters, distanceKmTo, formatDistance } from './distance';
export { useCurrentPosition } from './useCurrentPosition';
export { readScanPosition, SCAN_POSITION_TIMEOUT_MS } from './scanPosition';
export type { ScanPosition } from './scanPosition';
