import type { ColumnType } from './types';

/**
 * LA LOCALE DE LA CONSOLE, EN UN SEUL POINT.
 *
 * Elle etait ecrite `'fr-BE'` a TROIS endroits de ce fichier. La plateforme sert aussi la
 * France et le Maroc : un exploitant marocain lisait ses milliers separes a la belge
 * francophone, et ses dates au format belge.
 *
 * Une constante ne resout pas tout — l'application native n'a pas encore de reglage de
 * langue central — mais elle ramene trois valeurs eparses a une seule, qu'il suffira de
 * brancher le jour ou ce reglage existera.
 */
export const LOCALE_CONSOLE = 'fr-BE';

/**
 * LA DEVISE PAR DEFAUT — et le fait qu'elle SOIT un defaut est le point important.
 *
 * `currency: 'EUR'` etait code en dur : la console affichait TOUS les montants en euros,
 * y compris ceux d'une zone marocaine facturee en dirhams. Un montant faux sur un ecran de
 * pilotage se propage en decisions fausses.
 *
 * `formatCell` accepte desormais la devise de la ligne ; celle-ci ne sert que lorsque la
 * donnee n'en porte aucune.
 */
export const DEVISE_PAR_DEFAUT = 'EUR';

/**
 * Rend une valeur de cellule lisible, selon le type déclaré par le descripteur.
 *
 * POURQUOI LE TYPE VIENT DU SERVEUR ET NON D'UNE DEVINETTE. Deviner d'après la valeur reçue
 * afficherait un montant comme un nombre nu, et un identifiant à sept chiffres comme un montant.
 * Le descripteur sait ce que la colonne signifie ; le mobile se contente de l'appliquer.
 *
 * UNE VALEUR ABSENTE S'AFFICHE « — », JAMAIS « 0 » NI « null ». Un zéro affiché pour une donnée
 * manquante se lit comme une mesure, et personne ne va vérifier.
 */
export function formatCell(value: unknown, type: ColumnType, devise?: string | null): string {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  switch (type) {
    case 'money':
      return formatMoney(value, devise);

    case 'number':
      return typeof value === 'number' ? new Intl.NumberFormat(LOCALE_CONSOLE).format(value) : String(value);

    case 'date':
      return formatDate(value, false);

    case 'datetime':
      return formatDate(value, true);

    case 'bool':
      return value ? 'Oui' : 'Non';

    default:
      return String(value);
  }
}

function formatMoney(value: unknown, devise?: string | null): string {
  const nombre = typeof value === 'number' ? value : Number(value);

  if (Number.isNaN(nombre)) {
    return String(value);
  }

  return new Intl.NumberFormat(LOCALE_CONSOLE, {
    style: 'currency',
    currency: devise || DEVISE_PAR_DEFAUT,
  }).format(nombre);
}

function formatDate(value: unknown, withTime: boolean): string {
  const date = new Date(String(value));

  if (Number.isNaN(date.getTime())) {
    // Une date illisible s'affiche telle quelle plutôt que « Invalid Date » : au moins la valeur
    // brute permet de comprendre d'où vient le problème.
    return String(value);
  }

  return new Intl.DateTimeFormat(LOCALE_CONSOLE, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  }).format(date);
}
