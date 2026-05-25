import { NotificationPreferencesScreen } from '@/screens/NotificationPreferencesScreen';
import { LanguageScreen } from '@/screens/LanguageScreen';
import { AppearanceScreen } from '@/screens/AppearanceScreen';
import { WalkthroughScreen, hasCompletedWalkthrough } from '@/screens/WalkthroughScreen';

jest.mock('@/storage/secureStore');

describe('Polish screens (provider)', () => {
  [NotificationPreferencesScreen, LanguageScreen, AppearanceScreen, WalkthroughScreen].forEach(S => {
    it(`${S.name} exports`, () => {
      expect(S).toBeDefined();
    });
  });

  it('hasCompletedWalkthrough is a function', () => {
    expect(typeof hasCompletedWalkthrough).toBe('function');
  });
});
