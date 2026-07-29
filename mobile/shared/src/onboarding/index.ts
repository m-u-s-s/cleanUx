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
  /**
   * Codes des étapes qui doivent être terminées avant celle-ci. Le serveur le renvoyait déjà ;
   * le cockpit s'en sert pour verrouiller une carte plutôt que de laisser l'utilisateur
   * l'ouvrir et se heurter à un 422.
   */
  depends_on?: string[] | null;
}

/** Statut d'une pièce justificative, tel que le prestataire doit le lire. */
export interface OnboardingDocument {
  id: number;
  type: string;
  status: 'pending_review' | 'approved' | 'rejected' | string;
  file_name: string | null;
  /** Renseigné sur un refus : sans lui, la même pièce est redéposée puis refusée à nouveau. */
  rejection_reason: string | null;
  uploaded_at: string | null;
  reviewed_at: string | null;
}

/** Une exigence documentaire et, le cas échéant, la pièce déjà déposée pour la satisfaire. */
export interface DocumentRequirement {
  type: string;
  label: string;
  help: string;
  required: boolean;
  /** Types acceptés : la pièce d'identité vaut carte, passeport ou titre de séjour. */
  accepts: string[];
  document: OnboardingDocument | null;
}

export const ONBOARDING_DOCUMENTS_QUERY_KEY = ['onboarding', 'documents'] as const;

/**
 * Justificatifs attendus de CE prestataire, chacun avec son état.
 *
 * La liste dépend de ses métiers — un électricien fournit sa certification, un peintre son
 * assurance — et c'est le serveur qui la dérive : l'application ne décide pas de ce qui est exigé.
 */
export function useOnboardingDocuments(enabled: boolean = true) {
  return useQuery<{ requirements: DocumentRequirement[]; documents: OnboardingDocument[] }>({
    queryKey: ONBOARDING_DOCUMENTS_QUERY_KEY,
    queryFn: async () => {
      const { data } = await apiClient.get('/provider/onboarding/documents');

      return { requirements: data.requirements ?? [], documents: data.documents ?? [] };
    },
    enabled,
    staleTime: 0,
  });
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

/**
 * État de la vérification d'identité.
 *
 * `GET /provider/kyc/status` existe depuis le module KYC mais n'était appelé nulle part :
 * l'application lançait la vérification puis affichait « lancée » indéfiniment, sans jamais
 * savoir si elle avait abouti. La décision vient d'un tiers et tombe par webhook, donc à un
 * moment que l'application ne choisit pas — d'où l'interrogation périodique tant qu'elle est en
 * cours, et l'arrêt dès qu'elle est tranchée.
 */
export interface KycStatus {
  has_verification: boolean;
  verification_id?: number;
  status?: string;
  /** 'clear' | 'consider' | 'rejected' | null — null tant que le tiers n'a pas tranché. */
  decision?: string | null;
  rejection_reason?: string | null;
  provider_verification_status?: string | null;
}

export const KYC_STATUS_QUERY_KEY = ['onboarding', 'kyc-status'] as const;

/** Une vérification sans décision est encore en cours chez le prestataire d'identité. */
export function isKycPending(status: KycStatus | undefined): boolean {
  if (!status?.has_verification) return false;

  return status.decision === null || status.decision === undefined;
}

export function isKycVerified(status: KycStatus | undefined): boolean {
  return status?.decision === 'clear' || status?.provider_verification_status === 'verified';
}

export function useKycStatus(enabled: boolean = true) {
  return useQuery<KycStatus>({
    queryKey: KYC_STATUS_QUERY_KEY,
    queryFn: async () => (await apiClient.get('/provider/kyc/status')).data,
    enabled,
    staleTime: 0,
    // Cinq secondes tant que le tiers n'a pas tranché, puis plus rien : interroger en boucle une
    // décision déjà rendue ne ferait que consommer batterie et données.
    refetchInterval: query => (isKycPending(query.state.data) ? 5000 : false),
  });
}

/**
 * Contrat prestataire, rendu et signé via Contracts v2.
 *
 * L'écran affichait un texte codé en dur et « signait » en transmettant un simple numéro de
 * version : aucune signature n'existait en base, donc aucune piste d'audit, alors que le module
 * Contracts v2 — modèles versionnés, rendu, signature horodatée avec empreinte, PDF — était
 * entièrement construit et jamais appelé.
 */
export interface ContractDocument {
  id: number;
  code: string;
  body_rendered_html: string;
  status: string;
}

/** Rend le contrat pour l'utilisateur courant, en substituant ses données au modèle. */
export function useRenderContract() {
  return useMutation<ContractDocument, ApiError, { templateCode: string }>({
    mutationFn: async ({ templateCode }) => {
      const { data } = await apiClient.post('/v2/contracts/documents', {
        template_code: templateCode,
      });

      return data.document as ContractDocument;
    },
  });
}

/**
 * Signe le document. `signature_data` porte le consentement exprimé — le service en dérive une
 * empreinte horodatée avec l'adresse et le terminal, ce qui fait la valeur probante.
 */
export function useSignContract() {
  return useMutation<unknown, ApiError, { documentId: number; signerName: string }>({
    mutationFn: async ({ documentId, signerName }) =>
      (await apiClient.post(`/v2/contracts/documents/${documentId}/sign`, {
        signature_data: `accepted:${signerName}`,
        signer_name: signerName,
        terms_accepted: true,
      })).data,
  });
}
