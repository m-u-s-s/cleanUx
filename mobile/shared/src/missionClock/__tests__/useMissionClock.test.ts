import { renderHook } from '@testing-library/react-native';
import { useMissionClock, formatChronometre, formatDureeCourte } from '../useMissionClock';
import type { MissionClock } from '../types';

/**
 * LE COMPTEUR — et surtout la correction du décalage d'horloge.
 *
 * C'est la seule partie qu'aucune relecture n'attrape : sur le poste de travail comme sur le
 * simulateur, l'horloge est juste, et le compteur paraît parfait. Il ne se casse que sur
 * l'appareil d'une personne qui a réglé son heure à la main — ce qui est banal, et volontaire
 * quand le compteur surveille son porteur.
 */
describe('useMissionClock', () => {
  const T0 = Date.parse('2026-08-16T10:00:00.000Z');

  beforeEach(() => {
    jest.useFakeTimers();
    jest.setSystemTime(T0);
  });

  afterEach(() => jest.useRealTimers());

  /** Une horloge serveur cohérente : la mission a démarré il y a `ecouleesMin` minutes. */
  const horloge = (ecouleesMin: number, achetees = 180, extra: Partial<MissionClock> = {}): MissionClock => ({
    applies: true,
    server_now: new Date(T0).toISOString(),
    started_at: new Date(T0 - ecouleesMin * 60_000).toISOString(),
    purchased_minutes: achetees,
    grace_minutes: 15,
    overtime_amount_cents: 0,
    ...extra,
  });

  it('décompte le temps restant avant l’échéance', () => {
    const { result } = renderHook(() => useMissionClock(horloge(100)));

    expect(result.current.applies).toBe(true);
    expect(result.current.phase).toBe('running');
    expect(result.current.remainingSeconds).toBe(80 * 60);
    expect(result.current.progress).toBeCloseTo(100 / 180, 3);
  });

  /**
   * LE CŒUR DU SUJET.
   *
   * L'appareil croit qu'il est 11 h alors qu'il est 10 h. Sans correction, il calculerait 160 min
   * écoulées sur 180 achetées et annoncerait la fin imminente — pour une mission qui vient de
   * passer sa centième minute. Avec un décalage inverse, il masquerait un dépassement réel.
   */
  it('corrige une horloge d’appareil réglée en avance', () => {
    jest.setSystemTime(T0 + 60 * 60_000);

    const { result } = renderHook(() => useMissionClock(horloge(100)));

    expect(Math.round(result.current.elapsedSeconds / 60)).toBe(100);
    expect(result.current.phase).toBe('running');
  });

  it('corrige une horloge d’appareil réglée en retard', () => {
    jest.setSystemTime(T0 - 45 * 60_000);

    const { result } = renderHook(() => useMissionClock(horloge(190)));

    expect(Math.round(result.current.elapsedSeconds / 60)).toBe(190);
    expect(result.current.remainingSeconds).toBeLessThan(0);
  });

  it('passe en « ending » dans le dernier quart d’heure', () => {
    const { result } = renderHook(() => useMissionClock(horloge(170)));

    expect(result.current.phase).toBe('ending');
  });

  /** Le temps est écoulé mais la franchise court : rien n'est encore facturé, donc pas de rouge. */
  it('distingue la franchise du dépassement facturé', () => {
    const enFranchise = renderHook(() => useMissionClock(horloge(188)));

    expect(enFranchise.result.current.phase).toBe('grace');
    expect(enFranchise.result.current.graceSeconds).toBe(7 * 60);

    const enDepassement = renderHook(() => useMissionClock(horloge(200)));

    expect(enDepassement.result.current.phase).toBe('overtime');
    expect(enDepassement.result.current.graceSeconds).toBe(0);
  });

  /**
   * L'APPLICATION NE FABRIQUE PAS D'EUROS.
   *
   * Le montant traverse intact. Le jour où un écran multiplierait lui-même un tarif par une durée,
   * il existerait deux montants pour la même mission — et c'est celui de l'appareil que le client
   * aurait lu.
   */
  it('laisse le montant du serveur intact', () => {
    const { result } = renderHook(() =>
      useMissionClock(horloge(240, 180, { overtime_amount_cents: 5704, billable_overtime_minutes: 45 })),
    );

    expect(result.current.server.overtime_amount_cents).toBe(5704);
    expect(result.current.server.billable_overtime_minutes).toBe(45);
  });

  /** TÉMOIN : sans lui, tous les tests ci-dessus passeraient en mesurant une horloge morte. */
  it('reste éteint quand la mission ne se vend pas au temps', () => {
    const { result } = renderHook(() => useMissionClock({ applies: false }));

    expect(result.current.applies).toBe(false);
    expect(result.current.phase).toBe('idle');
  });

  it('reste éteint sans date de démarrage', () => {
    const { result } = renderHook(() =>
      useMissionClock({ applies: true, purchased_minutes: 180, server_now: new Date(T0).toISOString() }),
    );

    expect(result.current.applies).toBe(false);
  });
});

describe('formatage', () => {
  it('rend le chronomètre lisible à la seconde', () => {
    expect(formatChronometre(45)).toBe('00:45');
    expect(formatChronometre(8047)).toBe('2:14:07');
    // Un compteur ne recule pas sous zéro : il afficherait « -1:-1 ».
    expect(formatChronometre(-30)).toBe('00:00');
  });

  it('arrondit les durées longues à la minute', () => {
    expect(formatDureeCourte(45 * 60)).toBe('45 min');
    expect(formatDureeCourte(180 * 60)).toBe('3 h');
    expect(formatDureeCourte(134 * 60)).toBe('2 h 14');
  });
});
