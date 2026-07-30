import { AppState, type AppStateStatus } from 'react-native';
import { focusManager } from '@tanstack/react-query';

/**
 * Relie React Query au cycle de vie de l'application.
 *
 * React Query est écrit pour le web, où « focus » signifie un onglet redevenu actif. En React
 * Native cette notion n'existe pas : sans ce pont, `refetchOnWindowFocus` ne se déclenche
 * JAMAIS, et les minuteries de `refetchInterval` sont suspendues tant que l'application est en
 * arrière-plan.
 *
 * Conséquence observée : un client qui laisse l'écran de suivi ouvert, verrouille son téléphone,
 * puis y revient voit indéfiniment la position, l'ETA et le statut figés à l'instant où il est
 * parti — un prestataire arrivé depuis longtemps y paraît encore en route. Rien ne le signale,
 * l'écran a simplement l'air à jour.
 *
 * Le rappel doit RENDRE sa fonction de désabonnement : React Query l'appelle lorsqu'il remplace
 * l'écouteur, et l'oublier laisserait fuiter un abonnement à `AppState`.
 */
export function bindAppStateToQueryFocus(): void {
  focusManager.setEventListener(handleFocus => {
    const subscription = AppState.addEventListener('change', (status: AppStateStatus) => {
      // `inactive` est un état transitoire d'iOS — bascule d'applications, centre de contrôle,
      // appel entrant. Le traiter comme une perte de focus provoquerait des allers-retours
      // inutiles ; seul `active` compte comme un retour.
      handleFocus(status === 'active');
    });

    return () => subscription.remove();
  });
}
