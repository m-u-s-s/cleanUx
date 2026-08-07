# Brio Mobile RN — Sprint 2 : Auth + API Client + Reverb WS

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Connecter l'app RN au backend Laravel : login/register/logout/refresh fonctionnels, token stocké en SecureStore, intercepteurs axios (auth header, refresh-on-401), TanStack Query configuré, Reverb WS connecté avec auth Bearer, OTP phone verification.

**Architecture:** Module `@/api/` contient le client axios + intercepteurs. Module `@/auth/` contient le contexte React + hooks TanStack Query. Module `@/realtime/` contient le client Pusher/Reverb. Navigation conditionnelle : LoginScreen quand !authenticated, MainTabs quand authenticated.

**Tech Stack:** Axios, @tanstack/react-query, pusher-js/react-native, expo-secure-store (wrapper Sprint 1).

**Backend API shape (Sprint 0+existing):**
- `POST /api/auth/login` → `{ok, token, user}` body: `{email, password, device_name?}`
- `POST /api/auth/register` → `{ok, token, user}` body: `{name, email, password, password_confirmation, phone?, locale?, accept_terms, device_name?}`
- `POST /api/auth/logout` → revokes token
- `POST /api/auth/logout-all` → revokes all
- `GET /api/auth/me` → `{user}`
- `POST /api/auth/refresh` → `{token, expires_at}`
- `GET /api/realtime/socket-config` → `{driver, key, host, port, scheme, auth_endpoint}`
- `POST /api/broadcasting/auth` → `{auth}` (Pusher protocol)
- `POST /api/phone/verify-request` + `POST /api/phone/verify-confirm` (throttled)
- Errors : `{ok: false, error_code, message, errors?}`

---

## File Structure

```
mobile/client/src/
├── api/
│   ├── client.ts              # axios instance + interceptors
│   ├── types.ts               # ApiError, ApiResponse<T>, User types
│   └── index.ts
├── auth/
│   ├── AuthProvider.tsx        # React context (user, isAuthenticated, login/logout/refresh)
│   ├── useAuth.ts              # context consumer hook
│   ├── useLogin.ts             # TanStack useMutation
│   ├── useRegister.ts          # TanStack useMutation
│   ├── useMe.ts                # TanStack useQuery
│   └── index.ts
├── realtime/
│   ├── RealtimeProvider.tsx    # Pusher client init + context
│   ├── useChannel.ts           # subscribe to a private channel
│   ├── useSocketConfig.ts      # fetch /api/realtime/socket-config
│   └── index.ts
├── phone/
│   └── usePhoneVerification.ts # OTP request + confirm
mobile/client/__tests__/
├── api/
│   └── client.test.ts          # interceptor tests
├── auth/
│   ├── useLogin.test.tsx
│   └── AuthProvider.test.tsx
└── realtime/
    └── useSocketConfig.test.ts
```

---

## Task 1 — API client + interceptors + types

**Files:** `src/api/client.ts`, `src/api/types.ts`, `src/api/index.ts`, `__tests__/api/client.test.ts`

- [ ] **Step 1.1** — Write test for axios interceptors

```typescript
// __tests__/api/client.test.ts
import { apiClient, ApiError } from '@/api';
import { secureStore } from '@/storage/secureStore';
import MockAdapter from 'axios-mock-adapter';

jest.mock('@/storage/secureStore');
const mockStore = secureStore as jest.Mocked<typeof secureStore>;

describe('apiClient', () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(apiClient);
    jest.clearAllMocks();
  });

  afterEach(() => mock.restore());

  it('attaches Bearer token from secureStore', async () => {
    mockStore.getToken.mockResolvedValue('test-token');
    mock.onGet('/auth/me').reply(200, { user: { id: 1 } });

    await apiClient.get('/auth/me');

    expect(mock.history.get[0].headers?.Authorization).toBe('Bearer test-token');
  });

  it('skips Authorization when no token', async () => {
    mockStore.getToken.mockResolvedValue(null);
    mock.onGet('/auth/me').reply(200, {});

    await apiClient.get('/auth/me');

    expect(mock.history.get[0].headers?.Authorization).toBeUndefined();
  });

  it('wraps API errors into ApiError with error_code', async () => {
    mockStore.getToken.mockResolvedValue(null);
    mock.onPost('/auth/login').reply(422, {
      ok: false, error_code: 'validation_failed', message: 'Bad input', errors: { email: ['Required'] },
    });

    try {
      await apiClient.post('/auth/login', {});
      fail('should throw');
    } catch (e) {
      const err = e as ApiError;
      expect(err.errorCode).toBe('validation_failed');
      expect(err.status).toBe(422);
      expect(err.errors?.email).toContain('Required');
    }
  });
});
```

- [ ] **Step 1.2** — Run → FAIL (no modules)

- [ ] **Step 1.3** — Install axios + axios-mock-adapter

```bash
cd mobile/client && npm install axios && npm install -D axios-mock-adapter
```

- [ ] **Step 1.4** — Implement types

```typescript
// src/api/types.ts
export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: string;
  locale: string;
  email_verified_at: string | null;
  created_at: string;
}

export interface ApiResponse<T> {
  ok: boolean;
  data?: T;
  token?: string;
  user?: User;
}

export class ApiError extends Error {
  constructor(
    public status: number,
    public errorCode: string,
    message: string,
    public errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}
```

- [ ] **Step 1.5** — Implement client with interceptors

```typescript
// src/api/client.ts
import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';
import { secureStore } from '@/storage/secureStore';
import { ApiError } from './types';

const BASE_URL = process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000/api';

export const apiClient = axios.create({
  baseURL: BASE_URL,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
  timeout: 15000,
});

apiClient.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
  const token = await secureStore.getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ ok: boolean; error_code: string; message: string; errors?: Record<string, string[]> }>) => {
    const data = error.response?.data;
    if (data && data.error_code) {
      throw new ApiError(error.response?.status ?? 500, data.error_code, data.message, data.errors);
    }
    throw new ApiError(error.response?.status ?? 0, 'network_error', error.message);
  },
);
```

```typescript
// src/api/index.ts
export { apiClient } from './client';
export { ApiError } from './types';
export type { User, ApiResponse } from './types';
```

- [ ] **Step 1.6** — Run → PASS

- [ ] **Step 1.7** — Commit

```
feat(mobile): API client with auth interceptor + error mapping
```

---

## Task 2 — Auth refresh interceptor

**Files:** Modify `src/api/client.ts`, `__tests__/api/client.test.ts`

- [ ] **Step 2.1** — Add refresh test

```typescript
it('retries request after 401 by refreshing token', async () => {
  mockStore.getToken.mockResolvedValue('old-token');
  mock.onGet('/auth/me').replyOnce(401, { ok: false, error_code: 'unauthenticated', message: 'Auth required' });
  mock.onPost('/auth/refresh').replyOnce(200, { token: 'new-token', expires_at: null });
  mock.onGet('/auth/me').replyOnce(200, { user: { id: 1 } });

  const res = await apiClient.get('/auth/me');

  expect(res.data.user.id).toBe(1);
  expect(mockStore.setToken).toHaveBeenCalledWith('new-token');
});

it('logs out on refresh failure', async () => {
  mockStore.getToken.mockResolvedValue('old-token');
  mock.onGet('/auth/me').reply(401, { ok: false, error_code: 'unauthenticated', message: 'Auth' });
  mock.onPost('/auth/refresh').reply(401, { ok: false, error_code: 'unauthenticated', message: 'Invalid' });

  await expect(apiClient.get('/auth/me')).rejects.toThrow();
  expect(mockStore.clearToken).toHaveBeenCalled();
});
```

- [ ] **Step 2.2** — Run → FAIL (no retry logic)

- [ ] **Step 2.3** — Add refresh interceptor to `client.ts`

Add a 401 interceptor that:
1. Tries `POST /auth/refresh` with old token
2. If success: store new token via `secureStore.setToken()`, retry original request with new token
3. If fail: `secureStore.clearToken()` and throw
4. Prevent infinite loops: flag `_retry` on request config

```typescript
// Add after the existing response interceptor, or replace it with a combined one
let isRefreshing = false;
let refreshPromise: Promise<string> | null = null;

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean };
    const data = error.response?.data as any;

    if (error.response?.status === 401 && !originalRequest._retry && data?.error_code !== 'token_grace_expired') {
      originalRequest._retry = true;

      if (!isRefreshing) {
        isRefreshing = true;
        refreshPromise = (async () => {
          try {
            const res = await axios.post(`${BASE_URL}/auth/refresh`, null, {
              headers: { Authorization: `Bearer ${await secureStore.getToken()}` },
            });
            const newToken = res.data.token;
            await secureStore.setToken(newToken);
            return newToken;
          } catch {
            await secureStore.clearToken();
            throw new ApiError(401, 'session_expired', 'Session expired. Please login again.');
          } finally {
            isRefreshing = false;
            refreshPromise = null;
          }
        })();
      }

      const newToken = await refreshPromise!;
      originalRequest.headers.Authorization = `Bearer ${newToken}`;
      return apiClient(originalRequest);
    }

    if (data?.error_code) {
      throw new ApiError(error.response?.status ?? 500, data.error_code, data.message, data.errors);
    }
    throw new ApiError(error.response?.status ?? 0, 'network_error', error.message);
  },
);
```

- [ ] **Step 2.4** — Run → PASS (5 tests total)

- [ ] **Step 2.5** — Commit

```
feat(mobile): token refresh interceptor (retry on 401)
```

---

## Task 3 — TanStack Query + Auth hooks + AuthProvider

**Files:** `src/auth/AuthProvider.tsx`, `src/auth/useAuth.ts`, `src/auth/useLogin.ts`, `src/auth/useRegister.ts`, `src/auth/useMe.ts`, `src/auth/index.ts`, `__tests__/auth/useLogin.test.tsx`, `__tests__/auth/AuthProvider.test.tsx`, modify `App.tsx`

- [ ] **Step 3.1** — Install TanStack Query

```bash
cd mobile/client && npm install @tanstack/react-query
```

- [ ] **Step 3.2** — Write auth hook tests

```typescript
// __tests__/auth/useLogin.test.tsx
import React from 'react';
import { renderHook, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useLogin } from '@/auth/useLogin';
import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';

const wrapper = ({ children }: { children: React.ReactNode }) => (
  <QueryClientProvider client={new QueryClient({ defaultOptions: { mutations: { retry: false } } })}>
    {children}
  </QueryClientProvider>
);

describe('useLogin', () => {
  let mock: MockAdapter;
  beforeEach(() => { mock = new MockAdapter(apiClient); jest.clearAllMocks(); });
  afterEach(() => mock.restore());

  it('returns token and user on success', async () => {
    mock.onPost('/auth/login').reply(200, {
      ok: true, token: 'tok_123', user: { id: 1, name: 'Test', email: 'a@b.c' },
    });

    const { result } = renderHook(() => useLogin(), { wrapper });
    result.current.mutate({ email: 'a@b.c', password: '12345678' });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.token).toBe('tok_123');
  });
});
```

- [ ] **Step 3.3** — Run → FAIL

- [ ] **Step 3.4** — Implement auth hooks

```typescript
// src/auth/useLogin.ts
import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { secureStore } from '@/storage/secureStore';
import type { User } from '@/api/types';

interface LoginInput { email: string; password: string; deviceName?: string; }
interface LoginResult { token: string; user: User; }

export function useLogin() {
  return useMutation<LoginResult, ApiError, LoginInput>({
    mutationFn: async (input) => {
      const res = await apiClient.post('/auth/login', {
        email: input.email,
        password: input.password,
        device_name: input.deviceName ?? 'brio-mobile',
      });
      await secureStore.setToken(res.data.token);
      return { token: res.data.token, user: res.data.user };
    },
  });
}
```

```typescript
// src/auth/useRegister.ts
import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { secureStore } from '@/storage/secureStore';
import type { User } from '@/api/types';

interface RegisterInput {
  name: string; email: string; password: string; passwordConfirmation: string;
  phone?: string; locale?: string; acceptTerms: boolean; deviceName?: string;
}
interface RegisterResult { token: string; user: User; }

export function useRegister() {
  return useMutation<RegisterResult, ApiError, RegisterInput>({
    mutationFn: async (input) => {
      const res = await apiClient.post('/auth/register', {
        name: input.name, email: input.email, password: input.password,
        password_confirmation: input.passwordConfirmation,
        phone: input.phone, locale: input.locale, accept_terms: input.acceptTerms,
        device_name: input.deviceName ?? 'brio-mobile',
      });
      await secureStore.setToken(res.data.token);
      return { token: res.data.token, user: res.data.user };
    },
  });
}
```

```typescript
// src/auth/useMe.ts
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';
import type { User } from '@/api/types';

export function useMe(enabled: boolean = true) {
  return useQuery<User>({
    queryKey: ['auth', 'me'],
    queryFn: async () => {
      const res = await apiClient.get('/auth/me');
      return res.data.user;
    },
    enabled,
    staleTime: 5 * 60 * 1000,
  });
}
```

- [ ] **Step 3.5** — Implement AuthProvider

```tsx
// src/auth/AuthProvider.tsx
import React, { createContext, useCallback, useEffect, useState } from 'react';
import { secureStore } from '@/storage/secureStore';
import { apiClient } from '@/api';
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
```

```typescript
// src/auth/useAuth.ts
import { useContext } from 'react';
import { AuthContext } from './AuthProvider';

export function useAuth() {
  return useContext(AuthContext);
}
```

```typescript
// src/auth/index.ts
export { AuthProvider } from './AuthProvider';
export { useAuth } from './useAuth';
export { useLogin } from './useLogin';
export { useRegister } from './useRegister';
export { useMe } from './useMe';
```

- [ ] **Step 3.6** — Wire into App.tsx (add QueryClientProvider + AuthProvider)

```tsx
// App.tsx
import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from '@/auth';
import { RootNavigator, linking } from '@/navigation';
import './src/sentry/init';

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 2, staleTime: 60_000 } },
});

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <SafeAreaProvider>
          <NavigationContainer linking={linking}>
            <RootNavigator />
          </NavigationContainer>
          <StatusBar style="auto" />
        </SafeAreaProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}
```

- [ ] **Step 3.7** — Make RootNavigator auth-aware

```tsx
// src/navigation/RootNavigator.tsx — replace
import React from 'react';
import { View, ActivityIndicator } from 'react-native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuth } from '@/auth';
import { LoginScreen } from '@/screens/LoginScreen';
import { TabNavigator } from './TabNavigator';
import { colors } from '@/theme';
import type { RootStackParamList } from './types';

const Stack = createNativeStackNavigator<RootStackParamList>();

export function RootNavigator() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <View testID="root-navigator" style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.surface[50] }}>
        <ActivityIndicator size="large" color={colors.brand[500]} />
      </View>
    );
  }

  return (
    <View testID="root-navigator" style={{ flex: 1 }}>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {isAuthenticated ? (
          <Stack.Screen name="MainTabs" component={TabNavigator} />
        ) : (
          <Stack.Screen name="Login" component={LoginScreen} options={{ animationTypeForReplace: 'pop' }} />
        )}
      </Stack.Navigator>
    </View>
  );
}
```

- [ ] **Step 3.8** — Run all tests

- [ ] **Step 3.9** — Commit

```
feat(mobile): auth system (TanStack Query + AuthProvider + navigation guard)
```

---

## Task 4 — Reverb/Pusher realtime client

**Files:** `src/realtime/useSocketConfig.ts`, `src/realtime/RealtimeProvider.tsx`, `src/realtime/useChannel.ts`, `src/realtime/index.ts`, `__tests__/realtime/useSocketConfig.test.ts`

- [ ] **Step 4.1** — Install pusher-js

```bash
cd mobile/client && npm install pusher-js
```

- [ ] **Step 4.2** — Write test

```typescript
// __tests__/realtime/useSocketConfig.test.ts
import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';

jest.mock('@/storage/secureStore');

describe('socket-config endpoint', () => {
  let mock: MockAdapter;
  beforeEach(() => { mock = new MockAdapter(apiClient); });
  afterEach(() => mock.restore());

  it('parses socket config response', async () => {
    mock.onGet('/realtime/socket-config').reply(200, {
      driver: 'reverb', key: 'pk_test', host: 'ws.test', port: 443, scheme: 'https',
      auth_endpoint: '/api/broadcasting/auth',
    });

    const res = await apiClient.get('/realtime/socket-config');
    expect(res.data.key).toBe('pk_test');
    expect(res.data.port).toBe(443);
  });
});
```

- [ ] **Step 4.3** — Implement

```typescript
// src/realtime/useSocketConfig.ts
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';

export interface SocketConfig {
  driver: string; key: string; host: string; port: number; scheme: string; auth_endpoint: string;
}

export function useSocketConfig(enabled: boolean = true) {
  return useQuery<SocketConfig>({
    queryKey: ['realtime', 'socket-config'],
    queryFn: async () => (await apiClient.get('/realtime/socket-config')).data,
    enabled,
    staleTime: 30 * 60 * 1000,
  });
}
```

```tsx
// src/realtime/RealtimeProvider.tsx
import React, { createContext, useContext, useEffect, useRef } from 'react';
import Pusher from 'pusher-js/react-native';
import { useAuth } from '@/auth';
import { useSocketConfig } from './useSocketConfig';
import { secureStore } from '@/storage/secureStore';

const RealtimeContext = createContext<Pusher | null>(null);

export function useRealtime() { return useContext(RealtimeContext); }

export function RealtimeProvider({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuth();
  const { data: config } = useSocketConfig(isAuthenticated);
  const pusherRef = useRef<Pusher | null>(null);

  useEffect(() => {
    if (!config || !isAuthenticated) return;

    const apiBase = process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000/api';

    pusherRef.current = new Pusher(config.key, {
      wsHost: config.host,
      wsPort: config.port,
      wssPort: config.port,
      forceTLS: config.scheme === 'https',
      cluster: '',
      enabledTransports: ['ws', 'wss'],
      authorizer: (channel) => ({
        authorize: async (socketId, callback) => {
          try {
            const token = await secureStore.getToken();
            const res = await fetch(`${apiBase}${config.auth_endpoint}`, {
              method: 'POST',
              headers: {
                Authorization: `Bearer ${token}`,
                'Content-Type': 'application/json',
                Accept: 'application/json',
              },
              body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
            });
            if (!res.ok) throw new Error(`Auth failed: ${res.status}`);
            callback(null, await res.json());
          } catch (e) {
            callback(e as Error, { auth: '' });
          }
        },
      }),
    });

    return () => {
      pusherRef.current?.disconnect();
      pusherRef.current = null;
    };
  }, [config, isAuthenticated]);

  return (
    <RealtimeContext.Provider value={pusherRef.current}>
      {children}
    </RealtimeContext.Provider>
  );
}
```

```typescript
// src/realtime/useChannel.ts
import { useEffect, useRef } from 'react';
import type Pusher from 'pusher-js';
import type { Channel } from 'pusher-js';
import { useRealtime } from './RealtimeProvider';

export function useChannel(channelName: string | null, events: Record<string, (data: any) => void>) {
  const pusher = useRealtime();
  const channelRef = useRef<Channel | null>(null);

  useEffect(() => {
    if (!pusher || !channelName) return;

    const channel = pusher.subscribe(channelName);
    channelRef.current = channel;

    Object.entries(events).forEach(([event, handler]) => {
      channel.bind(event, handler);
    });

    return () => {
      Object.keys(events).forEach((event) => channel.unbind(event));
      pusher.unsubscribe(channelName);
      channelRef.current = null;
    };
  }, [pusher, channelName]);
}
```

```typescript
// src/realtime/index.ts
export { RealtimeProvider, useRealtime } from './RealtimeProvider';
export { useChannel } from './useChannel';
export { useSocketConfig } from './useSocketConfig';
export type { SocketConfig } from './useSocketConfig';
```

- [ ] **Step 4.4** — Wire RealtimeProvider into App.tsx (inside AuthProvider)

- [ ] **Step 4.5** — Run all tests

- [ ] **Step 4.6** — Commit

```
feat(mobile): Reverb/Pusher realtime client with channel hooks
```

---

## Task 5 — Phone OTP verification

**Files:** `src/phone/usePhoneVerification.ts`

- [ ] **Step 5.1** — Implement

```typescript
// src/phone/usePhoneVerification.ts
import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';

export function useRequestOtp() {
  return useMutation<void, ApiError, { phone: string }>({
    mutationFn: async ({ phone }) => {
      await apiClient.post('/phone/verify-request', { phone });
    },
  });
}

export function useConfirmOtp() {
  return useMutation<void, ApiError, { phone: string; code: string }>({
    mutationFn: async ({ phone, code }) => {
      await apiClient.post('/phone/verify-confirm', { phone, code });
    },
  });
}
```

- [ ] **Step 5.2** — Run all tests, type-check

- [ ] **Step 5.3** — Commit

```
feat(mobile): phone OTP verification hooks
```

---

## Final verification

- [ ] All mobile tests pass: `cd mobile/client && npm test`
- [ ] Type-check: `cd mobile/client && npm run typecheck`
- [ ] Sprint 0 Laravel tests unaffected: `php artisan test --filter="AuthRefreshTest|SocketConfigTest"`
- [ ] Metro starts: `cd mobile/client && npx expo start --no-dev --minify` (Ctrl+C after confirming)

## Self-Review

| Requirement | Task | Status |
|---|---|---|
| Login/Register/Logout/Refresh | Tasks 1-3 | ✅ |
| Token stored via secureStore | Task 1 (interceptor) + Task 3 (useLogin) | ✅ |
| Intercepteurs (auth header, refresh-on-401, error mapping) | Tasks 1-2 | ✅ |
| TanStack Query | Task 3 | ✅ |
| Reverb client + Bearer auth | Task 4 | ✅ |
| Navigation guard (Login vs MainTabs) | Task 3.7 | ✅ |
| OTP phone verification | Task 5 | ✅ |

### Placeholder scan
No TBD/TODO. All code blocks are complete. `EXPO_PUBLIC_API_URL` and `EXPO_PUBLIC_SENTRY_DSN` are env vars the user configures — not placeholders.

### Type consistency
- `ApiError` used consistently (api/types.ts → client.ts → useLogin → useRegister → usePhoneVerification)
- `User` type used in auth hooks, AuthProvider, and useMe
- `SocketConfig` interface used in useSocketConfig and RealtimeProvider
- `secureStore.getToken/setToken/clearToken` (Sprint 1 API) used correctly everywhere
