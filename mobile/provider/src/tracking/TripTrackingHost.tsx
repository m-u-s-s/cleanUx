import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';
import { useGpsWatcher, useSendPing } from './hooks';

interface MissionActive {
  id: number;
  status: string;
  booking_id?: number | null;
}

/**
 * Les états pendant lesquels le client a quelque chose à suivre.
 *
 * `started` en est exclu à dessein : une fois l'intervention commencée, le prestataire est chez le
 * client et sa position n'apprend plus rien — l'écran client remplace d'ailleurs la carte par le
 * code de présence. Relever la position d'un professionnel pendant qu'il travaille serait une
 * collecte sans usage.
 */
const ETATS_SUIVIS = ['en_route', 'arrived'];

/**
 * LE SUIVI EN DIRECT, ÉMIS PAR L'APPLICATION ET NON PAR UN ÉCRAN.
 *
 * Il vivait dans `TrackingScreen`, atteignable par un bouton « Suivi GPS » que le prestataire
 * devait penser à presser, et qu'il devait GARDER OUVERT — alors qu'il conduit. En pratique
 * personne ne le faisait : le parcours normal est « En route » puis « Je suis arrivé », et la
 * session de suivi naissait donc sans une seule position. Le client, à qui l'on promet de voir son
 * prestataire approcher, ne voyait jamais rien — la carte n'avait aucun point à afficher.
 *
 * MONTÉ AVEC LES ONGLETS, comme le battement de présence et la modale d'offre : une pile montée
 * une fois par session terrain et jamais démontée. Le relevé continue donc quel que soit l'écran
 * affiché, et survit au retour en arrière.
 *
 * IL S'ARRÊTE TOUT SEUL. Sans mission `en_route` ni `arrived`, aucun observateur GPS n'est ouvert :
 * la position d'un prestataire qui n'est en route pour personne ne regarde pas la plateforme.
 */
export function TripTrackingHost() {
  const { data: missions } = useQuery<MissionActive[]>({
    queryKey: ['provider', 'missions', 'active'],
    queryFn: async () => {
      const res = await apiClient.get('/provider/missions/active');

      return res.data.data ?? res.data ?? [];
    },
    // Même clé et même intervalle que la liste des missions : deux requêtes distinctes pour la
    // même chose feraient battre l'API deux fois pour un seul besoin.
    refetchInterval: 30000,
  });

  const enRoute = (missions ?? []).find(
    (m) => ETATS_SUIVIS.includes(m.status) && m.booking_id != null,
  );
  const bookingId = enRoute?.booking_id ?? null;

  const sessionId = useSessionForBooking(bookingId);
  const ping = useSendPing(sessionId);

  useGpsWatcher(sessionId !== null, (pos) => {
    if (sessionId === null) {
      return;
    }

    ping.mutate({
      latitude: pos.latitude,
      longitude: pos.longitude,
      speed: pos.speed ?? undefined,
      heading: pos.heading ?? undefined,
    });
  });

  return null;
}

/**
 * La session de suivi de la réservation en cours, ouverte UNE SEULE FOIS.
 *
 * Le serveur réutilise une session existante plutôt que d'en créer une seconde : le geste est donc
 * rejouable sans risque. On mémorise malgré tout la réservation en cours de traitement, pour ne pas
 * relancer un appel à chaque rafraîchissement de la liste — toutes les trente secondes, sur toute
 * la durée d'un trajet, cela ferait beaucoup d'ouvertures pour une seule route.
 */
function useSessionForBooking(bookingId: number | null): number | null {
  const [session, setSession] = useState<{ bookingId: number; sessionId: number } | null>(null);
  const enCours = useRef<number | null>(null);

  const start = useMutation({
    mutationFn: async (id: number) =>
      (await apiClient.post(`/provider/bookings/${id}/tracking/start`)).data?.data,
  });

  useEffect(() => {
    if (bookingId === null) {
      // Plus de mission en route : on relâche la session, ce qui ferme l'observateur GPS.
      setSession(null);

      return;
    }

    if (session?.bookingId === bookingId || enCours.current === bookingId) {
      return;
    }

    enCours.current = bookingId;

    start.mutate(bookingId, {
      onSuccess: (ouverte) => {
        enCours.current = null;

        if (ouverte?.id) {
          setSession({ bookingId, sessionId: ouverte.id });
        }
      },
      onError: () => {
        // Un échec ne fige pas la tentative : la boucle suivante réessaiera, et le prestataire n'a
        // aucun geste à faire pour cela.
        enCours.current = null;
      },
    });
  }, [bookingId, session?.bookingId]);

  return session?.bookingId === bookingId ? session.sessionId : null;
}
