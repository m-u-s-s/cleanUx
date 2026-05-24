import { AvailabilityScreen } from '@/screens/AvailabilityScreen';
import { BadgesScreen } from '@/screens/BadgesScreen';
import { KYCScreen } from '@/screens/KYCScreen';
import { ProviderDisputesScreen } from '@/screens/ProviderDisputesScreen';
import { ProviderRatingsScreen } from '@/screens/ProviderRatingsScreen';

jest.mock('@/storage/secureStore');

describe('Phase 2 remaining screens', () => {
  [AvailabilityScreen, BadgesScreen, KYCScreen, ProviderDisputesScreen, ProviderRatingsScreen].forEach(S => {
    it(`${S.name} exports`, () => { expect(S).toBeDefined(); });
  });
});
