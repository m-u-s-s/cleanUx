import AsyncStorage from '@react-native-async-storage/async-storage';

const DRAFT_KEY = 'booking_draft';
const DRAFT_TTL_MS = 24 * 60 * 60 * 1000; // 24 hours

type DraftPayload = Record<string, unknown> & { savedAt: number };

export const bookingDraft = {
  async save(draft: Record<string, unknown>): Promise<void> {
    const payload: DraftPayload = { ...draft, savedAt: Date.now() };
    await AsyncStorage.setItem(DRAFT_KEY, JSON.stringify(payload));
  },

  async load(): Promise<Record<string, unknown> | null> {
    const raw = await AsyncStorage.getItem(DRAFT_KEY);
    if (!raw) return null;
    const data = JSON.parse(raw) as DraftPayload;
    if (Date.now() - data.savedAt > DRAFT_TTL_MS) {
      await AsyncStorage.removeItem(DRAFT_KEY);
      return null;
    }
    const { savedAt: _savedAt, ...state } = data;
    return state;
  },

  async clear(): Promise<void> {
    await AsyncStorage.removeItem(DRAFT_KEY);
  },
};
