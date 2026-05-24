import { RatingScreen } from '@/screens/RatingScreen';
import { LoyaltyScreen } from '@/screens/LoyaltyScreen';
import { ReferralScreen } from '@/screens/ReferralScreen';
import { AiQuoteScreen } from '@/screens/AiQuoteScreen';
import { DisputesScreen } from '@/screens/DisputesScreen';
import { GDPRScreen } from '@/screens/GDPRScreen';
import { ProfileEditScreen } from '@/screens/ProfileEditScreen';
import { TipsScreen } from '@/screens/TipsScreen';
import { NPSScreen } from '@/screens/NPSScreen';

jest.mock('@/storage/secureStore');

describe('Sprint 9-10 screens', () => {
  const screens = [
    RatingScreen,
    LoyaltyScreen,
    ReferralScreen,
    AiQuoteScreen,
    DisputesScreen,
    GDPRScreen,
    ProfileEditScreen,
    TipsScreen,
    NPSScreen,
  ];
  screens.forEach(S => {
    it(`${S.name} exports without crash`, () => {
      expect(S).toBeDefined();
    });
  });
});
