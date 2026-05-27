import * as Location from 'expo-location';
import * as TaskManager from 'expo-task-manager';
import { apiClient } from '@/api';

const TASK_NAME = 'CLEANUX_BG_LOCATION';

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

export async function startBackgroundLocation(): Promise<boolean> {
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
