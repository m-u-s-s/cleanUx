import type { User } from '../api/types';

/**
 * CE QUE LE SERVEUR A ACCORDÉ — ET RIEN QUE CELA.
 *
 * L'application n'a PAS de matrice rôle → permissions, et ne doit pas en avoir. Recopier la
 * correspondance côté client l'aurait fait diverger de `PermissionService` au premier ajustement,
 * et surtout : une société qui règle sa propre matrice en base n'aurait rien changé sur les
 * téléphones. Le serveur envoie les clés résolues dans `/auth/me` et à la connexion ; ce fichier ne
 * fait que les lire.
 *
 * DÉFAUT-REFUS, sans exception. Champ absent, liste absente, utilisateur absent : `false`. C'est le
 * cas d'une application plus ancienne que la clé qu'elle interroge, et refuser est alors le bon
 * comportement — l'inverse ouvrirait un écran que l'API refusera de servir, ce qui donne un bouton
 * qui échoue plutôt qu'un bouton absent.
 *
 * CE N'EST PAS UNE FRONTIÈRE DE PRIVILÈGES. Cacher un bouton n'a jamais protégé une donnée : la
 * garde est côté serveur, sur chaque route. Ceci évite de promettre ce que l'API refusera.
 */
export function can(user: User | null | undefined, permission: string): boolean {
  const accordees = user?.organization_permissions;

  if (!Array.isArray(accordees)) {
    return false;
  }

  return accordees.includes(permission);
}

/** Au moins une des clés — pour un écran qui a plusieurs portes d'entrée légitimes. */
export function canAny(user: User | null | undefined, permissions: string[]): boolean {
  return permissions.some((permission) => can(user, permission));
}

/**
 * Le sous-rôle, pour l'AFFICHER.
 *
 * Volontairement séparé de `can()` : le jour où l'on serait tenté d'écrire
 * `role === 'dispatcher'` pour ouvrir un écran, la matrice réglable de la société cesserait d'être
 * lue. Cette fonction ne sert qu'à écrire un libellé sous un nom.
 */
export function organizationRole(user: User | null | undefined): string | null {
  return user?.organization_role ?? null;
}
