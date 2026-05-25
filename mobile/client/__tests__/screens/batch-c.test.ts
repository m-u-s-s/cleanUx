import { LegalScreen } from '@/screens/LegalScreen';
import { ProfileEditScreen } from '@/screens/ProfileEditScreen';
import { setupForegroundNotifications } from '@/push/foreground';

jest.mock('@/storage/secureStore');
jest.mock('expo-notifications', () => ({
  setNotificationHandler: jest.fn(),
  AndroidNotificationPriority: { HIGH: 'HIGH' },
}), { virtual: true });

describe('Batch C — client: validation + push foreground + avatar + legal', () => {
  it('LegalScreen exports without crash', () => {
    expect(LegalScreen).toBeDefined();
  });

  it('ProfileEditScreen exports without crash', () => {
    expect(ProfileEditScreen).toBeDefined();
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
