import React, { createContext, useContext, useEffect, useRef, useState } from 'react';
import { useAuth } from '@/auth';
import { useSocketConfig } from './useSocketConfig';
import { secureStore } from '@/storage/secureStore';
import { env } from '@/config/env';

const RealtimeContext = createContext<any>(null);
export function useRealtime() { return useContext(RealtimeContext); }

export function RealtimeProvider({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuth();
  const { data: config } = useSocketConfig(isAuthenticated);
  const [pusher, setPusher] = useState<any>(null);
  const pusherRef = useRef<any>(null);

  useEffect(() => {
    if (!config || !isAuthenticated) {
      pusherRef.current?.disconnect();
      pusherRef.current = null;
      setPusher(null);
      return;
    }

    let cancelled = false;

    (async () => {
      try {
        const PusherModule = await import('pusher-js');
        const PusherClass = (PusherModule as any).Pusher ?? (PusherModule as any).default ?? PusherModule;

        if (cancelled) return;

        const p = new PusherClass(config.key, {
          wsHost: config.host,
          wsPort: config.port,
          wssPort: config.port,
          forceTLS: config.scheme === 'https',
          cluster: '',
          enabledTransports: ['ws', 'wss'],
          authorizer: (channel: any) => ({
            authorize: async (socketId: string, callback: any) => {
              try {
                const token = await secureStore.getToken();
                const res = await fetch(`${env.apiUrl}${config.auth_endpoint}`, {
                  method: 'POST',
                  headers: {
                    Authorization: `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                  },
                  body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
                });
                if (!res.ok) throw new Error(`Auth ${res.status}`);
                callback(null, await res.json());
              } catch (e) {
                callback(e as Error, { auth: '' });
              }
            },
          }),
        });

        pusherRef.current = p;
        setPusher(p);
      } catch {
        // Pusher init failed — silently ignore
      }
    })();

    return () => {
      cancelled = true;
      pusherRef.current?.disconnect();
      pusherRef.current = null;
      setPusher(null);
    };
  }, [config, isAuthenticated]);

  return <RealtimeContext.Provider value={pusher}>{children}</RealtimeContext.Provider>;
}
