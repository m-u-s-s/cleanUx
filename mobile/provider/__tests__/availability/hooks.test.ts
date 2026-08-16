import { apiClient } from '@/api';
import MockAdapter from 'axios-mock-adapter';
import { WEEKDAY_LABELS, WEEK_ORDER, hhmm, weekdayLabel } from '@/availability/labels';

jest.mock('@/storage/secureStore');

/**
 * LE CONTRAT DE L'API DE DISPONIBILITÉ.
 *
 * `Api\Provider\AvailabilityController@index` rend `{slots, exceptions}`. L'écran précédent lisait
 * `res.data.data ?? []` — une clé qui n'a jamais existé : le `??` retombait sur l'objet ENTIER,
 * qu'il passait ensuite à une `FlatList`. Résultat : « Aucune disponibilité » affiché à des
 * prestataires qui en avaient. Une lecture fausse qui ne lève jamais.
 */
describe('Contrat /provider/availability', () => {
  let mock: MockAdapter;

  beforeEach(() => { mock = new MockAdapter(apiClient); });
  afterEach(() => mock.restore());

  it('rend deux tableaux, pas une enveloppe `data`', async () => {
    mock.onGet('/provider/availability').reply(200, {
      slots: [{ id: 1, weekday: 1, start_time: '08:00:00', end_time: '17:00:00' }],
      exceptions: [{ id: 2, date: '2026-08-20', exception_type: 'closed' }],
    });

    const res = await apiClient.get('/provider/availability');

    expect(res.data.data).toBeUndefined();
    expect(Array.isArray(res.data.slots)).toBe(true);
    expect(Array.isArray(res.data.exceptions)).toBe(true);
    expect(res.data.slots[0].weekday).toBe(1);
    // `day_of_week` était le nom lu par l'écran ; il n'existe pas.
    expect(res.data.slots[0].day_of_week).toBeUndefined();
  });

  it('crée un créneau sur le bon verbe et le bon chemin', async () => {
    mock.onPost('/provider/availability/slots').reply(201, { ok: true });

    const res = await apiClient.post('/provider/availability/slots', {
      weekday: 3, start_time: '09:00', end_time: '12:00',
    });

    expect(res.status).toBe(201);
    expect(JSON.parse(mock.history.post[0]!.data)).toEqual({
      weekday: 3, start_time: '09:00', end_time: '12:00',
    });
  });

  it('ferme une date par une exception, pas par une suppression de créneau', async () => {
    mock.onPost('/provider/availability/exceptions').reply(201, { ok: true });

    await apiClient.post('/provider/availability/exceptions', {
      date: '2026-08-20', exception_type: 'closed', reason: null,
    });

    expect(mock.history.delete).toHaveLength(0);
    expect(JSON.parse(mock.history.post[0]!.data).exception_type).toBe('closed');
  });
});

/**
 * LA CONVENTION DE JOUR — 0 = DIMANCHE, celle de Carbon côté serveur.
 *
 * L'écran portait `['Lun','Mar',…,'Dim']` indexé directement : les sept jours étaient décalés d'un
 * cran, et le dimanche s'affichait « Lun ». Sept étiquettes plausibles, aucune juste.
 */
describe('Étiquettes de jour', () => {
  it('fait correspondre chaque index à son jour', () => {
    expect(weekdayLabel(0)).toBe('Dimanche');
    expect(weekdayLabel(1)).toBe('Lundi');
    expect(weekdayLabel(6)).toBe('Samedi');
    expect(Object.keys(WEEKDAY_LABELS)).toHaveLength(7);
  });

  /** L'ordre d'AFFICHAGE est lundi-first ; c'est une notion distincte de l'index. */
  it('affiche la semaine du lundi au dimanche', () => {
    expect(WEEK_ORDER).toEqual([1, 2, 3, 4, 5, 6, 0]);
    expect(WEEK_ORDER.map(weekdayLabel)).toEqual([
      'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche',
    ]);
  });

  it('coupe les secondes que personne ne lit', () => {
    expect(hhmm('08:00:00')).toBe('08:00');
    expect(hhmm(null)).toBe('');
  });
});
