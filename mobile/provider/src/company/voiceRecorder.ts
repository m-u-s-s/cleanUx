/**
 * ENREGISTRER UNE NOTE VOCALE — et le faire de façon à ce que l'application démarre quand même.
 *
 * `expo-av` embarque du natif. L'importer en tête de module le charge au DÉMARRAGE, ce qui suffit à
 * faire échouer l'application dans Expo Go — le dépôt a déjà payé ce piège avec
 * `expo-notifications`, dont l'import lève au chargement. L'import est donc DYNAMIQUE et fait au
 * moment où l'on appuie sur le micro.
 *
 * ET LE MODULE PEUT ÊTRE ABSENT. Un dev-client qui n'a pas été reconstruit après l'ajout de la
 * dépendance n'a pas le natif : on rend `null` plutôt que de lever, et l'appelant le dit à
 * l'utilisateur. Un écran de messagerie qui plante sur le bouton micro serait pire que pas de
 * notes vocales.
 */

export interface NoteVocale {
  /** Le fichier prêt à être joint à un `FormData`. */
  fichier: { uri: string; name: string; type: string };
  dureeSecondes: number;
}

/**
 * Enregistre jusqu'à l'appui suivant, puis rend le fichier.
 *
 * @returns `null` si la permission est refusée ou si le module natif n'est pas disponible
 */
export async function enregistrerNoteVocale(): Promise<NoteVocale | null> {
  const av = await chargerExpoAv();

  if (av === null) {
    return null;
  }

  try {
    const permission = await av.Audio.requestPermissionsAsync();

    // Refuser le micro est une réponse légitime : on ne réessaie pas, et on ne bloque rien.
    if (!permission.granted) {
      return null;
    }

    await av.Audio.setAudioModeAsync({ allowsRecordingIOS: true, playsInSilentModeIOS: true });

    const { recording } = await av.Audio.Recording.createAsync(
      av.Audio.RecordingOptionsPresets.HIGH_QUALITY,
    );

    /*
     * DURÉE FIXE PLUTÔT QU'UN APPUI-RELÂCHE.
     *
     * Un « maintenir pour parler » suppose de garder le doigt sur l'écran — précisément ce qu'on ne
     * peut pas faire avec des gants sur un chantier. Trente secondes couvrent une consigne ; au-delà,
     * c'est un appel, et le lot 8 le rendra possible.
     */
    await attendre(30_000);

    await recording.stopAndUnloadAsync();

    const uri = recording.getURI();

    if (uri === null) {
      return null;
    }

    const statut = await recording.getStatusAsync();

    return {
      fichier: { uri, name: 'note.m4a', type: 'audio/m4a' },
      dureeSecondes: Math.max(1, Math.round((statut.durationMillis ?? 0) / 1000)),
    };
  } catch {
    // Micro occupé par un appel, stockage plein, permission révoquée en cours de route : aucune de
    // ces situations ne justifie de faire tomber l'écran.
    return null;
  }
}

/**
 * L'import est dynamique, protégé, ET NON TYPÉ STATIQUEMENT — les trois pour la même raison.
 *
 * `expo-av` N'EST PAS ENCORE UNE DÉPENDANCE de ce workspace : l'ajouter suppose un `npm install`
 * dans le monorepo puis une RECONSTRUCTION DU DEV-CLIENT, qu'aucun test ni aucun typecheck ne peut
 * valider ici. Une importation statique ferait échouer `tsc` sur toute la base pour un module qui
 * n'existe pas encore, et un `expo start` sur un client non reconstruit échouerait en accusant
 * `expo-modules-core` — piège déjà rencontré sur ce dépôt.
 *
 * Le chemin est donc prêt et inerte : le bouton micro rend `null`, l'écran le dit, et rien ne casse.
 * Le jour où la dépendance est ajoutée et le dev-client reconstruit, ce fichier fonctionne sans
 * modification.
 */
async function chargerExpoAv(): Promise<any | null> {
  try {
    // Le nom est calculé pour que le résolveur de modules ne l'exige pas à la compilation.
    const nomDuModule = 'expo-av';

    return await import(/* @vite-ignore */ nomDuModule);
  } catch {
    return null;
  }
}

function attendre(ms: number): Promise<void> {
  return new Promise((resoudre) => setTimeout(resoudre, ms));
}
