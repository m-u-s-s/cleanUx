// Regression guard: importing EmbeddedModuleRoute transitively resolves '@/webview'
// and '@/hooks/useDeviceId'. If the babel module-resolver alias for '@/webview'
// is missing, this import throws "Cannot find module" and the test fails.
import { EmbeddedModuleRoute } from '../EmbeddedModuleRoute';

describe('EmbeddedModuleRoute module resolution', () => {
  it('imports cleanly (verifies @/webview + @/hooks aliases resolve)', () => {
    expect(EmbeddedModuleRoute).toBeDefined();
    expect(typeof EmbeddedModuleRoute).toBe('function');
  });
});
