/**
 * LE SON DE L'OFFRE — parce qu'une modale silencieuse expire sans que personne ne l'ait vue.
 *
 * La vibration seule ne suffit pas : le téléphone est posé sur un tableau de bord, dans une sacoche
 * à côté d'une perceuse, ou sur un chantier bruyant. Vingt secondes s'écoulent, l'offre part au
 * suivant, et le prestataire découvre en fin de journée un taux d'acceptation qu'il ne comprend pas.
 *
 * QUAND L'OFFRE ARRIVE PAR NOTIFICATION, le système joue déjà son propre son — `expo-notifications`
 * est configuré avec `shouldPlaySound`. Mais l'application au premier plan reçoit l'offre par le
 * canal temps réel ou par sondage, sans notification et donc sans aucun son : c'est précisément le
 * cas où le prestataire est au volant, l'application ouverte, et le plus susceptible d'accepter.
 *
 * SOFT-FAIL INTÉGRAL. Un appareil sans sortie audio, un mode silencieux, un module natif absent en
 * Expo Go : rien de tout cela ne doit empêcher l'offre de s'afficher. Le son est un rappel, pas le
 * message. Le module est donc chargé PARESSEUSEMENT dans un try/catch — un import statique d'un
 * module natif absent fait tomber l'écran entier, pas seulement le son.
 *
 * `require` ET NON `await import` : le second n'existe pas dans l'environnement de test (Jest le
 * refuse sans `--experimental-vm-modules`), si bien qu'un `catch` silencieux avalait l'erreur et
 * rendait le son INVÉRIFIABLE — un test vert pour la pire des raisons. Le voisin `push/foreground`
 * charge `expo-notifications` de la même façon.
 */

interface Lecteur {
  play: () => void;
  remove: () => void;
  seekTo?: (secondes: number) => Promise<void>;
}

let lecteur: Lecteur | null = null;

export function jouerCarillonDOffre(): void {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const audio = require('expo-audio');

    // `createAudioPlayer` est l'API impérative d'expo-audio : elle s'utilise hors composant,
    // contrairement au hook `useAudioPlayer`, et c'est ce qu'il faut ici — le son suit l'ARRIVÉE
    // d'une offre, pas le cycle de rendu d'un écran. Le lecteur est gardé entre deux offres :
    // recréer une ressource native à chaque fois ajouterait une latence là où on en a le moins.
    const actuel: Lecteur =
      lecteur ?? (audio.createAudioPlayer(require('../../assets/sounds/offre.wav')) as Lecteur);

    lecteur = actuel;

    // Rembobiner : deux offres consécutives doivent sonner deux fois, pas une seule.
    void actuel.seekTo?.(0);
    actuel.play();
  } catch {
    // Silencieux à dessein — voir l'en-tête.
  }
}

/** Libère la ressource native. Appelé quand l'hôte d'offres est démonté. */
export function libererCarillon(): void {
  try {
    lecteur?.remove();
  } catch {
    // Rien à faire : le lecteur était déjà parti.
  } finally {
    lecteur = null;
  }
}
