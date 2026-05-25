import { LegalScreen } from '@/screens/LegalScreen';
import { setupForegroundNotifications } from '@/push/foreground';

jest.mock('@/storage/secureStore');
jest.mock('expo-notifications', () => ({
  setNotificationHandler: jest.fn(),
  AndroidNotificationPriority: { HIGH: 'HIGH' },
}), { virtual: true });

describe('Batch C — provider: validation + push foreground + legal', () => {
  it('LegalScreen exports without crash', () => {
    expect(LegalScreen).toBeDefined();
  });

  it('setupForegroundNotifications is callable without throwing', () => {
    expect(() => setupForegroundNotifications()).not.toThrow();
  });

  it('setupForegroundNotifications calls setNotificationHandler', () => {
    const Notifications = require('expo-notifications');
    setupForegroundNotifications();
    expect(Notifications.setNotificationHandler).toHaveBeenCalled();
  });
});
