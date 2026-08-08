/**
 * REJOINDRE UNE SALLE LIVEKIT — et le faire de façon à ce que l'application démarre quand même.
 *
 * `@livekit/react-native` embarque du natif et un plugin Expo. L'importer en tête de module le
 * charge au DÉMARRAGE, ce qui suffit à faire échouer l'application dans Expo Go — le dépôt a déjà
 * payé ce piège avec `expo-notifications`, dont l'import lève au chargement.
 *
 * LE MODULE N'EST PAS ENCORE UNE DÉPENDANCE de ce workspace, et l'ajouter n'est pas un
 * `npm install` de plus : il faut RECONSTRUIRE LE DEV-CLIENT. Aucun test ni aucun typecheck ne peut
 * le valider ici, et un plugin sans `app.plugin.js` fait accuser `expo-modules-core` — piège connu
 * de ce dépôt, où l'erreur désigne toujours le mauvais coupable.
 *
 * Le chemin est donc PRÊT ET INERTE : sans le natif, `rejoindre()` rend `null`, l'écran le dit, et
 * rien ne casse. Le jour où la dépendance est ajoutée et le dev-client reconstruit, ce fichier
 * fonctionne sans modification.
 */

export interface SalleRejointe {
  /** Ce qu'il faut appeler en quittant : la salle garde le micro tant qu'on ne la ferme pas. */
  quitter: () => Promise<void>;
}

export interface ParametresDeSalle {
  url: string;
  token: string;
  video: boolean;
}

export async function rejoindre(parametres: ParametresDeSalle): Promise<SalleRejointe | null> {
  const livekit = await chargerLiveKit();

  if (livekit === null) {
    return null;
  }

  try {
    const salle = new livekit.Room();

    await salle.connect(parametres.url, parametres.token);

    /*
     * L'AUDIO D'ABORD, LA VIDÉO EN OPTION. Sur un chantier, la vidéo consomme une bande passante
     * qu'on n'a pas toujours et une batterie qu'on ne peut pas recharger ; elle sert à MONTRER une
     * fuite, pas à tenir une conversation.
     */
    await salle.localParticipant.setMicrophoneEnabled(true);

    if (parametres.video) {
      await salle.localParticipant.setCameraEnabled(true);
    }

    return {
      quitter: async () => {
        await salle.disconnect();
      },
    };
  } catch {
    // Réseau coupé, jeton expiré, permission micro révoquée : aucune de ces situations ne justifie
    // de faire tomber l'écran.
    return null;
  }
}

/** Import dynamique, protégé, et non typé statiquement : voir l'en-tête du fichier. */
async function chargerLiveKit(): Promise<any | null> {
  try {
    // Le nom est calculé pour que le résolveur de modules ne l'exige pas à la compilation.
    const nomDuModule = '@livekit/react-native';

    return await import(/* @vite-ignore */ nomDuModule);
  } catch {
    return null;
  }
}
