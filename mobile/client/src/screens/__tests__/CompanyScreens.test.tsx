import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import { CompanyOverviewScreen } from '../company/CompanyOverviewScreen';
import { CompanySitesScreen } from '../company/CompanySitesScreen';
import { CompanyBookingsScreen } from '../company/CompanyBookingsScreen';
import { CompanyMembersScreen } from '../company/CompanyMembersScreen';
import { CompanyContractsScreen } from '../company/CompanyContractsScreen';
import { CompanyBillingScreen } from '../company/CompanyBillingScreen';
import { apiClient } from '@/api';

/**
 * L'ESPACE SOCIÉTÉ CLIENTE, EN NATIF.
 *
 * `config/parity.php` déclarait six modules `entreprise-client` en `mobile => 'webview'` — Accueil,
 * Locaux, Réservations, Membres, Facturation, Contrats. L'application n'en servait AUCUN : ni écran
 * natif, ni lien WebView, et `ModuleHubScreen`, seule porte générique vers ces modules, monté dans
 * aucun navigateur. Six modules déclarés, zéro joignable.
 *
 * Ces tests figent ce que chaque écran demande au serveur et ce qu'il rend visible : un écran natif
 * qui interroge la mauvaise route est un écran vide, sans erreur.
 *
 * La JOIGNABILITÉ, elle, ne se prouve pas ici — ces tests montent les écrans directement, comme
 * leurs équivalents prestataire qui n'avaient rien vu. Elle est gardée par
 * `CompanyReachability.test.ts` et `ProfileScreen.porteSociete.test.tsx`.
 */

jest.mock('@/api', () => ({
  apiClient: {
    get: jest.fn(),
    post: jest.fn(),
    patch: jest.fn(),
  },
}));

const mockNavigate = jest.fn();
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
}));

const mockGet = apiClient.get as jest.Mock;
const mockPost = apiClient.post as jest.Mock;

function afficher(composant: React.ReactElement) {
  // `retry: false` — sans cela, une requête échouée est rejouée et le test attend pour rien.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(<QueryClientProvider client={client}>{composant}</QueryClientProvider>);
}

beforeEach(() => {
  jest.clearAllMocks();
});

describe('CompanyOverviewScreen', () => {
  const ACCUEIL = {
    data: {
      data: {
        organization: { id: 1, name: 'Facility Corp' },
        kpis: {
          sites_count: 3,
          bookings_active: 2,
          bookings_month: 7,
          pending_approval: 1,
          members_count: 4,
        },
        recent_bookings: [
          { id: 9, reference: null, status: 'confirmed', site: 'Siège Bruxelles', provider: 'Ana', scheduled_at: null, estimated_price: null },
        ],
      },
    },
  };

  it('lit /client/company/overview et affiche les compteurs de la société', async () => {
    mockGet.mockResolvedValue(ACCUEIL);

    const { getByText } = afficher(<CompanyOverviewScreen />);

    await waitFor(() => expect(getByText('Facility Corp')).toBeTruthy());
    expect(mockGet).toHaveBeenCalledWith('/client/company/overview');
    expect(getByText('3')).toBeTruthy();
    expect(getByText('Siège Bruxelles')).toBeTruthy();
  });

  it('mène aux cinq autres écrans — sans quoi ils resteraient orphelins', async () => {
    mockGet.mockResolvedValue(ACCUEIL);

    const { getByText } = afficher(<CompanyOverviewScreen />);
    await waitFor(() => expect(getByText('Facility Corp')).toBeTruthy());

    fireEvent.press(getByText('Mes locaux'));
    expect(mockNavigate).toHaveBeenCalledWith('CompanySites');

    fireEvent.press(getByText('Facturation'));
    expect(mockNavigate).toHaveBeenCalledWith('CompanyBilling');
  });
});

describe('CompanySitesScreen', () => {
  it('liste les locaux renvoyés par /client/company/sites', async () => {
    mockGet.mockResolvedValue({
      data: {
        data: [
          {
            id: 4, name: 'Siège Bruxelles', address: null, city: 'Bruxelles', postal_code: '1000',
            status: 'active', surface_m2: 220, floor_count: 2, contact_name: null,
            contact_phone: null, service_frequency: 'weekly', active_bookings_count: 1,
          },
        ],
      },
    });

    const { getByText } = afficher(<CompanySitesScreen />);

    await waitFor(() => expect(getByText('Siège Bruxelles')).toBeTruthy());
    expect(mockGet).toHaveBeenCalledWith('/client/company/sites');
    expect(getByText(/220 m²/)).toBeTruthy();
  });

  it('crée un local et rafraîchit la liste', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } });
    mockPost.mockResolvedValue({ data: { data: { id: 8, name: 'Entrepôt' } } });

    const { getByTestId, getByText } = afficher(<CompanySitesScreen />);
    await waitFor(() => expect(mockGet).toHaveBeenCalled());

    fireEvent.changeText(getByTestId('champ-nom-local'), 'Entrepôt');
    fireEvent.press(getByText('Ajouter'));

    await waitFor(() =>
      expect(mockPost).toHaveBeenCalledWith('/client/company/sites', { name: 'Entrepôt', city: null }),
    );
  });
});

describe('CompanyBookingsScreen', () => {
  it('lit les réservations de la SOCIÉTÉ, pas celles du compte', async () => {
    mockGet.mockResolvedValue({
      data: {
        data: [
          { id: 12, reference: 'R-12', status: 'confirmed', site: 'Dépôt Nord', provider: 'Ana', scheduled_at: null, estimated_price: 120 },
        ],
      },
    });

    const { getByText } = afficher(<CompanyBookingsScreen />);

    await waitFor(() => expect(getByText('Dépôt Nord')).toBeTruthy());
    // L'onglet Réservations lit `/client/bookings` : une autre liste, une autre requête.
    expect(mockGet).toHaveBeenCalledWith('/client/company/bookings', { params: {} });
  });

  it('transmet le filtre de statut au serveur', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } });

    const { getByText } = afficher(<CompanyBookingsScreen />);
    await waitFor(() => expect(mockGet).toHaveBeenCalled());

    fireEvent.press(getByText('À approuver'));

    await waitFor(() =>
      expect(mockGet).toHaveBeenCalledWith('/client/company/bookings', {
        params: { status: 'pending_approval' },
      }),
    );
  });
});

describe('CompanyMembersScreen', () => {
  it('traduit le rôle plutôt que d’afficher la clé de l’enum', async () => {
    mockGet.mockResolvedValue({
      data: {
        data: [
          { id: 1, user_id: 2, name: 'Camille Dupont', email: 'c@d.fr', role: 'site_manager', status: 'active', joined_at: null },
        ],
      },
    });

    const { getByText } = afficher(<CompanyMembersScreen />);

    await waitFor(() => expect(getByText('Camille Dupont')).toBeTruthy());
    expect(mockGet).toHaveBeenCalledWith('/client/company/members');
    expect(getByText(/Responsable de site/)).toBeTruthy();
  });

  it('affiche un nom de repli quand le compte a été supprimé', async () => {
    mockGet.mockResolvedValue({
      data: {
        data: [{ id: 1, user_id: 2, name: null, email: null, role: 'requester', status: 'active', joined_at: null }],
      },
    });

    const { getByText } = afficher(<CompanyMembersScreen />);

    await waitFor(() => expect(getByText('Compte supprimé')).toBeTruthy());
  });
});

describe('CompanyContractsScreen', () => {
  it('liste les contrats en lecture seule', async () => {
    mockGet.mockResolvedValue({
      data: {
        data: [
          { id: 3, reference: 'CTR-2026-001', status: 'active', provider: 'CleanUx Partner', billing_cycle: 'monthly', effective_from: '2026-01-01', effective_to: null, payment_terms_days: 30 },
        ],
      },
    });

    const { getByText } = afficher(<CompanyContractsScreen />);

    await waitFor(() => expect(getByText('CTR-2026-001')).toBeTruthy());
    expect(mockGet).toHaveBeenCalledWith('/client/company/contracts');
  });
});

describe('CompanyBillingScreen', () => {
  it('affiche de vrais montants, et pas les zéros du stub web', async () => {
    mockGet.mockResolvedValue({
      data: {
        data: {
          summary: { unpaid: 250, total_month: 480, count_total: 2 },
          invoices: [
            { id: 5, invoice_number: 'F-2026-0042', status: 'issued', currency: 'EUR', total_amount: 250, balance_due: 250, issued_at: '2026-08-01', due_at: '2026-08-31' },
          ],
        },
      },
    });

    const { getByText } = afficher(<CompanyBillingScreen />);

    await waitFor(() => expect(getByText('F-2026-0042')).toBeTruthy());
    expect(mockGet).toHaveBeenCalledWith('/client/company/billing');
    expect(getByText('250.00 €')).toBeTruthy();
  });

  it('renvoie vers l’écran de facture EXISTANT plutôt que d’en dupliquer un', async () => {
    mockGet.mockResolvedValue({
      data: {
        data: {
          summary: { unpaid: 0, total_month: 0, count_total: 1 },
          invoices: [
            { id: 5, invoice_number: 'F-2026-0042', status: 'paid', currency: 'EUR', total_amount: 250, balance_due: 0, issued_at: '2026-08-01', due_at: null },
          ],
        },
      },
    });

    const { getByText } = afficher(<CompanyBillingScreen />);
    await waitFor(() => expect(getByText('F-2026-0042')).toBeTruthy());

    fireEvent.press(getByText('Voir'));

    expect(mockNavigate).toHaveBeenCalledWith('InvoiceDetail', { id: 5 });
  });

  it('explique le refus au lieu de rester muet quand le rôle ne permet pas', async () => {
    // L'API répond 403 à un rôle sans `finance.view`. Un écran vide se lirait « aucune facture ».
    mockGet.mockRejectedValue(new Error('403'));

    const { getByText } = afficher(<CompanyBillingScreen />);

    await waitFor(() => expect(getByText('Facturation indisponible')).toBeTruthy());
  });
});
