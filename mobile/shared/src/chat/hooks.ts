import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { useChannel } from '@/realtime';
import { useCallback } from 'react';
import type { ChatThread, ChatMessage, ChatMessageBroadcast } from './types';

/**
 * CE QUE LE SERVEUR ENVOIE VRAIMENT POUR UN FIL — et qui n'est pas `ChatThread`.
 *
 * `listMyThreads` rend les modèles Eloquent bruts : `last_message_preview`, `message_count`,
 * `title`… mais NI `participants` (la relation n'est pas chargée), NI `unread_count`, NI
 * `last_message`. L'écran, lui, lisait `item.participants[0]` sans garde : la messagerie tombait
 * sur son écran d'erreur au premier fil de la liste — « Cannot convert undefined value to object ».
 *
 * Cette traduction est le FILET, pas la correction : le serveur doit renvoyer les participants,
 * c'est lui qui sait qui parle à qui. Mais un champ absent ne doit plus jamais faire tomber
 * l'écran — c'est la troisième fois dans ce dépôt qu'un contrat non tenu se paie d'une page
 * blanche.
 */
type FilDeLApi = Partial<ChatThread> & {
  last_message_preview?: string | null;
  participants?: ChatThread['participants'] | null;
};

function versFil(brut: FilDeLApi): ChatThread {
  return {
    id: brut.id as number,
    booking_id: brut.booking_id,
    last_message: brut.last_message ?? brut.last_message_preview ?? undefined,
    last_message_at: brut.last_message_at,
    unread_count: brut.unread_count ?? 0,
    participants: Array.isArray(brut.participants) ? brut.participants : [],
    title: brut.title,
  };
}

export function useChatThreads() {
  return useQuery<ChatThread[]>({
    queryKey: ['chat', 'threads'],
    queryFn: async () => {
      const res = await apiClient.get('/v2/chat/threads');
      const brut = res.data?.data ?? res.data ?? [];

      return (Array.isArray(brut) ? brut : []).map(versFil);
    },
    /*
     * LE FILET, comme sur le suivi d'intervention.
     *
     * La messagerie ne vivait QUE de la socket. Un ascenseur, un tunnel, une application mise en
     * veille par le systeme : la socket tombe, plus rien n'arrive, et rien ne le rattrape tant que
     * l'utilisateur ne quitte pas l'ecran pour y revenir. Il voit une conversation figee et croit
     * que personne ne repond.
     *
     * Une minute sur la LISTE : elle sert a voir qu'un fil a bouge, pas a suivre un echange.
     * `focusManager` est branche sur `AppState` (voir `api/appFocus.ts`), donc ce sondage
     * s'interrompt de lui-meme quand l'application passe en arriere-plan -- il ne coute rien a la
     * batterie de quelqu'un qui ne regarde pas.
     */
    refetchInterval: 60000,
  });
}

export function useChatMessages(threadId: number | null) {
  return useQuery<ChatMessage[]>({
    queryKey: ['chat', 'messages', threadId],
    queryFn: async () => {
      const res = await apiClient.get(`/v2/chat/threads/${threadId}/messages`);
      return res.data.data ?? res.data;
    },
    enabled: threadId !== null,
    /*
     * PLUS SERRE QUE LA LISTE, et c'est voulu : ici quelqu'un ATTEND une reponse, l'ecran ouvert.
     * Quinze secondes de silence sur une conversation en cours se remarquent ; sur une liste de
     * fils, non.
     *
     * Ce sondage ne remplace pas la socket, qui reste instantanee. Il rattrape ce qu'elle perd.
     */
    refetchInterval: 15000,
  });
}

export function useSendMessage(threadId: number) {
  const queryClient = useQueryClient();
  return useMutation<ChatMessage, ApiError, { body: string }>({
    mutationFn: async ({ body }) => {
      const res = await apiClient.post(`/v2/chat/threads/${threadId}/messages`, { body });
      return res.data.data ?? res.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['chat', 'messages', threadId] }),
  });
}

export function useMarkThreadRead(threadId: number) {
  return useMutation({
    mutationFn: () => apiClient.post(`/v2/chat/threads/${threadId}/read`),
  });
}

/**
 * LE CHAT EN DIRECT — deux erreurs le rendaient muet, et une troisième le faisait mentir.
 *
 * Le canal écouté était `private-channel.{threadId}`, qui désigne la messagerie interne des sociétés
 * — un tout autre objet, dont l'identifiant n'a aucun rapport. Et l'événement attendu était
 * `ChatMessageSentEvent`, le nom de la CLASSE, alors que `broadcastAs()` publie `chat.message`.
 * Deux fautes indépendantes : corriger l'une seule aurait laissé le chat muet, ce qui explique
 * qu'aucune des deux n'ait été trouvée.
 *
 * Le préfixe `private-` est celui de Pusher, pas de Laravel : `PrivateChannel('chat.thread.4')` se
 * souscrit sous `private-chat.thread.4`. C'est la convention déjà suivie par le suivi de mission et
 * par les offres.
 */
export function useLiveChat(
  threadId: number | null,
  onMessage: (message: ChatMessageBroadcast) => void,
) {
  useChannel(threadId ? `private-chat.thread.${threadId}` : null, {
    'chat.message': (data: unknown) => onMessage(data as ChatMessageBroadcast),
  });
}
