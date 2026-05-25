import { useEffect, useRef } from 'react';
import type { Channel } from 'pusher-js';
import { useRealtime } from './RealtimeProvider';

export function useChannel(channelName: string | null, events: Record<string, (data: unknown) => void>) {
  const pusher = useRealtime();
  const channelRef = useRef<Channel | null>(null);

  useEffect(() => {
    if (!pusher || !channelName) return;
    const ch = pusher.subscribe(channelName);
    channelRef.current = ch;
    Object.entries(events).forEach(([event, handler]) => ch.bind(event, handler));
    return () => { Object.keys(events).forEach((e) => ch.unbind(e)); pusher.unsubscribe(channelName); channelRef.current = null; };
  }, [pusher, channelName]);
}
