import type { ColumnType } from './types';

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
export function formatCell(value: unknown, type: ColumnType): string {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  switch (type) {
    case 'money':
      return formatMoney(value);

    case 'number':
      return typeof value === 'number' ? new Intl.NumberFormat('fr-BE').format(value) : String(value);

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

function formatMoney(value: unknown): string {
  const nombre = typeof value === 'number' ? value : Number(value);

  if (Number.isNaN(nombre)) {
    return String(value);
  }

  return new Intl.NumberFormat('fr-BE', { style: 'currency', currency: 'EUR' }).format(nombre);
}

function formatDate(value: unknown, withTime: boolean): string {
  const date = new Date(String(value));

  if (Number.isNaN(date.getTime())) {
    // Une date illisible s'affiche telle quelle plutôt que « Invalid Date » : au moins la valeur
    // brute permet de comprendre d'où vient le problème.
    return String(value);
  }

  return new Intl.DateTimeFormat('fr-BE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  }).format(date);
}
