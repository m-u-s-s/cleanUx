import { useEffect, useMemo, useState } from 'react';
import type { LiveMissionClock, MissionClock, MissionClockPhase } from './types';

/** Les quinze dernières minutes : le moment d'inviter le client à prolonger, pas de le sanctionner. */
const SECONDES_AVANT_LA_FIN = 15 * 60;

const HORLOGE_ETEINTE: LiveMissionClock = {
  applies: false,
  phase: 'idle',
  elapsedSeconds: 0,
  remainingSeconds: 0,
  graceSeconds: 0,
  progress: 0,
  server: { applies: false },
};

/**
 * LE COMPTEUR QUI DÉFILE — et la seule chose que l'appareil ait le droit de calculer.
 *
 * L'ÉCART D'HORLOGE EST CORRIGÉ, et c'est la raison d'être de ce hook. Un téléphone dont l'heure
 * est réglée à la main — cas banal, et cas volontaire quand le compteur surveille son porteur —
 * afficherait sinon un dépassement d'une heure dès la première minute, ou masquerait un
 * dépassement réel. On mesure l'écart entre `server_now` et l'heure de l'appareil à la réception,
 * et on l'applique à chaque battement. Chaque rafraîchissement le recale.
 *
 * LES MONTANTS NE SONT JAMAIS RECALCULÉS ICI. Ils traversent tels quels dans `server`. Entre deux
 * rafraîchissements le montant affiché est donc légèrement en retard sur le compteur : c'est
 * assumé, et infiniment préférable à deux vérités sur ce que le client va payer.
 */
export function useMissionClock(clock: MissionClock | null | undefined): LiveMissionClock {
  const [maintenant, setMaintenant] = useState(() => Date.now());

  const ancre = clock?.server_now;

  /*
   * L'ÉCART SE MESURE PENDANT LE RENDU, pas dans un effet.
   *
   * Mesuré dans un effet, il ne serait appliqué qu'au battement SUIVANT : le premier affichage
   * montrerait la valeur non corrigée. Sur un appareil réglé une heure en avance, cela veut dire
   * un « TEMPS DÉPASSÉ » en rouge affiché pendant une seconde à chaque ouverture de l'écran, sur
   * une mission qui vient de commencer. Une seconde suffit à être lue, photographiée et contestée.
   */
  const ecart = useMemo(() => {
    if (!ancre) return 0;
    const serveur = Date.parse(ancre);

    return Number.isNaN(serveur) ? 0 : serveur - Date.now();
  }, [ancre]);

  const actif = clock?.applies === true && typeof clock.started_at === 'string';

  useEffect(() => {
    if (!actif) return;
    // Une seconde : c'est un chronomètre, il doit battre à la seconde pour se lire comme tel.
    const battement = setInterval(() => setMaintenant(Date.now()), 1000);
    return () => clearInterval(battement);
  }, [actif]);

  return useMemo(() => {
    if (!clock || !actif) return HORLOGE_ETEINTE;

    const debut = Date.parse(clock.started_at as string);
    if (Number.isNaN(debut)) return HORLOGE_ETEINTE;

    const achetees = Math.max(0, clock.purchased_minutes ?? 0);
    const franchise = Math.max(0, clock.grace_minutes ?? 0);

    const corrige = maintenant + ecart;
    const elapsedSeconds = Math.max(0, Math.floor((corrige - debut) / 1000));
    const remainingSeconds = achetees * 60 - elapsedSeconds;
    const graceSeconds = remainingSeconds >= 0 ? 0 : Math.max(0, franchise * 60 + remainingSeconds);

    let phase: MissionClockPhase;
    if (remainingSeconds > SECONDES_AVANT_LA_FIN) {
      phase = 'running';
    } else if (remainingSeconds > 0) {
      phase = 'ending';
    } else if (graceSeconds > 0) {
      phase = 'grace';
    } else {
      phase = 'overtime';
    }

    return {
      applies: true,
      phase,
      elapsedSeconds,
      remainingSeconds,
      graceSeconds,
      progress: achetees > 0 ? Math.min(1, elapsedSeconds / (achetees * 60)) : 0,
      server: clock,
    };
  }, [clock, actif, maintenant, ecart]);
}

/**
 * Une durée en secondes rendue lisible : « 2 h 14 » au-delà de l'heure, « 46 min » en dessous.
 *
 * On ne descend PAS à la seconde dans le libellé, alors que le compteur bat à la seconde. Un
 * client qui voit ses secondes défiler regarde ses secondes défiler ; le chiffre qui l'intéresse
 * est le nombre de minutes qu'il lui reste.
 */
export function formatDureeCourte(secondes: number): string {
  const total = Math.max(0, Math.round(secondes / 60));
  const heures = Math.floor(total / 60);
  const minutes = total % 60;

  if (heures <= 0) return `${minutes} min`;
  if (minutes === 0) return `${heures} h`;

  return `${heures} h ${String(minutes).padStart(2, '0')}`;
}

/** Le chronomètre lui-même, à la seconde : « 2:14:07 ». C'est lui qui donne l'impression du temps. */
export function formatChronometre(secondes: number): string {
  const total = Math.max(0, Math.floor(secondes));
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;

  const mm = String(m).padStart(2, '0');
  const ss = String(s).padStart(2, '0');

  return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
}
