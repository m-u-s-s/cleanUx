import { useState, useEffect } from 'react';
import { Appearance } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { apiClient } from '../api/client';

export type ThemeMode = 'light' | 'dark' | 'system';
export type ResolvedScheme = 'light' | 'dark' | 'unspecified';

const CLE_STOCKAGE = 'brio_theme_mode';

let overrideMode: ThemeMode = 'system';
const listeners = new Set<() => void>();
let charge = false;

/**
 * Relit le mode choisi au demarrage. Sans cela le choix ne survivait pas a la fermeture :
 * `overrideMode` n'est qu'une variable de module.
 */
export async function chargerLeModeEnregistre(): Promise<void> {
  if (charge) return;
  charge = true;

  try {
    const enregistre = await AsyncStorage.getItem(CLE_STOCKAGE);
    if (enregistre === 'light' || enregistre === 'dark' || enregistre === 'system') {
      overrideMode = enregistre;
      listeners.forEach(cb => cb());
    }
  } catch {
    // Stockage indisponible : on reste sur le mode systeme.
  }
}

/** Le compte garde son choix d'un appareil a l'autre, comme sur le web. */
async function conserver(mode: ThemeMode): Promise<void> {
  try { await AsyncStorage.setItem(CLE_STOCKAGE, mode); } catch { /* stockage refuse */ }
  try { await apiClient.post('/user/theme', { theme: mode }); } catch { /* le choix vaut deja localement */ }
}

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

    // Declenche ici plutot que dans chaque App.tsx : un appel oublie rendrait la
    // conservation inerte sans que rien ne le dise.
    void chargerLeModeEnregistre();

    return () => {
      listeners.delete(cb);
    };
  }, []);

  const setMode = (m: ThemeMode) => {
    overrideMode = m;
    listeners.forEach(cb => cb());
    void conserver(m);
  };

  const resolved: ResolvedScheme =
    overrideMode === 'system'
      ? (systemScheme === 'dark' ? 'dark' : systemScheme === 'light' ? 'light' : 'unspecified')
      : overrideMode;

  return { colorScheme: resolved, mode: overrideMode, setMode };
}
