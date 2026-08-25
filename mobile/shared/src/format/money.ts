/**
 * LE FORMATEUR MONÉTAIRE PARTAGÉ — l'équivalent natif de `<x-money>`.
 *
 * Il n'en existait aucun. Chaque écran écrivait sa propre ligne, et c'était TOUJOURS la
 * même, recopiée telle quelle :
 *
 *     new Intl.NumberFormat('fr-BE', { style: 'currency', currency: 'EUR' })
 *
 * Deux valeurs y sont figées, et les deux sont fausses pour une partie du marché. La
 * plateforme sert la Belgique, la France et le Maroc : la devise suit la POSITION, et un
 * montant marocain affiché en euros n'est pas une coquetterie de format — c'est un
 * engagement commercial qu'on ne tiendra pas.
 *
 * POURQUOI UN MODULE ET NON UN COMPOSANT. Ces montants sont assemblés dans des chaînes —
 * « Annuler coûte X », « il reste Y » — avant d'atteindre le rendu. Un composant obligerait
 * à découper la phrase ; une fonction s'insère où la phrase se construit.
 */

/**
 * La locale d'affichage.
 *
 * L'application native n'a pas encore de réglage de langue central : cette constante ramène
 * à un seul endroit ce qui était écrit en dur dans neuf fichiers, et se branchera le jour où
 * ce réglage existera.
 */
export const LOCALE_AFFICHAGE = 'fr-BE';

/**
 * La devise employée quand la donnée n'en porte aucune.
 *
 * Elle reste l'euro, et c'est délibéré : la majorité du parc est belge. Ce qui change, c'est
 * qu'elle soit un DÉFAUT explicite plutôt qu'une valeur figée dans chaque appel.
 */
export const DEVISE_PAR_DEFAUT = 'EUR';

/**
 * Formate un montant en unités (euros, dirhams…), pas en centimes.
 *
 * Une devise absente ou vide retombe sur le défaut plutôt que de lever : un écran qui
 * n'affiche rien parce qu'une devise manque est pire qu'un écran qui affiche l'euro.
 */
export function formatMontant(montant: number, devise?: string | null): string {
  if (!Number.isFinite(montant)) {
    return '—';
  }

  return new Intl.NumberFormat(LOCALE_AFFICHAGE, {
    style: 'currency',
    currency: devise || DEVISE_PAR_DEFAUT,
  }).format(montant);
}

/**
 * Formate un montant reçu en CENTIMES — la forme que l'API emploie partout.
 *
 * Elle existe séparément pour que la division par cent ne soit plus recopiée : elle l'était
 * dans chaque appelant, et une division oubliée affiche cent fois le prix réel.
 */
export function formatCentimes(centimes: number, devise?: string | null): string {
  if (!Number.isFinite(centimes)) {
    return '—';
  }

  return formatMontant(centimes / 100, devise);
}
