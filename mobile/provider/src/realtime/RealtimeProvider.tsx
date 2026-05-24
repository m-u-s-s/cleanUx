import React, { createContext, useContext, useEffect, useState } from 'react';
import Pusher from 'pusher-js/react-native';
import { useAuth } from '@/auth';
import { useSocketConfig } from './useSocketConfig';
import { secureStore } from '@/storage/secureStore';

const RealtimeContext = createContext<Pusher | null>(null);
export function useRealtime() { return useContext(RealtimeContext); }

export function RealtimeProvider({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuth();
  const { data: config } = useSocketConfig(isAuthenticated);
  const [pusher, setPusher] = useState<Pusher | null>(null);

  useEffect(() => {
    if (!config || !isAuthenticated) { setPusher(null); return; }

    const apiBase = process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000/api';

    const p = new Pusher(config.key, {
      wsHost: config.host, wsPort: config.port, wssPort: config.port,
      forceTLS: config.scheme === 'https', cluster: '',
      enabledTransports: ['ws', 'wss'],
      authorizer: (channel) => ({
        authorize: async (socketId, callback) => {
          try {
            const token = await secureStore.getToken();
            const res = await fetch(`${apiBase}${config.auth_endpoint}`, {
              method: 'POST',
              headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' },
              body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
            });
            if (!res.ok) throw new Error(`Auth ${res.status}`);
            callback(null, await res.json());
          } catch (e) { callback(e as Error, { auth: '' }); }
        },
      }),
    });
    setPusher(p);

    return () => { p.disconnect(); setPusher(null); };
  }, [config, isAuthenticated]);

  return <RealtimeContext.Provider value={pusher}>{children}</RealtimeContext.Provider>;
}
