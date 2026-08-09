import AsyncStorage from '@react-native-async-storage/async-storage';

/**
 * Sauvegarde locale du dossier d'inscription en cours.
 *
 * Un parcours en dix écrans se perd à la moindre interruption — un appel entrant, un changement
 * d'app, un système qui tue le processus. Chaque réponse est donc écrite localement dès sa saisie
 * et le wizard reprend là où il s'était arrêté.
 *
 * Deux valeurs ne sont JAMAIS écrites : le mot de passe et le jeton de vérification du téléphone.
 * AsyncStorage n'est pas chiffré ; un mot de passe s'y trouverait en clair, et le jeton y serait
 * un secret réutilisable. Ces deux champs sont donc redemandés après une interruption — c'est le
 * seul endroit du parcours où la reprise n'est pas exacte, et c'est délibéré.
 */

const KEY = 'brio.provider.register.draft.v1';

export interface RegisterDraft {
  phone: string;
  phoneVerified: boolean;
  firstName: string;
  lastName: string;
  email: string;
  providerKind: 'independent' | 'company' | null;
  companyName: string;
  vatNumber: string;
  tradeId: number | null;
  zoneIds: number[];
  tradeAnswers: Record<string, string | boolean>;
  acceptTerms: boolean;
}

export const emptyDraft: RegisterDraft = {
  phone: '',
  phoneVerified: false,
  firstName: '',
  lastName: '',
  email: '',
  providerKind: null,
  companyName: '',
  vatNumber: '',
  tradeId: null,
  zoneIds: [],
  tradeAnswers: {},
  acceptTerms: false,
};

export async function loadDraft(): Promise<RegisterDraft | null> {
  try {
    const raw = await AsyncStorage.getItem(KEY);
    if (!raw) return null;

    const parsed = JSON.parse(raw) as Partial<RegisterDraft>;

    // Fusion avec le brouillon vide : une version antérieure de l'app a pu écrire moins de
    // champs, et un champ absent doit valoir sa valeur par défaut plutôt qu'`undefined`.
    return { ...emptyDraft, ...parsed };
  } catch {
    // Brouillon illisible : on repart de zéro plutôt que de bloquer l'inscription.
    return null;
  }
}

export async function saveDraft(draft: RegisterDraft): Promise<void> {
  try {
    // `phoneVerified` est délibérément conservé à false au rechargement : sans le jeton, qui
    // n'est pas persisté, la vérification devra être refaite.
    await AsyncStorage.setItem(KEY, JSON.stringify({ ...draft, phoneVerified: false }));
  } catch {
    // Un échec d'écriture ne doit pas interrompre la saisie : la reprise est un confort.
  }
}

export async function clearDraft(): Promise<void> {
  try {
    await AsyncStorage.removeItem(KEY);
  } catch {
    // Sans conséquence : le brouillon suivant écrasera celui-ci.
  }
}
