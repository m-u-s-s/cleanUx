import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';

export type StepCode =
  | 'profile_complete'
  | 'contract_sign'
  | 'kyc_check'
  | 'document_upload'
  | 'skill_declare';

export interface OnboardingStep {
  id: number;
  position: number;
  code: StepCode | string;
  label: string;
  description: string | null;
  step_type: string;
  required: boolean;
  is_skippable: boolean;
  /** null tant que rien n'a été tenté, puis 'pending' | 'completed' | 'skipped'. */
  completion_status: string | null;
  completed_at: string | null;
}

export interface OnboardingProgress {
  progress: {
    id: number;
    status: string;
    percent_complete: number;
    current_step_code: string | null;
    completed_at: string | null;
  };
  journey: { code: string; name: string; role: string | null };
  steps: OnboardingStep[];
}

export const ONBOARDING_QUERY_KEY = ['onboarding', 'me'] as const;

/**
 * Progression du parcours de vérification de l'utilisateur courant.
 *
 * GET /v2/onboarding/me démarre le parcours s'il n'existe pas encore : cet appel est donc sûr
 * même juste après l'inscription, et sert aussi de filet quand le démarrage côté serveur a
 * échoué en soft-fail.
 */
export function useOnboardingProgress(enabled: boolean = true) {
  return useQuery<OnboardingProgress>({
    queryKey: ONBOARDING_QUERY_KEY,
    queryFn: async () => (await apiClient.get('/v2/onboarding/me')).data,
    enabled,
    staleTime: 0,
  });
}

/**
 * Marque une étape complétée. Le serveur REVALIDE : une étape ne passe que si son validateur
 * est satisfait, quoi qu'envoie l'app. Un 422 signifie donc que la condition réelle n'est pas
 * remplie, pas que la requête était malformée.
 */
export function useCompleteStep() {
  const queryClient = useQueryClient();

  return useMutation<unknown, ApiError, { stepId: number; payload?: Record<string, unknown> }>({
    mutationFn: async ({ stepId, payload }) =>
      (await apiClient.post(`/v2/onboarding/steps/${stepId}/complete`, { payload: payload ?? {} })).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ONBOARDING_QUERY_KEY }),
  });
}

/**
 * Le parcours est-il terminé ? Sert de garde d'accès au tableau de bord.
 *
 * Prudence délibérée quand la progression n'est pas encore connue : on ne considère PAS le
 * parcours comme terminé, pour ne pas laisser entrevoir un dashboard qui disparaîtrait à la
 * réponse suivante. En revanche une ERREUR de chargement ne doit pas enfermer l'utilisateur
 * dans un écran vide — l'appelant décide, d'où les deux informations distinctes.
 */
export function isJourneyComplete(data: OnboardingProgress | undefined): boolean {
  if (!data) return false;

  return data.steps
    .filter(step => step.required)
    .every(step => step.completion_status === 'completed' || step.completion_status === 'skipped');
}
