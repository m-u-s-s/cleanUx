import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/api';

/**
 * CE QUE L'API ENVOIE VRAIMENT.
 *
 * Cette interface annonçait déjà `title` et `body`, et les deux écrans les affichaient — mais
 * `ApiNotificationController::serialize()` ne rendait que `id`, `type`, `data`, `read_at` et
 * `created_at`. TypeScript ne pouvait pas le voir : une interface décrit une intention, pas une
 * réponse HTTP. La liste chargeait donc ses lignes et les rendait toutes VIDES.
 *
 * Le serveur remplit maintenant ces champs depuis `NotificationPresenter`, la même source que le
 * web — sans quoi la même notification s'intitulerait « Rendez-vous » d'un côté et « RappelRdv »
 * de l'autre.
 */
export type NotificationSeverity = 'default' | 'info' | 'success' | 'warning' | 'danger';

export interface AppNotification {
  id: string;
  /** Nom de classe brut (`RappelRdv`) — conservé pour la traçabilité, pas pour l'affichage. */
  type: string;
  /** Clé de famille normalisée : `rendezvous`, `finance`, `urgent`… */
  type_key: string;
  /** Le libellé lisible de cette famille : « Rendez-vous », « Finance »… */
  label: string;
  title: string;
  body: string;
  severity: NotificationSeverity;
  /** Références utiles déjà filtrées : mission, facture, zone, service, compte Google. */
  context: Record<string, string | number>;
  /** La page web où régler le problème, et ce qu'elle promet. */
  action_url: string;
  /**
   * Le CHEMIN de cette page quand elle appartient à l'application — ce que l'hôte WebView attend.
   * Vide si la cible est ailleurs : le natif ouvre alors le navigateur.
   */
  action_path: string;
  action_label: string;
  read_at: string | null;
  created_at: string;
  data?: Record<string, unknown>;
}

export function useNotifications() {
  return useQuery<AppNotification[]>({
    queryKey: ['notifications'],
    queryFn: async () => {
      const res = await apiClient.get('/notifications');
      return res.data.data ?? res.data;
    },
    refetchInterval: 30000,
  });
}

/** La fiche complète d'une notification. 404 si elle n'est pas au compte connecté. */
export function useNotification(id: string) {
  return useQuery<AppNotification>({
    queryKey: ['notifications', id],
    queryFn: async () => {
      const res = await apiClient.get(`/notifications/${id}`);
      return res.data.data ?? res.data;
    },
    enabled: id.length > 0,
  });
}

export function useMarkAllRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => apiClient.post('/notifications/read-all'),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }),
  });
}

/**
 * Marque UNE notification comme lue.
 *
 * L'endpoint existait déjà côté serveur sans aucun appelant mobile : le compteur ne redescendait
 * qu'en marquant tout d'un coup. La fiche l'appelle à l'ouverture — un GET ne doit pas modifier
 * l'état, donc la lecture est un geste explicite et rejouable.
 */
export function useMarkRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => apiClient.post(`/notifications/${id}/read`),
    onSuccess: (_data, id) => {
      qc.invalidateQueries({ queryKey: ['notifications'] });
      qc.invalidateQueries({ queryKey: ['notifications', id] });
    },
  });
}
