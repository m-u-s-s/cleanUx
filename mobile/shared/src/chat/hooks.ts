import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { useChannel } from '@/realtime';
import { useCallback } from 'react';
import type { ChatThread, ChatMessage, ChatMessageBroadcast } from './types';

export function useChatThreads() {
  return useQuery<ChatThread[]>({
    queryKey: ['chat', 'threads'],
    queryFn: async () => {
      const res = await apiClient.get('/v2/chat/threads');
      return res.data.data ?? res.data;
    },
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
