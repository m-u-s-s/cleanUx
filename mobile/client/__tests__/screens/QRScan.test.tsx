import { QRScanScreen } from '@/screens/QRScanScreen';

jest.mock('@/storage/secureStore');

describe('QRScanScreen', () => {
  it('exports without crash', () => {
    expect(QRScanScreen).toBeDefined();
  });
});
