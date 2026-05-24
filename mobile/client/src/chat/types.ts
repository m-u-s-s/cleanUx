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
