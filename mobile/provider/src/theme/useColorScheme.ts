import { useState, useEffect } from 'react';
import { Appearance } from 'react-native';

export type ThemeMode = 'light' | 'dark' | 'system';
export type ResolvedScheme = 'light' | 'dark' | 'unspecified';

let overrideMode: ThemeMode = 'system';
const listeners = new Set<() => void>();

export function useColorScheme(): {
  colorScheme: ResolvedScheme;
  mode: ThemeMode;
  setMode: (m: ThemeMode) => void;
} {
  const [, forceRender] = useState(0);
  const systemScheme = Appearance.getColorScheme();

  useEffect(() => {
    const cb = () => forceRender(n => n + 1);
    listeners.add(cb);
    return () => {
      listeners.delete(cb);
    };
  }, []);

  const setMode = (m: ThemeMode) => {
    overrideMode = m;
    listeners.forEach(cb => cb());
  };

  const resolved: ResolvedScheme =
    overrideMode === 'system'
      ? (systemScheme === 'dark' ? 'dark' : systemScheme === 'light' ? 'light' : 'unspecified')
      : overrideMode;

  return { colorScheme: resolved, mode: overrideMode, setMode };
}
