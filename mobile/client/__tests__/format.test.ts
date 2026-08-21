import { libelleStatut, formatDateHeure, formatHeureDuFil, formatHeureDuFilCompacte } from '../src/lib/format';
import { formatDateIso } from '@brio/shared';
import { formatNotificationDate } from '@/notifications';

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

/**
 * LE FIL « SUR PLACE » SE LIT AUSSI LE LENDEMAIN.
 *
 * Relevé dans l'émulateur : une mission démarrée le 18 août à 04:32, rouverte le 21 à 03 h 40,
 * affichait « 04:32 » pour son départ, « figée depuis 05:02 » pour sa liste et « fin estimée vers
 * 06:16 ». Trois heures À VENIR dans la journée en cours, pour une intervention vieille de trois
 * jours. Rien n'échouait : seule la lecture de l'écran pouvait le voir.
 */
describe('formatHeureDuFil', () => {
  const maintenant = new Date(2026, 7, 21, 3, 40);

  it('garde l’heure nue le jour même', () => {
    // TÉMOIN POSITIF : sans lui, un test qui exige la date passerait au vert alors que la fonction
    // daterait TOUT, y compris le cas courant qu'on cherche justement à ne pas alourdir.
    const memeJour = new Date(2026, 7, 21, 14, 5);

    expect(formatHeureDuFil(memeJour.toISOString(), maintenant)).toBe('14:05');
  });

  it('date l’heure dès qu’elle sort du jour même', () => {
    const avantHier = new Date(2026, 7, 18, 4, 32);

    expect(formatHeureDuFil(avantHier.toISOString(), maintenant)).toBe('18 août à 04:32');
  });

  it('rend un tiret plutôt qu’une date impossible', () => {
    expect(formatHeureDuFil(null, maintenant)).toBe('—');
    expect(formatHeureDuFil('pas une date', maintenant)).toBe('—');
  });
});

/**
 * LA GOUTTIÈRE DU FIL FAIT 52 PX, et la forme de phrase n'y tenait pas.
 *
 * Relevé à l'écran juste après la première correction : « 18 août à 04:32 » s'y coupait après le
 * « à », si bien que la ligne se lisait « 18 août à En route » avec l'heure reléguée en dessous.
 */
describe('formatHeureDuFilCompacte', () => {
  const maintenant = new Date(2026, 7, 21, 3, 40);

  it('garde l’heure nue le jour même, comme la gouttière l’a toujours affichée', () => {
    // TÉMOIN POSITIF : c'est la forme d'origine, celle qui ne doit RIEN changer au cas courant.
    expect(formatHeureDuFilCompacte(new Date(2026, 7, 21, 14, 5).toISOString(), maintenant))
      .toBe('14:05');
  });

  it('empile date et heure, sans mot de liaison qui puisse se couper', () => {
    const rendu = formatHeureDuFilCompacte(new Date(2026, 7, 18, 4, 32).toISOString(), maintenant);

    expect(rendu).toBe('18/08\n04:32');
    expect(rendu).not.toContain(' à ');
  });

  it('rend un tiret plutôt qu’une date impossible', () => {
    expect(formatHeureDuFilCompacte(null, maintenant)).toBe('—');
  });
});

/**
 * LE FIL DE NOTIFICATIONS PARLAIT AMÉRICAIN.
 *
 * `toLocaleDateString()` sans locale suit la langue de l'appareil : sur l'émulateur réglé en
 * anglais, le 18 août s'affichait « 8/18/2026 ». Mois et jour inversés, dans une application
 * française — et invisible depuis un poste réglé en français, ce qui explique la longévité.
 */
describe('formatDateIso', () => {
  const leJour = new Date(2026, 7, 18, 4, 32).toISOString();

  it('écrit la date en français, quelle que soit la langue de l’appareil', () => {
    expect(formatDateIso(leJour)).toBe('18 août 2026');
  });

  it('ajoute l’heure quand on la demande', () => {
    expect(formatDateIso(leJour, true)).toBe('18 août 2026 à 04:32');
  });

  it('rend une chaîne vide plutôt qu’une date impossible', () => {
    expect(formatDateIso(null)).toBe('');
    expect(formatDateIso('pas une date')).toBe('');
  });

  it('est bien la règle utilisée par le fil de notifications', () => {
    // Les deux applications passent par `formatNotificationDate` : c'est CE point d'appel qui
    // affichait « 8/18/2026 ». Le vérifier ici évite qu'il reparte de son côté.
    expect(formatNotificationDate(leJour)).toBe('18 août 2026');
  });
});
