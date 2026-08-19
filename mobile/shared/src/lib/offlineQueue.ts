import AsyncStorage from '@react-native-async-storage/async-storage';
import { apiClient } from '../api/client';
import { ApiError } from '../api/types';

export interface QueuedAction {
  id: string;
  url: string;
  method: 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body: unknown;
  createdAt: number;
  /** Ce que l'utilisateur croit avoir fait, pour le lui dire si ça échoue définitivement. */
  label?: string;
}

/** Une action que le serveur a REFUSÉE : elle ne repartira jamais, on la sort de la file. */
export interface DroppedAction extends QueuedAction {
  reason: string;
}

const QUEUE_KEY = 'brio_offline_queue';
const ABANDONS_KEY = 'brio_offline_dropped';

/**
 * COMBIEN DE TEMPS UNE ACTION MÉRITE D'ÊTRE REJOUÉE.
 *
 * Vingt-quatre heures. Une case cochée hier soir a encore du sens ce matin ; la même cochée il y
 * a trois semaines décrirait une mission close depuis longtemps, et la rejouer réécrirait un
 * passé que plus personne ne regarde.
 */
const AGE_MAX_MS = 24 * 60 * 60 * 1000;

/**
 * Envoie une action ; rend `true` en cas de succès.
 *
 * `permanent` distingue les deux échecs qu'il ne faut surtout pas confondre — voir `flush()`.
 */
export type ActionSender = (action: QueuedAction) => Promise<boolean | { ok: false; permanent: true; reason: string }>;

/**
 * M16 — la relecture passe par apiClient pour hériter de la baseURL configurée (action.url est
 * relative) ET du jeton injecté par l'intercepteur. Le `fetch(action.url, …)` d'origine échouait
 * toujours : URL relative et aucune authentification.
 */
const defaultSender: ActionSender = async (action) => {
  try {
    await apiClient({ url: action.url, method: action.method, data: action.body });

    return true;
  } catch (error: unknown) {
    /*
     * DEUX ÉCHECS QUI NE SE RESSEMBLENT PAS.
     *
     * Le réseau qui manque se retente : c'est exactement le cas pour lequel cette file existe.
     * Le serveur qui REFUSE ne se retente pas — « cette tâche n'existe plus », « la mission est
     * close » ne deviendront pas vrais en insistant. L'ancienne version les traitait pareil : un
     * refus définitif restait dans la file et repartait à chaque reconnexion, pour toujours.
     */
    if (error instanceof ApiError && error.status > 0) {
      return { ok: false, permanent: true, reason: error.message };
    }

    return false;
  }
};

export const offlineQueue = {
  async enqueue(action: Omit<QueuedAction, 'id' | 'createdAt'>): Promise<void> {
    const queue = await this.getAll();
    queue.push({
      ...action,
      id: `${Date.now()}_${Math.random().toString(36).slice(2)}`,
      createdAt: Date.now(),
    });
    await AsyncStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
  },

  async getAll(): Promise<QueuedAction[]> {
    const raw = await AsyncStorage.getItem(QUEUE_KEY);
    return raw ? JSON.parse(raw) : [];
  },

  /** Ce que le serveur a refusé, en attente d'être montré à quelqu'un. */
  async abandons(): Promise<DroppedAction[]> {
    const raw = await AsyncStorage.getItem(ABANDONS_KEY);
    return raw ? JSON.parse(raw) : [];
  },

  async oublierLesAbandons(): Promise<void> {
    await AsyncStorage.removeItem(ABANDONS_KEY);
  },

  async flush(
    sender: ActionSender = defaultSender,
    maintenant: number = Date.now(),
  ): Promise<{ success: number; failed: number; dropped: number }> {
    const queue = await this.getAll();
    let success = 0;
    let failed = 0;
    const remaining: QueuedAction[] = [];
    const abandonnees: DroppedAction[] = [];

    for (const action of queue) {
      // Trop vieille : on ne rejoue pas un geste dont le contexte a disparu.
      if (maintenant - action.createdAt > AGE_MAX_MS) {
        abandonnees.push({ ...action, reason: 'Trop ancienne pour être renvoyée.' });
        continue;
      }

      let resultat: Awaited<ReturnType<ActionSender>> = false;

      try {
        resultat = await sender(action);
      } catch {
        resultat = false;
      }

      if (resultat === true) {
        success++;

        continue;
      }

      if (resultat !== false && resultat.permanent) {
        abandonnees.push({ ...action, reason: resultat.reason });

        continue;
      }

      remaining.push(action);
      failed++;
    }

    await AsyncStorage.setItem(QUEUE_KEY, JSON.stringify(remaining));

    if (abandonnees.length > 0) {
      const deja = await this.abandons();
      await AsyncStorage.setItem(ABANDONS_KEY, JSON.stringify([...deja, ...abandonnees]));
    }

    return { success, failed, dropped: abandonnees.length };
  },

  async clear(): Promise<void> {
    await AsyncStorage.removeItem(QUEUE_KEY);
  },
};
