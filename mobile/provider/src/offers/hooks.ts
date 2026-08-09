import { useCallback, useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { useAuth } from '@/auth';
import { useChannel } from '@/realtime';
import type { MissionOffer } from './types';

/**
 * L'OFFRE EN COURS — trois canaux, une seule vérité.
 *
 * Le temps réel ouvre la modale en une fraction de seconde. Le push réveille l'application en
 * arrière-plan. Et ce sondage-ci est le REPLI : quand la socket est tombée et que le push s'est
 * perdu, l'offre est quand même là. Sans lui, un défaut d'infrastructure rendrait la plateforme
 * silencieuse sans que personne ne s'en aperçoive — les prestataires croiraient simplement qu'il
 * n'y a pas de travail.
 *
 * Le rythme est court parce qu'une offre vit vingt secondes : sonder toutes les minutes
 * proposerait des courses déjà escaladées.
 */
export const OFFER_POLL_INTERVAL_MS = 7_000;

export const OFFER_QUERY_KEY = ['provider', 'offers', 'current'] as const;

export function useCurrentOffer(enabled: boolean = true) {
  const { user, isAuthenticated } = useAuth();
  const queryClient = useQueryClient();

  /** L'offre reçue par la socket, avant même que le sondage ne la voie. */
  const [pushed, setPushed] = useState<MissionOffer | null>(null);
  /** Les offres écartées à la main : refuser ne doit pas rouvrir la modale au sondage suivant. */
  const [dismissed, setDismissed] = useState<number[]>([]);

  const polled = useQuery<MissionOffer | null>({
    queryKey: OFFER_QUERY_KEY,
    queryFn: async () => (await apiClient.get('/provider/offers/current')).data.data ?? null,
    enabled: enabled && isAuthenticated,
    refetchInterval: OFFER_POLL_INTERVAL_MS,
    staleTime: 0,
  });

  useChannel(isAuthenticated && user ? `private-user.${user.id}` : null, {
    MissionOfferPushed: (data: unknown) => {
      const offer = (data as { offer?: MissionOffer })?.offer;
      if (offer) {
        setPushed(offer);
      }
    },
    /*
     * L'offre n'est plus à prendre. Fermer la modale ici n'est pas une politesse : après la
     * dernière vague, plusieurs prestataires ont la même course à l'écran, et celui qui n'a pas
     * gagné verrait un compte à rebours tourner sur une course déjà partie.
     */
    MissionOfferWithdrawn: (data: unknown) => {
      const id = (data as { assignment_id?: number })?.assignment_id;
      if (id == null) return;
      setPushed((current) => (current && current.assignment_id === id ? null : current));
      setDismissed((ids) => (ids.includes(id) ? ids : [...ids, id]));
      void queryClient.invalidateQueries({ queryKey: OFFER_QUERY_KEY });
    },
  });

  const offer = useMemo(() => {
    const candidate = pushed ?? polled.data ?? null;

    if (!candidate || dismissed.includes(candidate.assignment_id)) {
      return null;
    }

    // Une offre dont l'échéance SERVEUR est passée n'est plus une offre : le serveur l'a déjà
    // escaladée, l'afficher ferait cliquer sur une erreur.
    if (candidate.expires_at && new Date(candidate.expires_at).getTime() <= Date.now()) {
      return null;
    }

    return candidate;
  }, [pushed, polled.data, dismissed]);

  const dismiss = useCallback((assignmentId: number) => {
    setPushed((current) => (current && current.assignment_id === assignmentId ? null : current));
    setDismissed((ids) => (ids.includes(assignmentId) ? ids : [...ids, assignmentId]));
  }, []);

  // La liste des écartées ne grandit pas indéfiniment : au-delà, les plus anciennes ne peuvent
  // plus revenir de toute façon (leur échéance est passée depuis longtemps).
  useEffect(() => {
    if (dismissed.length > 40) {
      setDismissed((ids) => ids.slice(-20));
    }
  }, [dismissed.length]);

  return { offer, dismiss, isLoading: polled.isLoading };
}

export function useAcceptOffer() {
  const queryClient = useQueryClient();

  return useMutation<{ mission_id: number | null }, ApiError, number>({
    mutationFn: async (assignmentId) => {
      const res = await apiClient.post(`/provider/assignments/${assignmentId}/accept`);
      return { mission_id: res.data?.mission_id ?? null };
    },
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: OFFER_QUERY_KEY });
      void queryClient.invalidateQueries({ queryKey: ['provider', 'assignments'] });
      void queryClient.invalidateQueries({ queryKey: ['provider', 'missions'] });
    },
  });
}

export function useDeclineOffer() {
  const queryClient = useQueryClient();

  return useMutation<void, ApiError, number>({
    mutationFn: async (assignmentId) => {
      await apiClient.post(`/provider/assignments/${assignmentId}/decline`);
    },
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: OFFER_QUERY_KEY });
      void queryClient.invalidateQueries({ queryKey: ['provider', 'assignments'] });
    },
  });
}

/**
 * Les secondes qui restent, calculées sur l'horloge SERVEUR.
 *
 * `expires_at` est la seule référence. Un compte à rebours parti d'un nombre de secondes reçu à
 * l'émission dérive de tout ce que le réseau a mis à livrer le message — et la modale afficherait
 * « 6 s » sur une offre que le serveur a déjà escaladée.
 */
export function useServerCountdown(expiresAt: string | null | undefined): number {
  const deadline = useMemo(() => (expiresAt ? new Date(expiresAt).getTime() : null), [expiresAt]);
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (!deadline) return undefined;
    const id = setInterval(() => setNow(Date.now()), 250);
    return () => clearInterval(id);
  }, [deadline]);

  if (!deadline) return 0;

  return Math.max(0, Math.ceil((deadline - now) / 1000));
}
