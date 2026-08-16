/**
 * LES JOURS, INDEXÉS PAR LA CONVENTION DU SERVEUR — 0 = DIMANCHE.
 *
 * L'écran précédent portait `const DAYS = ['Lun','Mar',…,'Dim']` et l'indexait par le jour reçu.
 * Un créneau du dimanche (`weekday = 0`) s'affichait donc « Lun », et TOUS les jours étaient
 * décalés d'un cran. Le décalage est invisible en lecture — sept étiquettes plausibles — et se
 * découvre le jour où quelqu'un se déplace un lundi pour un créneau du dimanche.
 *
 * Le tableau suit désormais l'index réel. L'ORDRE D'AFFICHAGE, lui, reste lundi-first : c'est ce
 * qu'attend une semaine européenne. Les deux notions sont séparées exprès — les confondre est
 * précisément ce qui a produit le décalage.
 */
export const WEEKDAY_LABELS: Record<number, string> = {
  0: 'Dimanche',
  1: 'Lundi',
  2: 'Mardi',
  3: 'Mercredi',
  4: 'Jeudi',
  5: 'Vendredi',
  6: 'Samedi',
};

export const WEEKDAY_SHORT: Record<number, string> = {
  0: 'Dim',
  1: 'Lun',
  2: 'Mar',
  3: 'Mer',
  4: 'Jeu',
  5: 'Ven',
  6: 'Sam',
};

/** L'ordre d'une semaine européenne : lundi d'abord, dimanche en dernier. */
export const WEEK_ORDER: number[] = [1, 2, 3, 4, 5, 6, 0];

export function weekdayLabel(weekday: number): string {
  return WEEKDAY_LABELS[weekday] ?? '—';
}

/** `08:00:00` → `08:00`. L'API rend des secondes dont personne n'a besoin à l'écran. */
export function hhmm(time: string | null | undefined): string {
  if (!time) return '';

  return time.slice(0, 5);
}

/** `2026-08-16` → `dimanche 16 août 2026`, sans dépendance de localisation. */
export function formatDate(iso: string): string {
  const d = new Date(`${iso}T00:00:00`);

  if (Number.isNaN(d.getTime())) return iso;

  return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}
