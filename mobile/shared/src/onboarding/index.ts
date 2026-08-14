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

/**
 * LE DOSSIER VÉHICULE, verdict compris.
 *
 * `required` est ce qui rend l'étape supportable pour tout le monde : le parcours d'inscription est
 * UNIQUE — le même pour le peintre, la garde d'enfants et le chauffeur — et une étape « déclarez
 * votre véhicule » qui bloquerait un jardinier empêcherait des inscriptions parfaitement légitimes.
 *
 * `reason` porte le motif exact d'un refus : « aucun véhicule déclaré », « date manquante », « six
 * ans, la limite est de quatre ». Un refus qui ne dit pas lequel des trois fait recommencer au
 * hasard.
 */
export interface VehicleDossier {
  required: boolean;
  compliant: boolean;
  reason: string | null;
  max_age_years: number;
  age_years: number | null;
  registration_document_uploaded: boolean;
  trades: string[];
  vehicle: {
    id: number;
    plate: string | null;
    brand: string | null;
    model: string | null;
    vehicle_type: string | null;
    registered_at: string | null;
    registered_country: string | null;
  } | null;
}

export const VEHICLE_DOSSIER_QUERY_KEY = ['provider', 'onboarding', 'vehicle'] as const;

export function useVehicleDossier(enabled: boolean = true) {
  return useQuery<VehicleDossier>({
    queryKey: VEHICLE_DOSSIER_QUERY_KEY,
    queryFn: async () => {
      const { data } = await apiClient.get('/provider/onboarding/vehicle');

      return data.data;
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
  /**
   * Avancement technique : pending | in_review | awaiting_documents | clear | consider |
   * unidentified | rejected | expired | cancelled.
   */
  status?: string;
  /**
   * VERDICT, et son vocabulaire est distinct de celui du statut : pending | approved |
   * rejected | manual_review. `clear` et `consider` sont des STATUTS, jamais des décisions —
   * les confondre faisait qu'aucun état ne correspondait après le démarrage, et l'écran ne
   * changeait pas d'un iota au clic.
   */
  decision?: string | null;
  rejection_reason?: string | null;
  provider_verification_status?: string | null;
}

export const KYC_STATUS_QUERY_KEY = ['onboarding', 'kyc-status'] as const;

/**
 * Le tiers n'a pas encore tranché. `pending` est la valeur que le serveur écrit à la création ;
 * l'absence de décision est traitée pareil, par prudence.
 */
export function isKycPending(status: KycStatus | undefined): boolean {
  if (!status?.has_verification) return false;

  return status.decision === 'pending' || status.decision === null || status.decision === undefined;
}

/** Un humain examine le dossier : ce n'est ni un refus ni une validation. */
export function isKycUnderReview(status: KycStatus | undefined): boolean {
  return status?.decision === 'manual_review';
}

export function isKycRefused(status: KycStatus | undefined): boolean {
  return status?.decision === 'rejected';
}

/**
 * `provider_verification_status` compte aussi : l'auto-approbation du module d'identité le pose
 * sur le profil, et un prestataire vérifié par une autre voie ne doit pas être redemandé.
 */
export function isKycVerified(status: KycStatus | undefined): boolean {
  return status?.decision === 'approved' || status?.provider_verification_status === 'verified';
}

export function useKycStatus(enabled: boolean = true) {
  return useQuery<KycStatus>({
    queryKey: KYC_STATUS_QUERY_KEY,
    queryFn: async () => (await apiClient.get('/provider/kyc/status')).data,
    enabled,
    staleTime: 0,
    // Cinq secondes tant que le tiers n'a pas tranché, puis plus rien : interroger en boucle une
    // décision déjà rendue ne ferait que consommer batterie et données.
    refetchInterval: query =>
      isKycPending(query.state.data) || isKycUnderReview(query.state.data) ? 5000 : false,
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

/**
 * Zones d'intervention proposées au prestataire.
 *
 * `POST /provider/onboarding/skills` accepte `service_zone_ids` depuis toujours, mais rien ne
 * permettait de connaître les zones existantes : aucun écran n'en proposait, le champ restait
 * vide, et le matching géographique n'avait aucune donnée sur laquelle travailler.
 */
export interface ServiceZone {
  id: number;
  name: string;
  code: string | null;
  coverage_type: string | null;
}

export function useServiceZones(enabled: boolean = true) {
  return useQuery<ServiceZone[]>({
    queryKey: ['onboarding', 'service-zones'],
    queryFn: async () => (await apiClient.get('/provider/onboarding/service-zones')).data.zones ?? [],
    enabled,
  });
}

/**
 * Demande au serveur d'interroger le prestataire d'identité et d'appliquer sa décision.
 *
 * `GET /provider/kyc/status` relit la ligne en base ; il ne la met pas à jour. La décision
 * arrive normalement par webhook, mais `POST /provider/kyc/verifications/{id}/sync` — le point
 * d'entrée prévu quand elle tarde ou n'arrive pas — n'était appelé de nulle part. En
 * développement, où le fournisseur est simulé, aucun webhook ne tombe jamais : sans cet appel,
 * l'étape identité restait « en cours » indéfiniment.
 */
export function useSyncKycStatus() {
  return useMutation<unknown, ApiError, { verificationId: number }>({
    mutationFn: async ({ verificationId }) =>
      (await apiClient.post(`/provider/kyc/verifications/${verificationId}/sync`)).data,
  });
}
