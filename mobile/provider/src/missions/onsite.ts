import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient, ApiError, offlineAwareMutation } from '@/api';
import { readScanPosition } from '@/tracking';
import { pickImage } from '@/screens/onboarding/documentPicker';

/**
 * LE KIT « SUR PLACE » — état des lieux, imprévus, fil de l'intervention.
 *
 * Le relevé de position accompagne CHAQUE cliché, et il est pris au moment de l'envoi et non au
 * montage de l'écran : une photo prise dans la cave n'a pas la même position que l'entrée, et
 * c'est précisément l'écart qui fait la valeur de la preuve. Son échec n'empêche rien — un
 * sous-sol sans GPS reste un endroit où l'on travaille.
 */

export type MissionMediaType = 'before_photo' | 'after_photo' | 'incident_photo';

export type MissionIncidentType =
  | 'preexisting_damage'
  | 'access_impossible'
  | 'missing_item'
  | 'other';

export interface MissionMediaItem {
  id: number;
  type: MissionMediaType;
  label: string;
  caption: string | null;
  url: string | null;
  taken_at: string | null;
  received_at: string | null;
  lat: number | null;
  lng: number | null;
  accuracy_m: number | null;
  fingerprint: string | null;
}

export interface MissionIncidentItem {
  id: number;
  type: MissionIncidentType;
  label: string;
  severity: string;
  status: string;
  description: string | null;
  reported_at: string | null;
  notified_at: string | null;
  photo: MissionMediaItem | null;
}

/**
 * Un supplément proposé sur place, et où en est la réponse du client.
 *
 * `awaiting_client` est la seule question que pose l'écran : le prestataire a besoin de savoir s'il
 * peut commencer le travail, pas de relire l'historique des statuts.
 */
export interface MissionExtraItem {
  id: number;
  label: string;
  description: string | null;
  price_cents: number;
  price: number;
  currency: string;
  status: 'proposed' | 'approved' | 'declined' | 'charged';
  awaiting_client: boolean;
  proposed_by: string | null;
  proposed_at: string | null;
  approved_at: string | null;
  declined_at: string | null;
  charged_at: string | null;
}

/**
 * La fiche d'accès au lieu — codes, étage, consignes.
 *
 * `available` porte la décision : elle ne s'ouvre qu'une fois l'arrivée confirmée, parce qu'un code
 * d'alarme et l'emplacement d'une boîte à clés sont les clés du domicile de quelqu'un.
 */
export interface MissionAccessSheet {
  available: boolean;
  address: string | null;
  floor: string | null;
  access_instructions: string | null;
  alarm_code_required: boolean;
  access_window: string | null;
  /**
   * LA CONSIGNE DE DERNIÈRE MINUTE — « le digicode a changé ce matin ».
   *
   * Dans son propre champ, et pas fondue dans `access_instructions` : l'écran doit pouvoir la
   * DISTINGUER, sinon elle se perd au milieu d'un paragraphe qu'on a déjà lu la fois d'avant.
   */
  live_note?: string | null;
  live_note_at?: string | null;
  notes: string | null;
  message?: string | null;

  /**
   * LES TROIS CONTRAINTES DE LA RÉSERVATION — pas celles du lieu.
   *
   * `preferences` porte le carnet du LIEU, ce qui vaut pour un site visité chaque semaine.
   * Celles-ci sont ce que le client a répondu POUR CETTE FOIS, et elles n'arrivaient nulle
   * part : le prestataire se présentait sans savoir s'il devait charger son matériel.
   *
   * Absentes quand la mission n'a pas de réservation rattachée.
   */
  constraints?: {
    equipment_provided: boolean;
    pets_on_site: boolean;
    parking_available: boolean;
  } | null;
}

export interface MissionTimelineEntry {
  kind: 'milestone' | 'checklist' | 'incident' | 'media';
  key: string;
  label: string;
  at: string | null;
  severity?: string;
  met?: boolean;
  media_type?: MissionMediaType;
}

export interface MissionTimeline {
  mission_id: number | null;
  status: string | null;
  started_at: string | null;
  estimated_end_at: string | null;
  progress: { done: number; total: number; percent: number };
  entries: MissionTimelineEntry[];
}

export const INCIDENT_TYPES: { value: MissionIncidentType; label: string }[] = [
  { value: 'preexisting_damage', label: 'Dégât préexistant' },
  { value: 'access_impossible', label: 'Accès impossible' },
  { value: 'missing_item', label: 'Objet ou fourniture manquant' },
  { value: 'other', label: 'Autre imprévu' },
];

export function useMissionMedia(missionId: number | null) {
  return useQuery<MissionMediaItem[]>({
    queryKey: ['provider', 'mission', missionId, 'media'],
    queryFn: async () => {
      const res = await apiClient.get(`/provider/missions/${missionId}/media`);

      return res.data.data ?? [];
    },
    enabled: missionId !== null,
  });
}

export function useMissionIncidents(missionId: number | null) {
  return useQuery<MissionIncidentItem[]>({
    queryKey: ['provider', 'mission', missionId, 'incidents'],
    queryFn: async () => {
      const res = await apiClient.get(`/provider/missions/${missionId}/incidents`);

      return res.data.data ?? [];
    },
    enabled: missionId !== null,
  });
}

/**
 * LES TÂCHES QUI EMPÊCHENT DE CLÔTURER.
 *
 * Le serveur refuse de terminer une mission tant qu'une tâche `is_required` reste ouverte, et ce
 * refus n'avait aucun remède ici : l'écran terrain n'affichait que la checklist du module
 * Inspection — une autre table — et celles-ci n'étaient cochables que depuis le web.
 *
 * `required_pending` est exactement la condition du serveur : l'écran peut donc dire ce qui
 * bloque, au lieu d'opposer un refus sans explication au moment de clôturer.
 */
export interface MissionChecklistItemDto {
  id: number;
  label: string;
  guidance: string | null;
  is_required: boolean;
  requires_photo: boolean;
  status: string;
  done: boolean;
}

export interface MissionChecklistDto {
  id: number;
  name: string | null;
  status: string;
  completion_rate: number;
  items: MissionChecklistItemDto[];
}

export interface MissionChecklistState {
  checklists: MissionChecklistDto[];
  required_pending: number;
  blocks_completion: boolean;
}

export function useMissionChecklist(missionId: number | null) {
  return useQuery<MissionChecklistState>({
    queryKey: ['provider', 'mission', missionId, 'checklist'],
    queryFn: async () =>
      (await apiClient.get(`/provider/missions/${missionId}/checklist`)).data.data,
    enabled: missionId !== null,
  });
}

/**
 * Recalcule l'état local d'une checklist après une case cochée.
 *
 * Pure et locale : `required_pending` et `blocks_completion` doivent suivre la case, sinon le
 * bouton de clôture resterait bloqué à l'écran alors que tout est fait.
 */
function coche(etat: MissionChecklistState, itemId: number, done: boolean): MissionChecklistState {
  const checklists = etat.checklists.map((liste) => ({
    ...liste,
    items: liste.items.map((item) =>
      item.id === itemId ? { ...item, done, status: done ? 'done' : 'pending' } : item,
    ),
  }));

  const restants = checklists
    .flatMap((liste) => liste.items)
    .filter((item) => item.is_required && !item.done).length;

  return { ...etat, checklists, required_pending: restants, blocks_completion: restants > 0 };
}

/**
 * COCHER UNE TÂCHE — LA SEULE ACTION DU TERRAIN QUI SUPPORTE LE HORS-LIGNE.
 *
 * ── POURQUOI CELLE-CI, ET PAS LA CLÔTURE ─────────────────────────────────────────────────────
 *
 * L'appel envoie une VALEUR ABSOLUE (`done` / `pending`), jamais une bascule. Le rejouer deux
 * fois donne exactement le même résultat que le jouer une fois : c'est ce qui le rend sûr dans
 * une file qui, par construction, peut renvoyer.
 *
 * La clôture, elle, n'entre PAS dans cette file, et ce n'est pas un oubli. Elle consomme un code
 * de fin à usage unique et déclenche l'encaissement : rejouée plus tard, elle échouerait sur un
 * code déjà consommé après avoir laissé croire au prestataire qu'il avait termine. Clôturer
 * demande du réseau, et l'écran le dit au lieu d'échouer sur un message générique.
 *
 * ── L'ÉTAT LOCAL BOUGE TOUT DE SUITE ─────────────────────────────────────────────────────────
 *
 * Hors-ligne il n'y a pas de réponse à écrire dans le cache. Sans mise à jour optimiste, la case
 * se décocherait sous le doigt — et un prestataire dans une cave la recocherait dix fois.
 */
export function useToggleMissionChecklistItem(missionId: number) {
  const qc = useQueryClient();

  return useMutation<MissionChecklistState | null, ApiError, { itemId: number; done: boolean }>({
    mutationFn: async ({ itemId, done }) => {
      const resultat = await offlineAwareMutation(
        `/provider/missions/${missionId}/checklist/${itemId}`,
        'POST',
        { status: done ? 'done' : 'pending' },
        done ? 'Cocher une tâche' : 'Décocher une tâche',
      );

      if (resultat.queued) {
        return null;
      }

      return (resultat.response as { data: MissionChecklistState }).data;
    },
    // Hors-ligne, c'est la SEULE chose qui bouge : la file partira à la reconnexion.
    onMutate: ({ itemId, done }) => {
      qc.setQueryData<MissionChecklistState>(
        ['provider', 'mission', missionId, 'checklist'],
        (etat) => (etat === undefined ? etat : coche(etat, itemId, done)),
      );
    },
    // La réponse porte déjà l'état complet : on l'écrit directement plutôt que de refaire un
    // aller-retour, pour que la case cochée et le compteur de blocage bougent d'un seul coup.
    onSuccess: (etat) => {
      if (etat === null) {
        return;
      }

      qc.setQueryData(['provider', 'mission', missionId, 'checklist'], etat);
      // Le détail de mission porte `checklist_items_pending` : le laisser périmé afficherait
      // deux comptes différents sur deux écrans de la même mission.
      qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId] });
    },
  });
}

export function useMissionTimeline(missionId: number | null) {
  return useQuery<MissionTimeline>({
    queryKey: ['provider', 'mission', missionId, 'timeline'],
    queryFn: async () => (await apiClient.get(`/provider/missions/${missionId}/timeline`)).data,
    enabled: missionId !== null,
  });
}

/**
 * Photographie l'état des lieux et l'envoie.
 *
 * `source` distingue l'appareil photo de la galerie. La galerie reste offerte : un prestataire qui
 * vient de photographier hors de l'application n'a pas à recommencer, et lui fermer cette porte le
 * ferait envoyer ses photos par un autre canal, hors de toute trace.
 */
export function useCaptureMissionMedia(missionId: number) {
  const qc = useQueryClient();

  return useMutation<
    MissionMediaItem | null,
    ApiError,
    { type: MissionMediaType; source?: 'camera' | 'library'; caption?: string }
  >({
    mutationFn: async ({ type, source = 'camera', caption }) => {
      const picked = await pickImage(source);

      // Annulation : ce n'est pas une erreur, et l'appelant ne doit rien afficher.
      if (!picked) {
        return null;
      }

      const position = await readScanPosition();

      const body = new FormData();
      body.append('type', type);
      // La forme { uri, name, type } est celle qu'attend FormData en React Native pour un fichier
      // local ; un Blob ne fonctionne pas ici.
      body.append('photo', { uri: picked.uri, name: picked.name, type: picked.mimeType } as never);

      if (caption) {
        body.append('caption', caption);
      }

      if (position) {
        body.append('lat', String(position.lat));
        body.append('lng', String(position.lng));

        if (position.accuracy_m !== null) {
          body.append('accuracy_m', String(position.accuracy_m));
        }
      }

      const res = await apiClient.post(`/provider/missions/${missionId}/media`, body, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      return res.data.data;
    },
    onSuccess: (media) => {
      if (media === null) {
        return;
      }

      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'media'] });
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'timeline'] });
    },
  });
}

/**
 * Signale un imprévu, photo à l'appui quand il y en a une.
 *
 * La photo est FACULTATIVE : un portail fermé se décrit en une phrase, et exiger un cliché
 * retarderait le seul signalement qui fasse gagner du temps à tout le monde.
 */
export function useReportMissionIncident(missionId: number) {
  const qc = useQueryClient();

  return useMutation<
    MissionIncidentItem,
    ApiError,
    { type: MissionIncidentType; description: string; withPhoto?: boolean }
  >({
    mutationFn: async ({ type, description, withPhoto = false }) => {
      const body = new FormData();
      body.append('type', type);
      body.append('description', description);

      if (withPhoto) {
        const picked = await pickImage('camera');

        if (picked) {
          body.append('photo', {
            uri: picked.uri,
            name: picked.name,
            type: picked.mimeType,
          } as never);
        }
      }

      const position = await readScanPosition();

      if (position) {
        body.append('lat', String(position.lat));
        body.append('lng', String(position.lng));
      }

      const res = await apiClient.post(`/provider/missions/${missionId}/incidents`, body, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      return res.data.data;
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'incidents'] });
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'timeline'] });
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'media'] });
    },
  });
}

/** Les suppléments de cette mission — le prestataire doit savoir ce qui est accepté avant d'agir. */
export function useMissionExtras(missionId: number | null) {
  return useQuery<MissionExtraItem[]>({
    queryKey: ['provider', 'mission', missionId, 'extras'],
    queryFn: async () => {
      const res = await apiClient.get(`/provider/missions/${missionId}/extras`);
      return res.data.data ?? [];
    },
    enabled: missionId !== null,
  });
}

/**
 * PROPOSER UN SUPPLÉMENT CONSTATÉ SUR PLACE.
 *
 * Sans ce geste, le prestataire n'a que deux mauvaises réponses — le faire gratuitement, ou ne pas
 * le faire — et une troisième pire que les deux : s'arranger en espèces, ce qui sort l'argent de la
 * plateforme et le client de toute protection.
 *
 * LE PRIX EST EN CENTIMES jusqu'au serveur. Convertir en euros ici ferait voyager un flottant, et
 * un flottant sur un montant produit des écarts d'un centime que personne ne sait expliquer.
 */
export function useProposeMissionExtra(missionId: number) {
  const qc = useQueryClient();

  return useMutation<
    MissionExtraItem,
    ApiError,
    { label: string; priceCents: number; description?: string }
  >({
    mutationFn: async ({ label, priceCents, description }) => {
      const res = await apiClient.post(`/provider/missions/${missionId}/extras`, {
        label,
        price_cents: priceCents,
        description: description ?? null,
      });

      return res.data.data;
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'extras'] });
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'timeline'] });
    },
  });
}

/** La fiche d'accès de cette mission — verrouillée tant que l'arrivée n'est pas confirmée. */
export function useMissionAccessSheet(missionId: number | null) {
  return useQuery<MissionAccessSheet>({
    queryKey: ['provider', 'mission', missionId, 'access-sheet'],
    queryFn: async () => {
      const res = await apiClient.get(`/provider/missions/${missionId}/access-sheet`);
      return res.data.data;
    },
    enabled: missionId !== null,
  });
}

/* ───────────────────────────────────────────────────────────────────────────────────────────────
 * LE NOUVEAU DEVIS
 *
 * À ne pas confondre avec le SUPPLÉMENT juste au-dessus : celui-ci AJOUTE une ligne à un devis
 * juste, pour un imprévu découvert EN TRAVAILLANT. Celui-là dit que le devis était faux DÈS LE
 * DÉPART, et il remplace le prix.
 *
 * La fenêtre se ferme dès que le prestataire a touché à quelque chose — une tâche cochée, une photo
 * « après ». Le serveur en décide et le dit ; l'écran ne calcule rien.
 * ─────────────────────────────────────────────────────────────────────────────────────────────── */

export interface QuoteRevisionWindow {
  open: boolean;
  closes_at: string | null;
  reason: string | null;
  /* La devise de la mission. Le formulaire s'affiche AVANT qu'une revision existe :
     sa devise ne peut donc pas venir d'elle. */
  currency?: string | null;
}

export interface ProviderQuoteRevision {
  id: number;
  status: string;
  original_total_cents: number;
  revised_total_cents: number;
  top_up_cents: number;
  currency: string;
  breakdown: Record<string, unknown> | null;
  reason_code: string;
  reason_text: string;
  awaiting_client: boolean;
  client_decision: string | null;
  window_closes_at: string | null;
  last_error: string | null;
}

export function useQuoteRevision(missionId: number) {
  return useQuery<{ window: QuoteRevisionWindow; revision: ProviderQuoteRevision | null }>({
    queryKey: ['provider', 'mission', missionId, 'quote-revision'],
    queryFn: async () => {
      const res = await apiClient.get(`/provider/missions/${missionId}/quote-revision`);

      return { window: res.data.window, revision: res.data.revision ?? null };
    },
    // Le client répond depuis son téléphone pendant que le prestataire attend devant lui : une
    // minute de retard est une minute d'attente inexpliquée.
    refetchInterval: 20000,
  });
}

/**
 * SIMULER — « si j'annonce ce prix de service, que paiera le client ? »
 *
 * Le prestataire ne saisit JAMAIS le total : le serveur réapplique les remises. Sans cette
 * simulation, il annoncerait de vive voix un chiffre qui ne serait pas celui du téléphone, et le
 * client se sentirait trompé au moment de lire l'écran.
 */
export function useSimulerLaRevision(missionId: number) {
  return useMutation<{ total_cents: number; breakdown: Record<string, unknown> }, ApiError, number>({
    mutationFn: async (serviceCents) => {
      const res = await apiClient.post(`/provider/missions/${missionId}/quote-revision/simulate`, {
        service_cents: serviceCents,
      });

      return res.data.quote;
    },
  });
}

export function useProposerLaRevision(missionId: number) {
  const qc = useQueryClient();

  return useMutation<
    ProviderQuoteRevision,
    ApiError,
    { serviceCents: number; reasonText: string; mediaIds: number[] }
  >({
    mutationFn: async ({ serviceCents, reasonText, mediaIds }) => {
      const res = await apiClient.post(`/provider/missions/${missionId}/quote-revision`, {
        service_cents: serviceCents,
        reason_text: reasonText,
        media_ids: mediaIds,
      });

      return res.data.revision;
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId] });
    },
  });
}

export function useRetirerLaRevision(missionId: number) {
  const qc = useQueryClient();

  return useMutation<ProviderQuoteRevision, ApiError, number>({
    mutationFn: async (revisionId) => {
      const res = await apiClient.delete(
        `/provider/missions/${missionId}/quote-revision/${revisionId}`,
      );

      return res.data.revision;
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId] });
    },
  });
}

/**
 * DEMANDER DU RENFORT — la troisième issue, quand le chantier est plus gros que prévu.
 *
 * Elle vit à côté de la révision de devis, et pas par hasard : ce sont les deux réponses au même
 * constat. Réviser le prix, ou faire venir quelqu'un. Le questionnaire d'annulation renvoie vers
 * l'une ou l'autre plutôt que de laisser abandonner.
 */
export function useDemanderDuRenfort(missionId: number) {
  const qc = useQueryClient();

  return useMutation<
    { id: number; status: string; required_people: number },
    ApiError,
    { reason: string; people?: number }
  >({
    mutationFn: async ({ reason, people }) => {
      const res = await apiClient.post(`/provider/missions/${missionId}/reinforcement`, {
        reason,
        people: people ?? 1,
      });

      return res.data.reinforcement;
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId] });
    },
  });
}

/*
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 * LE RETARD, VU DU PRESTATAIRE
 * ─────────────────────────────────────────────────────────────────────────────────────────────
 *
 * Il n'a pas besoin qu'on lui apprenne qu'il est en retard — il a une montre. Il a besoin de
 * savoir que le CLIENT le sait, et depuis quand : arriver en s'excusant d'un retard dont l'autre
 * parlait depuis vingt minutes est la façon la plus sûre de commencer mal une intervention.
 */

export interface RetardAnnonce {
  arrivee_at: string | null;
  motif: string | null;
}

export interface EtatDeRetard {
  en_retard: boolean;
  minutes: number | null;
  heure_prevue: string | null;
  annonce: RetardAnnonce | null;
  annulation_gratuite: boolean;
  prevenu_at: string | null;
}

export function useMonRetard(missionId: number | null) {
  return useQuery<EtatDeRetard | null>({
    queryKey: ['provider', 'mission', missionId, 'delay'],
    queryFn: async () => (await apiClient.get(`/provider/missions/${missionId}/delay`)).data.data,
    enabled: missionId !== null,
    refetchInterval: 60000,
  });
}

/**
 * ANNONCER SON RETARD — la seule action qui évite l'annulation gratuite.
 *
 * On envoie des MINUTES, pas une horloge : sur la route, personne ne calcule « j'arriverai à
 * 14 h 37 ». Le serveur convertit, pour que les deux applications ne le fassent pas chacune à sa
 * façon.
 */
export function useAnnoncerMonRetard(missionId: number) {
  const qc = useQueryClient();

  return useMutation<EtatDeRetard, ApiError, { minutes: number; reason?: string }>({
    mutationFn: async (annonce) =>
      (await apiClient.post(`/provider/missions/${missionId}/delay`, annonce)).data.data,
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId] });
    },
  });
}
