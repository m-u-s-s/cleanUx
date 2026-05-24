import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/api';
import { useChannel } from '@/realtime';
import { useCallback } from 'react';
import type { ChatThread, ChatMessage } from './types';

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
  return useMutation<ChatMessage, Error, { body: string }>({
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

export function useLiveChat(threadId: number | null, onMessage: (msg: ChatMessage) => void) {
  useChannel(threadId ? `private-channel.${threadId}` : null, {
    'ChatMessageSentEvent': (data: unknown) => onMessage((data as any).message ?? data),
  });
}
