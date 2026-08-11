/**
 * PHASE 2 CÔTÉ MOBILE — LE PLANNING (E19), LES ABSENCES (E21), LES HEURES (E20+E22), LE STOCK (E23).
 *
 * CES CINQ MODULES SE CONSULTENT DEBOUT. Un chef d'équipe regarde son planning dans la camionnette,
 * quelqu'un vérifie ce qui reste en stock AVANT de partir, un exécutant pose son congé le soir : à
 * chaque fois, le poste de travail est hors de portée.
 *
 * CE QUE CE FICHIER PROUVE, ET QUE `tsc` NE PROUVE PAS : que les boutons appellent les bons points
 * d'API, et surtout que les droits FILTRENT le rendu. Un écran complet derrière une permission mal
 * choisie est le défaut le plus coûteux de cette application — les cinq premiers écrans société ont
 * passé une livraison entière derrière une condition insatisfiable.
 */
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';

notifyManager.setScheduler((callback) => callback());

const mockAuth = { user: null as unknown, logout: jest.fn() };

const mockGet = jest.fn();
const mockPost = jest.fn();
const mockPatch = jest.fn();
const mockDelete = jest.fn();

jest.mock('@/auth', () => ({
  useAuth: () => mockAuth,
  can: jest.requireActual('../../../shared/src/auth/permissions').can,
}));

jest.mock('@/api', () => ({
  apiClient: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
    patch: (...args: unknown[]) => mockPatch(...args),
    delete: (...args: unknown[]) => mockDelete(...args),
    put: jest.fn(),
  },
}));

import { CompanyPlanningScreen } from '@/screens/company/CompanyPlanningScreen';
import { CompanyTimesheetsScreen } from '@/screens/company/CompanyTimesheetsScreen';
import { CompanyInventoryScreen } from '@/screens/company/CompanyInventoryScreen';

const CRENEAU = {
  id: 12,
  user_id: 9,
  user_name: 'Karim',
  starts_at: '2026-08-17T08:00:00+02:00',
  ends_at: '2026-08-17T17:00:00+02:00',
  status: 'planned',
  is_published: false,
};

const ABSENCE = {
  id: 5,
  user_id: 9,
  user_name: 'Karim',
  type: 'paid',
  starts_on: '2026-08-20',
  ends_on: '2026-08-24',
  status: 'pending',
  reason: null,
  blocks_planning: false,
};

const ARTICLE = {
  id: 3,
  name: 'Sacs poubelle 100 L',
  unit: 'carton',
  quantity: 2,
  reorder_threshold: 5,
  agency_name: 'Dépôt Nord',
  needs_reorder: true,
};

function monter(ecran: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(<QueryClientProvider client={client}>{ecran}</QueryClientProvider>);
}

beforeEach(() => {
  mockPost.mockReset().mockResolvedValue({ data: { data: {} } });
  mockPatch.mockReset().mockResolvedValue({ data: { data: {} } });
  mockDelete.mockReset().mockResolvedValue({ data: { data: {} } });

  mockGet.mockReset().mockImplementation((url: string) => {
    if (url === '/provider/company/shifts') {
      return Promise.resolve({ data: { data: [CRENEAU] } });
    }

    if (url === '/provider/company/leaves') {
      return Promise.resolve({ data: { data: [ABSENCE], meta: { can_manage: true } } });
    }

    if (url === '/provider/company/timesheets') {
      return Promise.resolve({
        data: {
          data: [
            { user_id: 9, name: 'Karim', entries_count: 2, worked_minutes: 300, worked_hours: 5 },
          ],
          pending: [
            {
              id: 31,
              user_id: 9,
              user_name: 'Karim',
              started_at: '2026-08-17T08:00:00+02:00',
              worked_minutes: 180,
              notes: 'GPS coupé au sous-sol.',
            },
          ],
        },
      });
    }

    if (url === '/provider/company/profitability') {
      return Promise.resolve({
        data: {
          data: [
            {
              key: 4,
              missions_count: 2,
              missions_without_timesheet: 1,
              revenue_cents: 30000,
              total_cost_cents: 12000,
              margin_cents: 18000,
            },
          ],
          meta: { missions_without_timesheet: 1, default_hourly_rate_cents: 2200 },
        },
      });
    }

    if (url === '/provider/company/inventory') {
      return Promise.resolve({ data: { data: [ARTICLE], meta: { can_manage: true } } });
    }

    return Promise.resolve({ data: { data: [] } });
  });
});

describe('CompanyPlanningScreen — le planning et les absences', () => {
  it('dit qu’un créneau est un BROUILLON', async () => {
    mockAuth.user = { organization_permissions: ['team.view'] };

    monter(<CompanyPlanningScreen />);

    // Sans ce badge, quelqu'un compterait sur un horaire que personne ne lui a communiqué : un
    // brouillon ne rend PAS assignable.
    expect(await screen.findByTestId('creneau-12')).toBeTruthy();
    expect(screen.getByText('Brouillon')).toBeTruthy();
  });

  it('publier est un geste séparé, réservé à qui gère', async () => {
    mockAuth.user = { organization_permissions: ['team.view', 'team.manage'] };

    monter(<CompanyPlanningScreen />);

    fireEvent.press(await screen.findByTestId('bouton-publier-semaine'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/shifts/publish', {}),
    );
  });

  it('un exécutant ne publie pas le planning', async () => {
    mockAuth.user = { organization_permissions: ['team.view'] };

    monter(<CompanyPlanningScreen />);

    await screen.findByTestId('creneau-12');

    // Publier ENGAGE : c'est ce geste qui rend toute l'équipe assignable.
    expect(screen.queryByTestId('bouton-publier-semaine')).toBeNull();
  });

  it('chacun pose SA propre absence', async () => {
    mockAuth.user = { organization_permissions: ['team.view'] };

    monter(<CompanyPlanningScreen />);

    fireEvent.changeText(await screen.findByTestId('champ-debut-conge'), '2026-09-01');
    fireEvent.changeText(screen.getByTestId('champ-fin-conge'), '2026-09-05');
    fireEvent.press(screen.getByTestId('bouton-poser-absence'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/leaves', {
        starts_on: '2026-09-01',
        ends_on: '2026-09-05',
      }),
    );
  });

  it('seul un responsable tranche une demande', async () => {
    mockAuth.user = { organization_permissions: ['team.view', 'team.manage'] };

    monter(<CompanyPlanningScreen />);

    fireEvent.press(await screen.findByTestId('approuver-absence-5'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/leaves/5/decision', {
        approve: true,
      }),
    );
  });

  it('un exécutant ne voit pas le bloc des décisions', async () => {
    mockAuth.user = { organization_permissions: ['team.view'] };

    monter(<CompanyPlanningScreen />);

    await screen.findByTestId('creneau-12');

    expect(screen.queryByTestId('approuver-absence-5')).toBeNull();
  });
});

describe('CompanyTimesheetsScreen — les heures et la marge', () => {
  it('approuve une correction saisie à la main', async () => {
    mockAuth.user = { organization_permissions: ['team.view', 'team.manage'] };

    monter(<CompanyTimesheetsScreen />);

    fireEvent.press(await screen.findByTestId('approuver-correction-31'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/timesheets/31/decision', {
        approve: true,
      }),
    );
  });

  it('annonce les missions sans pointage plutôt que de les fondre', async () => {
    mockAuth.user = { organization_permissions: ['team.view', 'analytics.view'] };

    monter(<CompanyTimesheetsScreen />);

    /*
     * LES FONDRE DANS LA MOYENNE ferait apparaître une marge de 100 % sur chacune, et un site
     * entier paraîtrait florissant parce que personne n'y a pointé. Une rentabilité flatteuse et
     * fausse est pire que pas de rentabilité.
     */
    expect(
      await screen.findByText(/1 mission\(s\) sans pointage/),
    ).toBeTruthy();
  });

  it('ne demande même pas la marge sans `analytics.view`', async () => {
    mockAuth.user = { organization_permissions: ['team.view'] };

    monter(<CompanyTimesheetsScreen />);

    await screen.findByTestId('heures-9');

    // Demander une donnée qu'on n'a pas le droit de lire produirait un 403 à chaque ouverture — et
    // la marge dit ce que coûte chaque personne.
    expect(mockGet).not.toHaveBeenCalledWith('/provider/company/profitability');
    expect(screen.queryByTestId('marge-4')).toBeNull();
  });
});

describe('CompanyInventoryScreen — le stock', () => {
  it('signale un article sous son seuil', async () => {
    mockAuth.user = { organization_permissions: ['inventory.view'] };

    monter(<CompanyInventoryScreen />);

    // On découvre la rupture le matin du départ, sinon.
    expect(await screen.findByTestId('article-3')).toBeTruthy();
    expect(screen.getByText('Stock bas')).toBeTruthy();
  });

  it('déclare un prélèvement depuis le terrain', async () => {
    mockAuth.user = { organization_permissions: ['inventory.view', 'inventory.manage'] };

    monter(<CompanyInventoryScreen />);

    fireEvent.changeText(await screen.findByTestId('quantite-3'), '2');
    fireEvent.press(screen.getByTestId('prelever-3'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/inventory/3/movements', {
        type: 'consumption',
        quantity: 2,
      }),
    );
  });

  it('voir n’est pas commander', async () => {
    mockAuth.user = { organization_permissions: ['inventory.view'] };

    monter(<CompanyInventoryScreen />);

    await screen.findByTestId('article-3');

    /*
     * `inventory.view` VA JUSQU'AUX EXÉCUTANTS, et c'est délibéré : savoir ce qui reste avant de
     * partir n'est pas commander. Mais le mouvement, lui, reste à `inventory.manage` — sans quoi
     * l'exactitude du stock dépendrait de qui a ouvert l'écran en dernier.
     */
    expect(screen.queryByTestId('prelever-3')).toBeNull();
    expect(screen.queryByTestId('receptionner-3')).toBeNull();
  });
});

describe('Joignabilité', () => {
  it('les trois écrans sont montés ET atteignables depuis le profil', () => {
    const fs = require('fs');
    const path = require('path');
    const src = path.join(__dirname, '..', '..', 'src');
    const lire = (rel: string) => fs.readFileSync(path.join(src, rel), 'utf8');

    const racine = lire('navigation/RootNavigator.tsx');
    const profil = lire('screens/ProfileScreen.tsx');

    for (const ecran of ['CompanyPlanning', 'CompanyTimesheets', 'CompanyInventory']) {
      // Montés dans les DEUX piles — société et terrain — comme `CompanySites` : un gérant qui a
      // choisi « terrain » y accède par son profil, sans quoi l'écran n'existerait que pour la
      // moitié des comptes.
      expect(racine.match(new RegExp(`name="${ecran}"`, 'g'))?.length).toBe(2);
      expect(profil).toContain(ecran);
    }
  });
});
