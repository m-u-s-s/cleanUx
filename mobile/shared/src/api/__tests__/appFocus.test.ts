/**
 * React Query doit apprendre le cycle de vie d'une application mobile.
 *
 * Il est écrit pour le web, où « focus » signifie un onglet redevenu actif. En React Native cette
 * notion n'existe pas : sans ce pont, `refetchOnWindowFocus` ne se déclenche jamais et les
 * minuteries de `refetchInterval` restent suspendues tant que l'application dort.
 *
 * Défaut observé : un client laisse l'écran de suivi ouvert, verrouille son téléphone, y revient
 * — et voit la position, l'ETA et le statut figés à l'instant où il est parti. Un prestataire
 * arrivé depuis longtemps y paraît encore en route, sans que rien ne signale que l'écran ment.
 */
import { AppState } from 'react-native';
import { focusManager } from '@tanstack/react-query';
import { bindAppStateToQueryFocus } from '../appFocus';

describe('Pont AppState → React Query', () => {
  let listener: ((status: string) => void) | null = null;
  let removed = false;

  beforeEach(() => {
    listener = null;
    removed = false;
    jest.spyOn(AppState, 'addEventListener').mockImplementation(((_event: string, cb: any) => {
      listener = cb;

      return { remove: () => { removed = true; } };
    }) as never);
  });

  afterEach(() => jest.restoreAllMocks());

  it('signale le focus au retour au premier plan', () => {
    const handleFocus = jest.fn();
    jest.spyOn(focusManager, 'setEventListener').mockImplementation((setup: any) => setup(handleFocus));

    bindAppStateToQueryFocus();
    listener?.('active');

    expect(handleFocus).toHaveBeenCalledWith(true);
  });

  it('signale la perte de focus en arrière-plan', () => {
    const handleFocus = jest.fn();
    jest.spyOn(focusManager, 'setEventListener').mockImplementation((setup: any) => setup(handleFocus));

    bindAppStateToQueryFocus();
    listener?.('background');

    expect(handleFocus).toHaveBeenCalledWith(false);
  });

  /**
   * `inactive` est transitoire sur iOS — bascule d'applications, centre de contrôle, appel
   * entrant. Le compter comme un retour provoquerait des rafraîchissements en rafale.
   */
  it('ne compte pas l’état transitoire iOS comme un retour', () => {
    const handleFocus = jest.fn();
    jest.spyOn(focusManager, 'setEventListener').mockImplementation((setup: any) => setup(handleFocus));

    bindAppStateToQueryFocus();
    listener?.('inactive');

    expect(handleFocus).toHaveBeenCalledWith(false);
  });

  /** Sans désabonnement, chaque remontage laisserait un écouteur derrière lui. */
  it('rend de quoi se détacher', () => {
    let cleanup: (() => void) | undefined;
    jest.spyOn(focusManager, 'setEventListener').mockImplementation((setup: any) => {
      cleanup = setup(jest.fn());
    });

    bindAppStateToQueryFocus();
    cleanup?.();

    expect(removed).toBe(true);
  });
});
