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
import { fireEvent, render, screen } from '@testing-library/react-native';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

const canaux: (string | null)[] = [];

/*
 * PRÉFIXE `mock` OBLIGATOIRE. Babel hisse les appels `jest.mock()` au-dessus des déclarations, et
 * n'autorise leurs fabriques à référencer qu'une variable dont le nom commence par `mock`. Ce dépôt
 * a déjà buté trois fois sur cette règle.
 */
const mockRepondre = jest.fn();
const mockProlonger = jest.fn();

const etat: {
  fil: unknown;
  photos: unknown;
  imprevus: unknown;
  supplements: unknown;
} = { fil: null, photos: null, imprevus: [], supplements: [] };

jest.mock('@/booking/onsite', () => ({
  useOnSiteTimeline: () => ({ data: etat.fil, isLoading: false }),
  useOnSiteMedia: () => ({ data: etat.photos }),
  useOnSiteIncidents: () => ({ data: etat.imprevus }),
  useOnSiteExtras: () => ({ data: etat.supplements }),
  useRepondreAuSupplement: () => ({ mutate: mockRepondre, isPending: false }),
  /*
    UN BOUCHON DE MODULE DOIT SUIVRE SON MODULE.

    `jest.mock` d'un module entier remplace TOUT ce qu'il exporte : un hook ajouté au vrai fichier
    et absent d'ici ne vaut pas `undefined` par accident, il n'existe simplement pas — et l'écran
    tombe avec « is not a function ». Neuf tests sont morts d'un coup pour cette ligne manquante,
    alors qu'aucun d'eux ne parle de prolongation.
  */
  useProlongerLesHeures: () => ({ mutate: mockProlonger, isPending: false }),
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
  etat.supplements = [];
  mockRepondre.mockClear();
  mockProlonger.mockClear();
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

  /*
   * LES SUPPLÉMENTS (F12) — la seule chose de cet écran qui ATTEND une réponse.
   *
   * Ce dépôt a un historique d'écrans complets que personne ne pouvait atteindre, et de boutons
   * montés qui n'appelaient rien. On PRESSE donc réellement, et on vérifie ce qui part.
   */
  const supplement = (id: number, awaiting: boolean) => ({
    id,
    label: 'Nettoyage des vitres',
    description: null,
    price_cents: 2500,
    price: 25,
    currency: 'EUR',
    status: awaiting ? 'proposed' : 'approved',
    awaiting_client: awaiting,
    proposed_by: 'Karim',
    proposed_at: '2026-08-11T09:20:00+00:00',
  });

  it('montre le supplément qui attend une réponse, et rassure sur le devis', () => {
    etat.supplements = [supplement(9, true)];

    render(<OnSiteScreen route={route} navigation={{} as never} />);

    expect(screen.getByText('Nettoyage des vitres')).toBeTruthy();
    // La phrase qui lève l'inquiétude doit être là : sans elle, on refuse par précaution.
    expect(screen.getByText(/devis initial ne change pas/)).toBeTruthy();
  });

  it('accepter envoie bien la réponse', () => {
    etat.supplements = [supplement(9, true)];

    render(<OnSiteScreen route={route} navigation={{} as never} />);

    fireEvent.press(screen.getByText('Accepter'));

    expect(mockRepondre).toHaveBeenCalledWith(
      expect.objectContaining({ extraId: 9, accepte: true }),
      expect.anything(),
    );
  });

  it('refuser emprunte le même chemin', () => {
    etat.supplements = [supplement(9, true)];

    render(<OnSiteScreen route={route} navigation={{} as never} />);

    fireEvent.press(screen.getByText('Refuser'));

    expect(mockRepondre).toHaveBeenCalledWith(
      expect.objectContaining({ extraId: 9, accepte: false }),
      expect.anything(),
    );
  });

  it('un supplément déjà répondu ne redemande rien', () => {
    etat.supplements = [supplement(9, false)];

    render(<OnSiteScreen route={route} navigation={{} as never} />);

    // Redemander une réponse déjà donnée ferait douter de ce qu'on a validé.
    expect(screen.queryByText('Accepter')).toBeNull();
  });
  /*
    LE COMPTEUR ET LA PROLONGATION.

    Le bouton n'apparaît QUE si le serveur l'autorise, et il OUVRE un choix au lieu de prolonger
    d'un coup : ce geste engage de l'argent, il ne doit pas se déclencher par un appui distrait sur
    un écran qu'on consulte d'une main.
  */
  const horlogeEnCours = {
    applies: true,
    server_now: '2026-08-11T10:05:00+00:00',
    started_at: '2026-08-11T09:05:00+00:00',
    purchased_minutes: 180,
    grace_minutes: 15,
    deadline_at: '2026-08-11T12:05:00+00:00',
    overtime_amount_cents: 0,
  };

  const prolongationOuverte = {
    allowed: true,
    reason: null,
    max_minutes: 240,
    increment_minutes: 30,
    options: [
      { minutes: 30, label: '30 min', amount_cents: 2925 },
      { minutes: 60, label: '1 h', amount_cents: 5850 },
    ],
  };

  it('le bouton prolonger ouvre un choix chiffre par le serveur', () => {
    etat.fil = { ...filComplet, clock: horlogeEnCours, extension: prolongationOuverte };

    render(<OnSiteScreen route={route} navigation={{} as never} />);

    // Rien n'est ouvert tant qu'on n'a pas appuye : le panneau ne doit pas encombrer le suivi.
    expect(screen.queryByTestId('panneau-prolongation')).toBeNull();

    fireEvent.press(screen.getByTestId('mission-clock-bar-extend'));

    expect(screen.getByTestId('panneau-prolongation')).toBeTruthy();
    // Le montant vient du SERVEUR : l'ecran n'a aucune multiplication a faire.
    expect(screen.getByTestId('prolonger-60')).toBeTruthy();
  });

  it('choisir une duree envoie les minutes, pas un montant', () => {
    etat.fil = { ...filComplet, clock: horlogeEnCours, extension: prolongationOuverte };

    render(<OnSiteScreen route={route} navigation={{} as never} />);

    fireEvent.press(screen.getByTestId('mission-clock-bar-extend'));
    fireEvent.press(screen.getByTestId('prolonger-60'));

    expect(mockProlonger).toHaveBeenCalledWith(60, expect.anything());
  });

  /*
    LE MOTIF DU REFUS SE MONTRE. Un client qui deborde doit comprendre pourquoi il ne peut plus
    prolonger, sinon la ligne majoree sur sa facture arrive sans explication -- et c'est un litige.
  */
  it('quand la prolongation est fermee, le motif est affiche et le bouton absent', () => {
    etat.fil = {
      ...filComplet,
      clock: horlogeEnCours,
      extension: {
        allowed: false,
        reason: 'Le temps supplementaire est deja en cours de facturation.',
        max_minutes: 240,
        increment_minutes: 30,
        options: [],
      },
    };

    render(<OnSiteScreen route={route} navigation={{} as never} />);

    expect(screen.queryByTestId('mission-clock-bar-extend')).toBeNull();
    expect(screen.getByText(/deja en cours de facturation/)).toBeTruthy();
  });

  /* TEMOIN : sur une prestation au forfait, il n'y a ni compteur ni bouton. */
  it('un forfait naffiche aucun compteur', () => {
    etat.fil = { ...filComplet, clock: { applies: false }, extension: null };

    render(<OnSiteScreen route={route} navigation={{} as never} />);

    expect(screen.queryByTestId('mission-clock-bar')).toBeNull();
    expect(screen.queryByTestId('mission-clock-bar-extend')).toBeNull();
  });
});
