/**
 * L'ÉCRAN « INTERVENTION EN COURS » — ce que le client voit pendant les deux heures.
 *
 * Trois choses s'y jouent, et chacune a déjà été manquée ailleurs dans ce dépôt :
 *
 *  - une réservation SANS mission n'est pas une erreur : le suivi ouvert avant l'heure doit dire
 *    « pas encore commencé », pas « introuvable » ;
 *  - le comparateur avant/après n'affiche que ce qui existe — une bande vide avec son titre se
 *    lirait comme un chargement qui ne finit jamais ;
 *  - l'abonnement temps réel porte le numéro de MISSION, celui que rend le fil, jamais celui de la
 *    réservation.
 */
import React from 'react';
import { render, screen } from '@testing-library/react-native';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

const canaux: (string | null)[] = [];

const etat: {
  fil: unknown;
  photos: unknown;
  imprevus: unknown;
} = { fil: null, photos: null, imprevus: [] };

jest.mock('@/booking/onsite', () => ({
  useOnSiteTimeline: () => ({ data: etat.fil, isLoading: false }),
  useOnSiteMedia: () => ({ data: etat.photos }),
  useOnSiteIncidents: () => ({ data: etat.imprevus }),
  useLiveOnSite: (_bookingId: number | null, missionId: number | null) => {
    canaux.push(missionId === null ? null : `private-mission.${missionId}`);
  },
}));

import { OnSiteScreen } from '@/screens/OnSiteScreen';

const route = { params: { bookingId: 77 } } as never;

const filComplet = {
  mission_id: 4242,
  status: 'started',
  started_at: '2026-08-11T09:05:00+00:00',
  estimated_end_at: '2026-08-11T11:05:00+00:00',
  progress: { done: 2, total: 5, percent: 40 },
  entries: [
    { kind: 'milestone', key: 'm1', label: 'Arrivé sur place', at: '2026-08-11T09:00:00+00:00' },
    { kind: 'checklist', key: 'c1', label: 'Vitres nettoyées', at: '2026-08-11T09:40:00+00:00' },
  ],
};

const photo = (id: number, type: string, label: string) => ({
  id,
  type,
  label,
  caption: null,
  url: 'https://example.test/p.jpg',
  taken_at: '2026-08-11T09:10:00+00:00',
  fingerprint: 'abc123',
});

beforeEach(() => {
  canaux.length = 0;
  etat.fil = filComplet;
  etat.photos = { before: [], after: [], incident: [] };
  etat.imprevus = [];
});

describe('OnSiteScreen', () => {
  it('affiche le déroulé et l’heure de fin estimée', () => {
    render(<OnSiteScreen route={route} navigation={{} as never} />);

    expect(screen.getByText('Arrivé sur place')).toBeTruthy();
    expect(screen.getByText('Vitres nettoyées')).toBeTruthy();
    expect(screen.getByText(/Fin estimée vers/)).toBeTruthy();
  });

  it('écoute le canal de la mission rendue par le fil', () => {
    render(<OnSiteScreen route={route} navigation={{} as never} />);

    expect(canaux).toContain('private-mission.4242');
  });

  it('dit que rien n’a commencé plutôt que d’afficher une erreur', () => {
    etat.fil = { ...filComplet, mission_id: null, entries: [] };

    render(<OnSiteScreen route={route} navigation={{} as never} />);

    expect(screen.getByText('L’intervention n’a pas encore commencé')).toBeTruthy();
  });

  it('n’affiche le comparateur que lorsqu’il y a des photos', () => {
    render(<OnSiteScreen route={route} navigation={{} as never} />);

    expect(screen.queryByText('Avant / après')).toBeNull();

    etat.photos = { before: [photo(1, 'before_photo', 'Photo avant')], after: [], incident: [] };
    screen.rerender(<OnSiteScreen route={route} navigation={{} as never} />);

    expect(screen.getByText('Avant / après')).toBeTruthy();
    expect(screen.getByText('Avant')).toBeTruthy();
    // Pas de bande « Après » vide : elle se lirait comme un chargement sans fin.
    expect(screen.queryByText('Après')).toBeNull();
  });

  it('met les imprévus signalés en tête, avant les photos', () => {
    etat.imprevus = [
      {
        id: 3,
        type: 'access_impossible',
        label: 'Accès impossible',
        severity: 'high',
        status: 'open',
        description: 'Portail fermé, personne ne répond.',
        reported_at: '2026-08-11T09:02:00+00:00',
        photo: null,
        dispute_prefill: { category: 'access', subject: '', description: '' },
      },
    ];

    render(<OnSiteScreen route={route} navigation={{} as never} />);

    expect(screen.getByText('Accès impossible')).toBeTruthy();
    expect(screen.getByText('Portail fermé, personne ne répond.')).toBeTruthy();
  });
});
