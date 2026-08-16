import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/api';

/**
 * LE CONTRAT DU CONTRÔLE FACIAL, CÔTÉ CLIENT.
 *
 * Ce que le serveur envoie, et RIEN DE PLUS. En particulier : il n'existe aucun champ d'échéance
 * ici, et il ne doit jamais y en avoir. Un prestataire qui connaîtrait la date du prochain
 * contrôle se présenterait en personne juste avant et prêterait son compte le reste du temps —
 * le module aurait coûté cher pour ne rien prouver.
 *
 * `state` dit s'il faut agir MAINTENANT. Jamais quand il faudra agir ensuite.
 */
export type FaceCheckState =
  | 'ok'
  | 'face_enrolment_required'
  | 'face_check_required'
  | 'face_check_pending'
  | 'face_check_blocked';

export interface FaceCheckStatus {
  /** Faux pour un prestataire hors périmètre : aucun de ses métiers ni sa zone ne l'exigent. */
  required: boolean;
  state: FaceCheckState;
  message?: string | null;
  enrolled?: boolean;
  blocked?: boolean;
  block_reason?: string | null;
  id_match_status?: string | null;
  consent_version?: string;
  max_attempts?: number;
  liveness_required?: boolean;
  pending_check?: number | null;
  open_incidents?: number;
}

export interface FaceCheck {
  id: number;
  status: 'pending' | 'passed' | 'failed' | 'abandoned' | 'expired' | 'error';
  attempt_number: number;
  attempts_left: number;
  failure_reason: string | null;
  liveness_result: string | null;
  expires_at: string | null;
  /** Le fournisseur n'a pas encore tranché : on sonde, la porte reste fermée. */
  awaiting_provider_decision: boolean;
}

export const FACE_CHECK_STATUS_KEY = ['face-check', 'status'] as const;

/**
 * L'état du contrôle facial.
 *
 * `refetchInterval` seulement quand un verdict est en attente : sonder en permanence userait la
 * batterie d'un téléphone qui passe la journée dehors, pour une information qui ne change qu'à
 * la suite d'un geste.
 */
export function useFaceCheckStatus(enabled: boolean = true) {
  return useQuery<FaceCheckStatus>({
    queryKey: FACE_CHECK_STATUS_KEY,
    queryFn: async () => {
      const { data } = await apiClient.get('/provider/face-check/status');

      return data.data as FaceCheckStatus;
    },
    enabled,
    staleTime: 15_000,
    refetchInterval: (query) =>
      query.state.data?.state === 'face_check_pending' ? 4_000 : false,
  });
}

/** Enrôle le visage de référence. Le consentement est une condition, pas une case décorative. */
export function useEnrollFace() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (input: { uri: string; name?: string; type?: string }) => {
      const corps = new FormData();

      corps.append('image', {
        uri: input.uri,
        name: input.name ?? 'reference.jpg',
        type: input.type ?? 'image/jpeg',
      } as unknown as Blob);
      corps.append('consent', '1');

      const { data } = await apiClient.post('/provider/face-check/enroll', corps, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      return data.data;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: FACE_CHECK_STATUS_KEY });
    },
  });
}

/**
 * Ouvre un contrôle — ou rend celui qui est déjà ouvert.
 *
 * Le serveur refuse d'en ouvrir un qui n'est pas dû : l'application ne décide pas de la cadence,
 * elle ne fait qu'obéir. Rend `null` quand il n'y a rien à faire.
 */
export function useStartFaceCheck() {
  return useMutation({
    mutationFn: async (): Promise<FaceCheck | null> => {
      const { data } = await apiClient.post('/provider/face-check/start');

      return (data.data?.id ? (data.data as FaceCheck) : null);
    },
  });
}

export function useSubmitFaceCheck() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (input: { checkId: number; uri: string; name?: string; type?: string }) => {
      const corps = new FormData();

      corps.append('image', {
        uri: input.uri,
        name: input.name ?? 'live.jpg',
        type: input.type ?? 'image/jpeg',
      } as unknown as Blob);

      const { data } = await apiClient.post(
        `/provider/face-check/${input.checkId}/submit`,
        corps,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      );

      return data.data as FaceCheck;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: FACE_CHECK_STATUS_KEY });
    },
  });
}

/** Relit un contrôle dont le verdict était différé. */
export function useFaceCheck(checkId: number | null) {
  return useQuery<FaceCheck>({
    queryKey: ['face-check', 'check', checkId],
    queryFn: async () => {
      const { data } = await apiClient.get(`/provider/face-check/${checkId}`);

      return data.data as FaceCheck;
    },
    enabled: checkId !== null,
    refetchInterval: (query) => (query.state.data?.awaiting_provider_decision ? 3_000 : false),
  });
}

export function useAbandonFaceCheck() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (checkId: number) => {
      const { data } = await apiClient.post(`/provider/face-check/${checkId}/abandon`);

      return data.data as FaceCheck;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: FACE_CHECK_STATUS_KEY });
    },
  });
}

/**
 * « Le contrôle ne fonctionne pas. »
 *
 * CE GESTE NE DÉBLOQUE RIEN, et l'écran le dit franchement. Un bouton qui accorderait un sursis
 * serait la porte de sortie de quiconque veut éviter le contrôle, et elle serait empruntée dès la
 * première semaine.
 */
export function useReportFaceIncident() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (input: {
      message: string;
      checkId?: number | null;
      diagnostics?: Record<string, string | undefined>;
    }) => {
      const { data } = await apiClient.post('/provider/face-check/incidents', {
        message: input.message,
        face_check_id: input.checkId ?? null,
        diagnostics: input.diagnostics ?? {},
      });

      return data.data;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: FACE_CHECK_STATUS_KEY });
    },
  });
}

/** Retire le consentement. La conséquence est annoncée par l'écran AVANT l'appel. */
export function useWithdrawFaceConsent() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post('/provider/face-check/consent/withdraw', { confirm: true });

      return data.data;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: FACE_CHECK_STATUS_KEY });
    },
  });
}

/**
 * L'état bloque-t-il l'accès au terrain ?
 *
 * `undefined` LAISSE PASSER — même règle que l'onboarding : une requête qui échoue ne doit pas
 * enfermer quelqu'un hors de son application. Le serveur, lui, refusera de toute façon les gestes
 * qui comptent ; c'est là que la sécurité se joue, pas dans la navigation.
 */
export function faceCheckBloqueLeTerrain(status?: FaceCheckStatus): boolean {
  if (!status || !status.required) {
    return false;
  }

  return status.state !== 'ok';
}
