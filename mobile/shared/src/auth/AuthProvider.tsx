import React, { createContext, useCallback, useEffect, useState } from 'react';
import { secureStore } from '@/storage/secureStore';
import { apiClient } from '@/api';
import { onSessionExpired } from '@/api/client';
import type { User } from '@/api/types';

interface AuthContextValue {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  setUser: (user: User | null) => void;
  logout: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextValue>({
  user: null, isAuthenticated: false, isLoading: true,
  setUser: () => {}, logout: async () => {},
});

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    (async () => {
      const hasToken = await secureStore.isAuthenticated();
      if (hasToken) {
        try {
          const res = await apiClient.get('/auth/me');
          setUser(res.data.user);
        } catch {
          await secureStore.clearToken();
        }
      }
      setIsLoading(false);
    })();
  }, []);

  // M17 — when the interceptor gives up refreshing an expired session, drop the local user
  // so the UI returns to the logged-out state instead of looping failed requests.
  useEffect(() => onSessionExpired(() => setUser(null)), []);

  const logout = useCallback(async () => {
    try { await apiClient.post('/auth/logout'); } catch {}
    await secureStore.clearToken();
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider value={{ user, isAuthenticated: !!user, isLoading, setUser, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
