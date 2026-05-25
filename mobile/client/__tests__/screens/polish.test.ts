import { NotificationPreferencesScreen } from '@/screens/NotificationPreferencesScreen';
import { LanguageScreen } from '@/screens/LanguageScreen';
import { AppearanceScreen } from '@/screens/AppearanceScreen';
import { OnboardingScreen, hasCompletedOnboarding } from '@/screens/OnboardingScreen';

jest.mock('@/storage/secureStore');

describe('Polish screens', () => {
  [NotificationPreferencesScreen, LanguageScreen, AppearanceScreen, OnboardingScreen].forEach(S => {
    it(`${S.name} exports`, () => {
      expect(S).toBeDefined();
    });
  });

  it('hasCompletedOnboarding is a function', () => {
    expect(typeof hasCompletedOnboarding).toBe('function');
  });
});
