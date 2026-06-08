import * as Location from 'expo-location';
import * as TaskManager from 'expo-task-manager';
import { apiClient } from '@/api';

const TASK_NAME = 'CLEANUX_BG_LOCATION';

let taskDefined = false;

/**
 * M15 — register the background task lazily instead of at module top-level. Running
 * TaskManager.defineTask at import time executes a native side-effect as soon as any screen
 * importing '@/tracking' mounts, which crashes in Expo Go (expo-task-manager is unsupported
 * there). Defining it on first start (guarded) keeps the module import side-effect free.
 */
function ensureTaskDefined(): void {
  if (taskDefined) return;
  try {
    TaskManager.defineTask(TASK_NAME, async ({ data, error }) => {
      if (error || !data) return;
      const { locations } = data as { locations: Location.LocationObject[] };
      const loc = locations[0];
      if (!loc) return;

      try {
        await apiClient.post('/provider/presence-v2/heartbeat', {
          lat: loc.coords.latitude,
          lng: loc.coords.longitude,
        });
      } catch {}
    });
    taskDefined = true;
  } catch (e) {
    // expo-task-manager unavailable (e.g. Expo Go) — background tracking just won't run.
    console.warn('Background location task registration failed:', e);
  }
}

export async function startBackgroundLocation(): Promise<boolean> {
  ensureTaskDefined();

  const { status } = await Location.requestBackgroundPermissionsAsync();
  if (status !== 'granted') return false;

  await Location.startLocationUpdatesAsync(TASK_NAME, {
    accuracy: Location.Accuracy.Balanced,
    timeInterval: 30000,
    distanceInterval: 50,
    showsBackgroundLocationIndicator: true,
    foregroundService: {
      notificationTitle: 'CleanUx Pro',
      notificationBody: 'Suivi GPS actif pendant la mission',
    },
  });
  return true;
}

export async function stopBackgroundLocation(): Promise<void> {
  const isRunning = await TaskManager.isTaskRegisteredAsync(TASK_NAME);
  if (isRunning) await Location.stopLocationUpdatesAsync(TASK_NAME);
}
