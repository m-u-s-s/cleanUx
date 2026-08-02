/**
 * Quelle application parle : `client` ou `provider`.
 *
 * Les deux APK partagent ce code, le même point de connexion et les mêmes jetons. Le serveur ne
 * peut refuser un compte prestataire dans l'application cliente — et l'inverse — que s'il sait à
 * qui il parle. Chaque application se déclare donc au démarrage, et l'en-tête voyage ensuite sur
 * toutes les requêtes.
 *
 * POURQUOI UN APPEL EXPLICITE PLUTÔT QU'UNE VARIABLE D'ENVIRONNEMENT. Une variable absente d'une
 * chaîne de build produirait une application muette : le serveur laisse passer quand rien n'est
 * déclaré — il le faut, pour ne pas déconnecter le parc déjà installé — donc l'oubli ne se verrait
 * nulle part. Un appel dans le point d'entrée se teste ; une variable d'environnement manquante,
 * non.
 */
export type AppAudience = 'client' | 'provider';

let current: AppAudience | null = null;

export function setAppAudience(audience: AppAudience | null): void {
  current = audience;
}

export function getAppAudience(): AppAudience | null {
  return current;
}

export const APP_AUDIENCE_HEADER = 'X-CleanUx-App';
