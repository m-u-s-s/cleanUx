/**
 * Les modules d'administration servis par un écran natif SUR-MESURE plutôt que par le moteur.
 *
 * POURQUOI CETTE TABLE EXISTE. Le moteur de console rend n'importe quel domaine décrit par un
 * descripteur — liste, détail, formulaire, actions. Certains domaines demandent autre chose :
 * demander trois valeurs avant d'agir, naviguer un arbre, choisir dans une liste de candidats
 * scorés. Ceux-là passent en `coverage: 'screen'` côté serveur, et cette table dit sur quel écran
 * l'annuaire doit les ouvrir.
 *
 * LES DEUX SENS SONT GARDÉS PAR UN TEST. Un module déclaré `screen` sans entrée ici s'ouvrirait
 * sur la liste générique — un écran qui n'est pas celui qu'on a écrit, sans que rien ne le dise.
 * Une entrée ici sur un module resté `descriptor` livrerait un écran que personne ne voit. Les
 * deux écarts font échouer `nativeScreens.test.ts`, tout comme le serveur refuse un module
 * `descriptor` sans descripteur.
 *
 * L'écran nommé doit aussi être MONTÉ dans `RootNavigator` : le test le vérifie, parce qu'une
 * route inconnue fait tomber la navigation à l'ouverture, pas à la compilation.
 */
export const NATIVE_ADMIN_SCREENS: Record<string, { screen: string }> = {
  // Renseigné domaine par domaine (sous-projet C, tâches 2 à 5).
};
