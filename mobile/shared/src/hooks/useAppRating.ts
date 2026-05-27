import AsyncStorage from '@react-native-async-storage/async-storage';

const STORAGE_KEY = 'cleanux_completed_bookings';
const THRESHOLD = 3;

/**
 * Increments the local completed-booking counter.
 * Call this after a booking is confirmed complete on the client side.
 */
export async function incrementCompletedBookings(): Promise<void> {
  const raw = await AsyncStorage.getItem(STORAGE_KEY);
  const count = parseInt(raw ?? '0', 10);
  await AsyncStorage.setItem(STORAGE_KEY, String(count + 1));
}

/**
 * Requests a store review if the user has completed >= THRESHOLD bookings
 * and the platform supports the review action.
 * Resets the counter after a prompt is shown so the user is not asked again
 * until they complete another cycle of THRESHOLD bookings.
 */
export async function maybeRequestReview(): Promise<void> {
  const raw = await AsyncStorage.getItem(STORAGE_KEY);
  const count = parseInt(raw ?? '0', 10);
  if (count < THRESHOLD) return;

  try {
    const StoreReview = await import('expo-store-review');
    const canPrompt = await StoreReview.hasAction();
    if (!canPrompt) return;
    await StoreReview.requestReview();
    await AsyncStorage.setItem(STORAGE_KEY, '0');
  } catch {
    // expo-store-review not available — silent fail
  }
}
