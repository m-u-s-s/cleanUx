import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import { apiClient } from '@/api';
import type { FilterValues, ResourcePage, ResourceRow } from './types';

/**
 * La liste d'un domaine, page par page.
 *
 * PAGINATION PAR CURSEUR, pas par offset : le serveur lit des tables qui bougent pendant qu'on
 * les feuillette, et un offset y saute ou répète des lignes sans rien dire. Le curseur voyage
 * opaque — le mobile ne l'interprète jamais.
 *
 * Le DESCRIPTEUR arrive avec chaque page. C'est redondant, et c'est voulu : il rend chaque page
 * autonome, donc un rechargement partiel ne peut pas rendre une liste dont on aurait perdu les
 * colonnes.
 */
export function useResourceIndex(
  resource: string,
  options: { filters?: FilterValues; sort?: string; direction?: 'asc' | 'desc' } = {},
) {
  const { filters = {}, sort, direction } = options;

  return useInfiniteQuery<ResourcePage>({
    queryKey: ['admin', 'console', resource, { filters, sort, direction }],
    initialPageParam: null as string | null,
    queryFn: async ({ pageParam }) => {
      const params: Record<string, unknown> = {};

      for (const [key, value] of Object.entries(filters)) {
        if (value === '' || value === false || value === undefined || value === null) {
          continue;
        }
        params[`filters[${key}]`] = value === true ? 1 : value;
      }

      if (sort) {
        params.sort = sort;
      }
      if (direction) {
        params.direction = direction;
      }
      if (pageParam) {
        params.cursor = pageParam;
      }

      return (await apiClient.get(`/admin/console/${resource}`, { params })).data as ResourcePage;
    },
    getNextPageParam: (lastPage) => lastPage.next_cursor,
  });
}

export function useResourceDetail(resource: string, id: string | number) {
  return useQuery<ResourceRow>({
    queryKey: ['admin', 'console', resource, 'detail', String(id)],
    queryFn: async () => (await apiClient.get(`/admin/console/${resource}/${id}`)).data.row,
  });
}

/**
 * Création et édition.
 *
 * L'invalidation porte sur TOUT le domaine, pas seulement sur la ligne touchée : une création
 * change la liste, et une édition peut changer le rang d'une ligne dans le tri courant.
 */
export function useResourceSave(resource: string, id?: string | number) {
  const qc = useQueryClient();

  return useMutation<ResourceRow, unknown, Record<string, unknown>>({
    mutationFn: async (values) => {
      const { data } = id
        ? await apiClient.patch(`/admin/console/${resource}/${id}`, values)
        : await apiClient.post(`/admin/console/${resource}`, values);

      return data.row as ResourceRow;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin', 'console', resource] }),
  });
}

export function useResourceDelete(resource: string) {
  const qc = useQueryClient();

  return useMutation<void, unknown, string | number>({
    mutationFn: async (id) => {
      await apiClient.delete(`/admin/console/${resource}/${id}`);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin', 'console', resource] }),
  });
}

/**
 * Une action métier nommée.
 *
 * Le mobile envoie une clé ; l'exécution vit côté serveur, dans les services qui portent la
 * règle. Rien du comportement de l'action ne transite ici — c'est ce qui garde le moteur honnête.
 */
export function useResourceAction(resource: string) {
  const qc = useQueryClient();

  return useMutation<unknown, unknown, { id: string | number; action: string }>({
    mutationFn: async ({ id, action }) =>
      (await apiClient.post(`/admin/console/${resource}/${id}/actions/${action}`)).data.result,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin', 'console', resource] }),
  });
}

/**
 * Le message d'erreur d'une réponse serveur, et les erreurs par champ.
 *
 * Le moteur rend `{ok:false, error, errors}`. Sans cette lecture, un formulaire refusé
 * n'afficherait qu'un « une erreur est survenue » générique alors que le serveur a dit
 * précisément quel champ pose problème.
 */
export function readServerErrors(error: unknown): {
  message: string;
  fields: Record<string, string>;
} {
  /*
   * L'intercepteur du client normalise TOUTE erreur en `ApiError(status, errorCode, message,
   * errors)` — `error.response` n'existe donc plus quand on arrive ici. Lire la forme axios brute
   * rendait un message générique pour chaque refus, alors que le serveur avait dit précisément
   * quel champ pose problème. La forme axios reste lue en repli, pour les appels qui
   * contourneraient l'intercepteur.
   */
  const normalisee = error as {
    errorCode?: string;
    errors?: Record<string, string[]>;
    response?: { data?: Record<string, unknown> };
  };

  const brute = (normalisee?.response?.data ?? {}) as Record<string, unknown>;

  const errors =
    normalisee?.errors ?? (brute.errors as Record<string, string[]> | undefined);

  const fields: Record<string, string> = {};

  if (errors) {
    for (const [key, messages] of Object.entries(errors)) {
      const first = messages?.[0];
      if (first) {
        fields[key] = first;
      }
    }
  }

  const known: Record<string, string> = {
    validation_failed: 'Certains champs doivent être corrigés.',
    unknown_resource: 'Ce module n’est pas servi par la console.',
    unknown_action: 'Cette action n’existe pas.',
    read_only_resource: 'Ce module est en lecture seule.',
    not_found: 'Cet élément n’existe plus.',
    forbidden_not_admin: 'Votre compte n’a pas les droits d’administration.',
    invalid_sort: 'Ce tri n’est pas autorisé sur ce module.',
    invalid_direction: 'Ce sens de tri n’est pas valide.',
  };

  const code =
    normalisee?.errorCode ??
    (typeof brute.error_code === 'string' ? brute.error_code : undefined) ??
    (typeof brute.error === 'string' ? brute.error : '');

  return { message: known[code] ?? 'Une erreur est survenue.', fields };
}
