import axios from 'axios';
import { secureStore } from '@/storage/secureStore';
import { ApiError } from './types';
import { getAppAudience, APP_AUDIENCE_HEADER } from './appAudience';
import { env } from '@/config/env';

const BASE_URL = env.apiUrl;

// M17 — let the app react to an irrecoverable session loss. The 401-refresh interceptor
// clears the token but cannot touch React state; AuthProvider subscribes here to reset the
// user (so the UI doesn't stay "logged in" while every request 401s until app restart).
type SessionListener = () => void;
const sessionExpiredListeners = new Set<SessionListener>();

export function onSessionExpired(listener: SessionListener): () => void {
  sessionExpiredListeners.add(listener);

  return () => {
    sessionExpiredListeners.delete(listener);
  };
}

function emitSessionExpired(): void {
  sessionExpiredListeners.forEach((listener) => {
    try {
      listener();
    } catch {
      // a listener throwing must not break the interceptor chain
    }
  });
}

export const apiClient = axios.create({
  baseURL: BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

// Request interceptor: attach Bearer token if present
apiClient.interceptors.request.use(async (config) => {
  const token = await secureStore.getToken();
  if (token) {
    config.headers = config.headers ?? {};
    config.headers['Authorization'] = `Bearer ${token}`;
  }

  /*
   * L'application se déclare sur CHAQUE requête, pas seulement à la connexion.
   *
   * Le serveur refuse un compte prestataire dans l'application cliente et l'inverse ; il doit
   * pouvoir le faire aussi à la reprise de session, sinon un jeton obtenu dans l'autre APK reste
   * valide indéfiniment — bloquer la porte d'entrée ne sert à rien si la fenêtre reste ouverte.
   */
  const audience = getAppAudience();
  if (audience) {
    config.headers = config.headers ?? {};
    config.headers[APP_AUDIENCE_HEADER] = audience;
  }

  return config;
});

// Extend AxiosRequestConfig to carry _retry flag
declare module 'axios' {
  interface InternalAxiosRequestConfig {
    _retry?: boolean;
  }
}

// Response interceptor: error mapping + 401 refresh retry
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalConfig = error.config as import('axios').InternalAxiosRequestConfig & {
      _retry?: boolean;
    };

    // Map structured API errors
    const responseData = error.response?.data as
      | { ok: false; error_code?: string; message?: string; errors?: Record<string, string[]> }
      | undefined;

    const status: number = error.response?.status ?? 0;
    const errorCode: string = responseData?.error_code ?? 'unknown_error';
    const message: string = responseData?.message ?? error.message ?? 'An error occurred.';
    const errors = responseData?.errors;

    /*
     * Un 401 sur une route d'AUTHENTIFICATION ne dit pas « jeton expire » : il dit
     * « identifiants incorrects ». Le rafraichir n'a aucun sens — il n'y a pas encore de
     * session — et le flux effacait le jeton puis annoncait une session perdue, la ou l'ecran
     * devait simplement dire que le mot de passe etait faux.
     */
    const routeDAuthentification = /\/auth\/(login|register|refresh|phone\/)/.test(
      String(originalConfig?.url ?? ''),
    );

    // 401 refresh flow (only if not already retried and not a grace-expired token)
    if (
      status === 401 &&
      ! routeDAuthentification &&
      !originalConfig._retry &&
      errorCode !== 'token_grace_expired'
    ) {
      originalConfig._retry = true;

      try {
        const currentToken = await secureStore.getToken();
        const refreshResponse = await axios.post<{ token: string }>(
          `${BASE_URL}/auth/refresh`,
          { token: currentToken },
        );
        const newToken = refreshResponse.data.token;
        await secureStore.setToken(newToken);

        // Patch Authorization header for retry
        originalConfig.headers = originalConfig.headers ?? {};
        originalConfig.headers['Authorization'] = `Bearer ${newToken}`;

        return apiClient(originalConfig);
      } catch {
        await secureStore.clearToken();
        emitSessionExpired();
        throw new ApiError(401, 'session_expired', 'Your session has expired. Please log in again.');
      }
    }

    /*
     * Un compte desactive garde un jeton valide : sans cela, chaque ecran collectionnerait un
     * refus sans jamais rendre la main. L'ecran de connexion redira pourquoi.
     */
    if (status === 403 && errorCode === 'compte_inactif') {
      await secureStore.clearToken();
      emitSessionExpired();
    }

    throw new ApiError(status, errorCode, message, errors, responseData as Record<string, unknown> | undefined);
  },
);
