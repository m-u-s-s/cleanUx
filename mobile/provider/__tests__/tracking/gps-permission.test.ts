import { renderHook, waitFor } from '@testing-library/react-native';

// Note: préfixées `mock` car babel-plugin-jest-hoist (jest.mock hoisting) interdit de
// référencer depuis la factory une variable hors-scope qui ne commence pas par `mock`
// (convention déjà utilisée ailleurs dans ce repo, ex. `mockNavigate` dans batch-c.test.ts).
const mockRequestForegroundPermissionsAsync = jest.fn();
const mockWatchPositionAsync = jest.fn();

jest.mock('expo-location', () => ({
  requestForegroundPermissionsAsync: (...a: any[]) => mockRequestForegroundPermissionsAsync(...a),
  watchPositionAsync: (...a: any[]) => mockWatchPositionAsync(...a),
  Accuracy: { High: 4, Balanced: 3 },
}));

import { useGpsWatcher } from '@/tracking/hooks';
import { useCurrentPosition } from '@/tracking/useCurrentPosition';

describe('useGpsWatcher — état de permission', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockWatchPositionAsync.mockResolvedValue({ remove: jest.fn() });
  });

  it('rapporte "granted" quand la permission est accordée', async () => {
    mockRequestForegroundPermissionsAsync.mockResolvedValue({ status: 'granted' });

    const { result } = renderHook(() => useGpsWatcher(true, jest.fn()));

    await waitFor(() => expect(result.current.permission).toBe('granted'));
    expect(mockWatchPositionAsync).toHaveBeenCalled();
  });

  it('rapporte "denied" et ne démarre aucun suivi quand elle est refusée', async () => {
    mockRequestForegroundPermissionsAsync.mockResolvedValue({ status: 'denied' });

    const { result } = renderHook(() => useGpsWatcher(true, jest.fn()));

    await waitFor(() => expect(result.current.permission).toBe('denied'));
    expect(mockWatchPositionAsync).not.toHaveBeenCalled();
  });

  it('reste "pending" quand le hook est désactivé', () => {
    const { result } = renderHook(() => useGpsWatcher(false, jest.fn()));

    expect(result.current.permission).toBe('pending');
    expect(mockRequestForegroundPermissionsAsync).not.toHaveBeenCalled();
  });

  it('nettoie l\'abonnement natif si le composant est démonté pendant watchPositionAsync', async () => {
    mockRequestForegroundPermissionsAsync.mockResolvedValue({ status: 'granted' });

    // watchPositionAsync reste en attente tant qu'on ne résout pas manuellement — ça simule
    // le cas où le démontage (ou enabled -> false) survient pendant cet await natif.
    const removeSpy = jest.fn();
    let resolveWatch!: (sub: { remove: () => void }) => void;
    const watchPromise = new Promise<{ remove: () => void }>((resolve) => { resolveWatch = resolve; });
    mockWatchPositionAsync.mockReturnValue(watchPromise);

    const { unmount } = renderHook(() => useGpsWatcher(true, jest.fn()));

    // La permission est accordée et watchPositionAsync a été appelé, mais n'a pas encore résolu.
    await waitFor(() => expect(mockWatchPositionAsync).toHaveBeenCalled());

    // Démontage pendant que watchPositionAsync est encore en attente.
    unmount();

    // watchPositionAsync se résout seulement maintenant, après le nettoyage.
    resolveWatch({ remove: removeSpy });

    // Sans la seconde vérification de `cancelled`, cet abonnement serait publié après coup
    // et personne ne l'arrêterait jamais : remove() ne serait jamais appelé.
    await waitFor(() => expect(removeSpy).toHaveBeenCalledTimes(1));
  });

  // Les deux cas ci-dessous : un rejet (services de localisation coupés, module natif absent)
  // n'est PAS un refus de permission, et l'IIFE asynchrone n'avait aucun catch. La promesse
  // partait en rejet non géré et `permission` restait 'pending' à jamais — la carte affichait
  // alors le repli pays sans position ET sans notice, donc sans la moindre explication.
  it('rapporte "denied" quand la demande de permission elle-même échoue', async () => {
    mockRequestForegroundPermissionsAsync.mockRejectedValue(new Error('Location services are disabled'));

    const { result } = renderHook(() => useGpsWatcher(true, jest.fn()));

    await waitFor(() => expect(result.current.permission).toBe('denied'));
  });

  it('rapporte "denied" quand le démarrage du suivi échoue', async () => {
    mockRequestForegroundPermissionsAsync.mockResolvedValue({ status: 'granted' });
    mockWatchPositionAsync.mockRejectedValue(new Error('E_LOCATION_UNAVAILABLE'));

    const { result } = renderHook(() => useGpsWatcher(true, jest.fn()));

    await waitFor(() => expect(result.current.permission).toBe('denied'));
  });
});

describe('useCurrentPosition — surveillance conditionnelle', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockRequestForegroundPermissionsAsync.mockResolvedValue({ status: 'granted' });
    mockWatchPositionAsync.mockResolvedValue({ remove: jest.fn() });
  });

  it('ne demande rien tant que l écran appelant n est pas au premier plan', () => {
    renderHook(() => useCurrentPosition(false));

    expect(mockRequestForegroundPermissionsAsync).not.toHaveBeenCalled();
    expect(mockWatchPositionAsync).not.toHaveBeenCalled();
  });

  it('surveille par défaut, sans argument', async () => {
    renderHook(() => useCurrentPosition());

    await waitFor(() => expect(mockWatchPositionAsync).toHaveBeenCalled());
  });
});
