/**
 * ENREGISTRER UNE NOTE VOCALE — et le faire de façon à ce que l'application démarre quand même.
 *
 * `expo-audio` embarque du natif. Le charger en tête de module le charge au DÉMARRAGE, ce qui suffit
 * à faire échouer l'application dans Expo Go — le dépôt a déjà payé ce piège avec
 * `expo-notifications`, dont l'import lève au chargement. Le module est donc chargé au moment où
 * l'on appuie sur le micro, dans un `try`.
 *
 * POURQUOI `expo-audio` ET NON `expo-av`. Le chemin visait `expo-av`, qui n'a jamais été installé :
 * le bouton micro rendait donc `null` sur tous les appareils, silencieusement, depuis le lot 8. Et
 * il ne pouvait pas l'être — `expo-av` est retiré à partir du SDK 54, `expo-audio` est son
 * successeur. Une dépendance déclarée aurait échoué à l'installation ; c'est le chemin lui-même qui
 * était périmé, pas l'installation qui manquait.
 *
 * `require` ET NON `await import` : Jest refuse l'import dynamique sans `--experimental-vm-modules`,
 * si bien qu'un `catch` silencieux avalait tout et rendait ce chemin INVÉRIFIABLE — vert pour la
 * pire des raisons.
 */

export interface NoteVocale {
  /** Le fichier prêt à être joint à un `FormData`. */
  fichier: { uri: string; name: string; type: string };
  dureeSecondes: number;
}

/** La durée maximale d'une note. Au-delà, c'est un appel — et le lot 8 l'a rendu possible. */
const DUREE_MAX_MS = 30_000;

/**
 * Enregistre une note, puis rend le fichier.
 *
 * @returns `null` si la permission est refusée ou si le module natif n'est pas disponible
 */
export async function enregistrerNoteVocale(): Promise<NoteVocale | null> {
  const audio = chargerExpoAudio();

  if (audio === null) {
    return null;
  }

  let enregistreur: any = null;

  try {
    const permission = await audio.requestRecordingPermissionsAsync();

    // Refuser le micro est une réponse légitime : on ne réessaie pas, et on ne bloque rien.
    if (!permission.granted) {
      return null;
    }

    await audio.setAudioModeAsync({ allowsRecording: true, playsInSilentMode: true });

    enregistreur = new audio.AudioRecorder(audio.RecordingPresets.HIGH_QUALITY);

    // `prepareToRecordAsync` alloue le fichier de sortie. Sans lui, `record()` démarre sur un
    // enregistreur sans destination et `uri` reste nul à l'arrêt.
    await enregistreur.prepareToRecordAsync();
    enregistreur.record();

    /*
     * DURÉE FIXE PLUTÔT QU'UN APPUI-RELÂCHE.
     *
     * Un « maintenir pour parler » suppose de garder le doigt sur l'écran — précisément ce qu'on ne
     * peut pas faire avec des gants sur un chantier.
     */
    await attendre(DUREE_MAX_MS);

    await enregistreur.stop();

    const uri: string | null = enregistreur.uri ?? null;

    if (uri === null) {
      return null;
    }

    const secondes = Number(enregistreur.currentTime ?? 0);

    return {
      fichier: { uri, name: 'note.m4a', type: 'audio/m4a' },
      dureeSecondes: Math.max(1, Math.round(secondes > 0 ? secondes : DUREE_MAX_MS / 1000)),
    };
  } catch {
    /*
     * Micro occupé par un appel, stockage plein, permission révoquée en cours de route : aucune de
     * ces situations ne justifie de faire tomber l'écran. On tente quand même de rendre la
     * ressource native — un enregistreur laissé ouvert garde le micro pour toute l'application, et
     * le suivant échouerait sans qu'on comprenne pourquoi.
     */
    try {
      await enregistreur?.stop();
    } catch {
      // Déjà arrêté, ou jamais démarré.
    }

    return null;
  }
}

function chargerExpoAudio(): any | null {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    return require('expo-audio');
  } catch {
    // Dev-client non reconstruit après l'ajout de la dépendance : le natif manque. On rend `null`
    // plutôt que de lever, et l'appelant le dit à l'utilisateur.
    return null;
  }
}

function attendre(ms: number): Promise<void> {
  return new Promise((resoudre) => setTimeout(resoudre, ms));
}
