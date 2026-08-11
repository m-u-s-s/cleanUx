/**
 * PHASE 2 CÔTÉ MOBILE — LES DEVIS (E24), LE RECRUTEMENT (E25), LA QUALITÉ ET LA FLOTTE (E26+E27).
 *
 * POURQUOI CES ÉCRANS SONT MOBILES. Un devis se chiffre CHEZ LE CLIENT, pendant la visite : c'est
 * le seul moment où l'on voit la surface, l'état des sols, l'absence d'ascenseur. Le tri des
 * candidatures se fait entre deux chantiers. Une échéance de permis se vérifie avant de partir.
 *
 * CE QUE CE FICHIER PROUVE ET QUE `tsc` NE PROUVE PAS : que les boutons appellent les bons points
 * d'API, et que les droits filtrent le rendu. Un écran complet derrière une permission mal choisie
 * est le défaut le plus coûteux de cette application.
 */
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';

notifyManager.setScheduler((callback) => callback());

const mockAuth = { user: null as unknown, logout: jest.fn() };

const mockGet = jest.fn();
const mockPost = jest.fn();

jest.mock('@/auth', () => ({
  useAuth: () => mockAuth,
  can: jest.requireActual('../../../shared/src/auth/permissions').can,
}));

jest.mock('@/api', () => ({
  apiClient: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
    patch: jest.fn(),
    delete: jest.fn(),
    put: jest.fn(),
  },
}));

import { CompanyQuotesScreen } from '@/screens/company/CompanyQuotesScreen';
import { CompanyRecruitmentScreen } from '@/screens/company/CompanyRecruitmentScreen';
import { CompanyQualityFleetScreen } from '@/screens/company/CompanyQualityFleetScreen';

const DEVIS_BROUILLON = {
  id: 8,
  reference: 'DEV-202608-ABCDEF',
  title: 'Remise en état des communs',
  client_name: 'Résidence Les Tilleuls',
  status: 'draft',
  total_cents: 41000,
  is_open: false,
};

const DEVIS_ENVOYE = { ...DEVIS_BROUILLON, id: 9, status: 'sent', is_open: true };

const OFFRE = {
  id: 4,
  reference: 'JOB-202608-XYZ123',
  title: 'Agent d’entretien (H/F)',
  trade_name: 'Nettoyage',
  status: 'published',
  applications_count: 1,
};

const CANDIDATURE = {
  id: 21,
  full_name: 'Nadia B.',
  email: 'nadia@exemple.test',
  phone: null,
  status: 'received',
  invited: false,
};

function monter(ecran: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(<QueryClientProvider client={client}>{ecran}</QueryClientProvider>);
}

beforeEach(() => {
  mockPost.mockReset().mockResolvedValue({ data: { data: {} } });

  mockGet.mockReset().mockImplementation((url: string) => {
    if (url === '/provider/company/quotes') {
      return Promise.resolve({ data: { data: [DEVIS_BROUILLON, DEVIS_ENVOYE] } });
    }

    if (url === '/provider/company/job-postings') {
      return Promise.resolve({ data: { data: [OFFRE] } });
    }

    if (url === '/provider/company/job-postings/4/applications') {
      return Promise.resolve({ data: { data: [CANDIDATURE] } });
    }

    if (url === '/provider/company/quality-scores') {
      return Promise.resolve({
        data: {
          data: [
            {
              user_id: 9,
              name: 'Karim',
              missions_count: 12,
              has_enough_data: true,
              inspection_score: 88,
              satisfaction_score: 92,
              punctuality_score: 55,
              score: 82.1,
            },
            {
              user_id: 10,
              name: 'Nouvelle recrue',
              missions_count: 1,
              has_enough_data: false,
              inspection_score: null,
              satisfaction_score: null,
              punctuality_score: null,
              score: null,
            },
          ],
          meta: { missions_minimum: 3 },
        },
      });
    }

    if (url === '/provider/company/fleet') {
      return Promise.resolve({
        data: {
          vehicles: [
            {
              id: 3,
              plate: '1-ABC-123',
              brand: 'Renault',
              model: 'Kangoo',
              status: 'available',
              current_provider_name: null,
            },
          ],
          equipment: [],
          expiring: [
            {
              id: 7,
              certification_type: 'controle_technique',
              subject_type: 'vehicle',
              subject_id: 3,
              expires_at: '2026-08-21',
            },
          ],
          meta: { can_manage: true, notice_days: 30 },
        },
      });
    }

    return Promise.resolve({ data: { data: [] } });
  });
});

describe('CompanyQuotesScreen — les devis', () => {
  it('chiffre depuis la visite', async () => {
    mockAuth.user = { organization_permissions: ['quotes.view', 'quotes.manage'] };

    monter(<CompanyQuotesScreen />);

    fireEvent.changeText(await screen.findByTestId('champ-titre-devis'), 'Visite du 12');
    fireEvent.press(screen.getByTestId('bouton-creer-devis'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/quotes', { title: 'Visite du 12' }),
    );
  });

  it('n’offre d’envoyer QUE le brouillon', async () => {
    mockAuth.user = { organization_permissions: ['quotes.view', 'quotes.manage'] };

    monter(<CompanyQuotesScreen />);

    // Un devis envoyé ne se modifie plus : le corriger après coup ferait diverger ce que le client
    // a reçu de ce qu'il accepte.
    expect(await screen.findByTestId('envoyer-devis-8')).toBeTruthy();
    expect(screen.queryByTestId('envoyer-devis-9')).toBeNull();
  });

  it('un lecteur ne chiffre pas', async () => {
    mockAuth.user = { organization_permissions: ['quotes.view'] };

    monter(<CompanyQuotesScreen />);

    await screen.findByTestId('devis-8');

    expect(screen.queryByTestId('champ-titre-devis')).toBeNull();
    expect(screen.queryByTestId('envoyer-devis-8')).toBeNull();
  });
});

describe('CompanyRecruitmentScreen — le recrutement', () => {
  it('embaucher émet l’invitation', async () => {
    mockAuth.user = { organization_permissions: ['recruitment.view', 'recruitment.manage'] };

    monter(<CompanyRecruitmentScreen />);

    fireEvent.press(await screen.findByTestId('ouvrir-offre-4'));
    fireEvent.press(await screen.findByTestId('embaucher-21'));

    /*
     * UN MÊME GESTE, PAS DEUX ÉCRANS. Séparer les deux produirait le défaut qu'on répare : une
     * candidature marquée « embauché » et personne dans l'organigramme.
     */
    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/provider/company/job-applications/21/decision', {
        decision: 'hire',
      }),
    );
  });

  it('un lecteur ne tranche pas', async () => {
    mockAuth.user = { organization_permissions: ['recruitment.view'] };

    monter(<CompanyRecruitmentScreen />);

    fireEvent.press(await screen.findByTestId('ouvrir-offre-4'));
    await screen.findByTestId('candidature-21');

    expect(screen.queryByTestId('embaucher-21')).toBeNull();
  });
});

describe('CompanyQualityFleetScreen — qualité et flotte', () => {
  it('ne fabrique pas un score sans matière', async () => {
    mockAuth.user = { organization_permissions: ['missions.quality'] };

    monter(<CompanyQualityFleetScreen />);

    // Une moyenne sur une mission serait lue comme un jugement.
    expect(await screen.findByTestId('score-10')).toBeTruthy();
    expect(screen.getByText('Pas assez de données')).toBeTruthy();
    expect(screen.getByText('82.1 %')).toBeTruthy();
  });

  it('annonce l’échéance avant que le moteur ne refuse', async () => {
    mockAuth.user = { organization_permissions: ['fleet.view'] };

    monter(<CompanyQualityFleetScreen />);

    // Découvrir l'expiration au moment où l'assignation est refusée, c'est la découvrir trop tard.
    expect(await screen.findByTestId('echeances')).toBeTruthy();
    expect(screen.getByTestId('vehicule-3')).toBeTruthy();
  });

  it('ne demande pas ce qu’on n’a pas le droit de lire', async () => {
    mockAuth.user = { organization_permissions: ['fleet.view'] };

    monter(<CompanyQualityFleetScreen />);

    await screen.findByTestId('vehicule-3');

    // Demander une donnée refusée produirait un 403 à chaque ouverture de l'écran.
    expect(mockGet).not.toHaveBeenCalledWith('/provider/company/quality-scores');
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

    for (const ecran of ['CompanyQuotes', 'CompanyRecruitment', 'CompanyQualityFleet']) {
      // Montés dans les DEUX piles — société et terrain : un gérant qui a choisi « terrain » y
      // accède par son profil, sans quoi l'écran n'existerait que pour la moitié des comptes.
      expect(racine.match(new RegExp(`name="${ecran}"`, 'g'))?.length).toBe(2);
      expect(profil).toContain(ecran);
    }
  });
});
