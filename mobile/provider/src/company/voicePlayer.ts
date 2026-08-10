/**
 * ÉCOUTER UNE NOTE VOCALE — le geste qui manquait de l'autre côté.
 *
 * On pouvait ENREGISTRER et ENVOYER une note depuis le lot 8, et personne ne pouvait l'écouter :
 * l'API ne disait ni que le message était vocal ni où trouver le son, et aucun lecteur n'existait.
 * Le fil affichait « 🎙️ Note vocale » comme un texte ordinaire. Une messagerie vocale à sens unique
 * est une messagerie qu'on abandonne pour WhatsApp — hors de l'outil, hors de toute trace, hors de
 * la modération.
 *
 * MÊMES PRÉCAUTIONS QUE L'ENREGISTREUR, et pour les mêmes raisons : `expo-audio` embarque du natif,
 * le charger en tête de module le charge au DÉMARRAGE et suffit à faire échouer l'application dans
 * Expo Go. Il est donc chargé au moment où l'on appuie sur lecture, dans un `try`, avec `require`
 * et non `await import` — Jest refuse l'import dynamique sans `--experimental-vm-modules`, si bien
 * qu'un `catch` silencieux rendrait ce chemin invérifiable.
 */

export interface LecteurDeNote {
  arreter: () => Promise<void>;
}

/**
 * Joue le son situé à cette adresse.
 *
 * @returns de quoi l'interrompre, ou `null` si le module natif n'est pas disponible ou si le son
 *          n'a pas pu être chargé — l'appelant le dit à l'utilisateur plutôt que de laisser un
 *          bouton qui ne fait rien.
 */
export async function jouerNoteVocale(adresse: string): Promise<LecteurDeNote | null> {
  const audio = chargerExpoAudio();

  if (audio === null || !adresse) {
    return null;
  }

  let lecteur: any = null;

  try {
    /*
     * Le mode audio est repositionné pour la LECTURE. L'enregistreur laisse la session en mode
     * enregistrement : sans cette bascule, la note suivante se joue dans l'écouteur téléphonique
     * au lieu du haut-parleur, à un volume que personne n'entend sur un chantier.
     */
    await audio.setAudioModeAsync({ allowsRecording: false, playsInSilentMode: true });

    lecteur = audio.createAudioPlayer({ uri: adresse });
    lecteur.play();

    return {
      arreter: async () => {
        try {
          lecteur?.pause();
          lecteur?.remove();
        } catch {
          // Déjà libéré : rien à faire.
        }
      },
    };
  } catch {
    /*
     * Adresse expirée, réseau coupé, fichier encore en analyse antivirus : aucune de ces situations
     * ne justifie de faire tomber l'écran. On libère la ressource native — un lecteur laissé ouvert
     * garde la session audio pour toute l'application.
     */
    try {
      lecteur?.remove();
    } catch {
      // Jamais créé.
    }

    return null;
  }
}

function chargerExpoAudio(): any | null {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    return require('expo-audio');
  } catch {
    // Dev-client non reconstruit après l'ajout de la dépendance : le natif manque.
    return null;
  }
}
