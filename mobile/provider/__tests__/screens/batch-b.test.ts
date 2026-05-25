import { ForgotPasswordScreen } from '@/screens/ForgotPasswordScreen';
import { EmptyState } from '@/ui/EmptyState';
import { ErrorState } from '@/ui/ErrorState';

jest.mock('@/storage/secureStore');

describe('Batch B — provider screens & components', () => {
  it('ForgotPasswordScreen exports without crash', () => {
    expect(ForgotPasswordScreen).toBeDefined();
  });

  it('EmptyState exports without crash', () => {
    expect(EmptyState).toBeDefined();
  });

  it('ErrorState exports without crash', () => {
    expect(ErrorState).toBeDefined();
  });
});
