import { BookingDetailScreen } from '@/screens/BookingDetailScreen';

jest.mock('@/storage/secureStore');

describe('BookingDetailScreen', () => {
  it('exports without crash', () => {
    expect(BookingDetailScreen).toBeDefined();
  });
});
