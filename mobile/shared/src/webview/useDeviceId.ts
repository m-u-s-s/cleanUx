import { useEffect, useState } from 'react';

/**
 * Stable per-install identifier used to bind WebView handoff tickets. The id is
 * only used for audit binding, not security enforcement, so a coarse value is fine.
 */
export function useDeviceId(): string {
  const [id, setId] = useState('mobile');
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        // Prefer expo-application if available; fall back to the constant.
        const Application = await import('expo-application');
        const value =
          (typeof Application.getAndroidId === 'function' ? Application.getAndroidId() : null) ??
          Application.applicationId ??
          'mobile';
        if (!cancelled && typeof value === 'string') setId(value);
      } catch {
        // expo-application not installed — keep the 'mobile' fallback.
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);
  return id;
}
