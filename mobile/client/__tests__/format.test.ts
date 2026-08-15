import { libelleStatut, formatDateHeure } from '../src/lib/format';

/**
 * CE QUE LE CLIENT LIT — relevé à l'écran, sur l'accueil de l'app.
 *
 * Deux valeurs remontaient de l'API telles quelles : la pastille affichait « pending » et la carte
 * « 2026-08-20 à 11:00 », au milieu d'une app par ailleurs entièrement française. Rien n'échouait,
 * donc rien ne le signalait — seul un regard sur l'écran pouvait le voir.
 */
describe('libelleStatut', () => {
  it('traduit les états normalisés de l’API cliente', () => {
    expect(libelleStatut('pending')).toBe('En attente');
    expect(libelleStatut('confirmed')).toBe('Confirmée');
    expect(libelleStatut('in_progress')).toBe('En cours');
    expect(libelleStatut('completed')).toBe('Terminée');
    expect(libelleStatut('cancelled')).toBe('Annulée');
  });

  it('rend un statut absent lisible plutôt que vide', () => {
    expect(libelleStatut(null)).toBe('À préciser');
    expect(libelleStatut(undefined)).toBe('À préciser');
  });

  /**
   * LE TÉMOIN : un état qu'on n'a pas prévu reste VISIBLE.
   *
   * Le masquer laisserait croire que la réservation n'a pas d'état ; le montrer tel quel se
   * remarque et se corrige. Sans ce test, « traduire » pourrait dériver en « effacer ».
   */
  it('laisse passer un état inconnu au lieu de l’effacer', () => {
    expect(libelleStatut('en_route')).toBe('en_route');
  });
});

describe('formatDateHeure', () => {
  it('écrit la date en français', () => {
    expect(formatDateHeure('2026-08-20', '11:00')).toBe('20 août 2026 à 11h00');
  });

  /** Les secondes disparaissent ; le zéro de tête reste, comme sur le web (« 09h00 »). */
  it('ignore les secondes, que personne ne lit', () => {
    expect(formatDateHeure('2026-08-17', '09:00:00')).toBe('17 août 2026 à 09h00');
  });

  it('accepte un horodatage complet', () => {
    expect(formatDateHeure('2026-12-01T00:00:00.000Z', '14:30')).toBe('1 décembre 2026 à 14h30');
  });

  it('rend la date seule quand l’heure manque', () => {
    expect(formatDateHeure('2026-08-20', null)).toBe('20 août 2026');
  });

  /** Une entrée illisible ressort telle quelle : mieux qu'un tiret sur l'écran d'un client. */
  it('ne perd pas une valeur qu’il ne sait pas lire', () => {
    expect(formatDateHeure('bientôt', '11:00')).toBe('bientôt à 11:00');
    expect(formatDateHeure(null, null)).toBe('');
  });
});
