export interface ChatThread {
  id: number;
  booking_id?: number;
  last_message?: string;
  last_message_at?: string;
  unread_count: number;
  participants: Array<{ id: number; name: string; role: string }>;
}

export interface ChatMessage {
  id: number;
  thread_id: number;
  sender_id: number;
  sender_name: string;
  body: string;
  attachments?: Array<{ url: string; mime_type: string }>;
  created_at: string;
}

/**
 * La charge diffusée sur `chat.thread.{id}`, telle que `ChatMessageSentEvent::broadcastWith()`
 * l'écrit. Elle NE correspond PAS à `ChatMessage` : la clé est `message_id`, pas `id`, et le nom de
 * l'expéditeur n'y figure pas. Le hook promettait pourtant un `ChatMessage` — un mensonge que
 * personne n'avait vu parce que les deux appelants ignorent la charge et se contentent de recharger.
 */
export interface ChatMessageBroadcast {
  message_id: number;
  thread_id: number;
  sender_user_id: number;
  sender_role: string;
  body: string;
  has_attachment: boolean;
  attachment_mime: string | null;
  moderation_status: string;
  created_at: string | null;
}
