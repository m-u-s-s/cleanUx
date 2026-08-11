/**
 * PHASE 3 CÔTÉ MOBILE — LE CARNET DE LIEUX (E2), LE BUDGET (E4) ET « MA PROTECTION » (E6).
 *
 * POURQUOI CES TROIS SUR UN TÉLÉPHONE. C'est SUR PLACE qu'on note le digicode qu'on vient de
 * composer, l'étage, la clé chez la voisine du deuxième. Le budget se regarde en recevant une
 * facture. La protection se consulte au pire moment : quand quelque chose vient de se casser, et
 * qu'on n'est pas devant un ordinateur.
 *
 * CE QUE CE FICHIER PROUVE ET QUE `tsc` NE PROUVE PAS : que les boutons appellent les bons points
 * d'API, et que les trois écrans sont ATTEIGNABLES. Un écran monté sans porte d'entrée est le mode
 * d'échec documenté de ce dépôt.
 */
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

notifyManager.setScheduler((callback) => callback());

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true, isInternetReachable: true }),
  default: {
    addEventListener: jest.fn(() => () => undefined),
    fetch: jest.fn().mockResolvedValue({ isConnected: true, isInternetReachable: true }),
  },
}));

import { apiClient } from '@/api';
import { PlacesScreen } from '../PlacesScreen';
import { BudgetScreen } from '../BudgetScreen';
import { ProtectionScreen } from '../ProtectionScreen';

const mock = new MockAdapter(apiClient);

const LIEU = {
  id: 4,
  label: 'Chez moi',
  address: 'Rue Haute 1',
  city: 'Bruxelles',
  postal_code: '1000',
  floor: '3e étage, porte gauche',
  access_instructions: 'Digicode 4512B.',
  alarm_code_required: true,
  is_default: true,
};

function monter(ecran: React.ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } },
  });

  return render(<QueryClientProvider client={client}>{ecran}</QueryClientProvider>);
}

beforeEach(() => {
  mock.reset();

  mock.onGet('/client/places').reply(200, { data: [LIEU], meta: { maximum: 25 } });
  mock.onPost('/client/places').reply(201, { data: { ...LIEU, id: 5 } });
  mock.onDelete(/\/client\/places\/\d+/).reply(200, { data: { archived: true } });

  mock.onGet(/\/client\/budget/).reply(200, {
    data: {
      bookings_count: 4,
      total_cents: 48000,
      monthly_average_cents: 12000,
      by_month: [{ month: '2026-07', bookings_count: 2, total_cents: 24000 }],
      by_trade: [{ trade: 'Nettoyage', bookings_count: 4, total_cents: 48000 }],
      subscription_vs_on_demand: {
        subscription: { bookings_count: 0, total_cents: 0, average_cents: 0 },
        on_demand: { bookings_count: 4, total_cents: 48000, average_cents: 12000 },
      },
    },
  });

  mock.onGet('/client/protection').reply(200, {
    data: {
      insurance: {
        active_count: 1,
        total_coverage_cents: 500000,
        policies: [
          {
            id: 9,
            policy_number: 'POL-123',
            coverage_amount_cents: 500000,
            effective_until: '2026-12-31',
            booking_reference: 'BK-2026-001',
          },
        ],
      },
      cancellation: {
        upcoming_count: 1,
        quotes: [
          {
            booking_id: 12,
            booking_reference: 'BK-2026-002',
            scheduled_at: '2026-08-20T09:00:00+02:00',
            hours_before: 40,
            policy: { fee_cents: 0 },
          },
        ],
      },
      disputes: { open_count: 0, cases: [] },
    },
  });
});

describe('PlacesScreen — le carnet de lieux', () => {
  it('enregistre un lieu avec ses consignes', async () => {
    monter(<PlacesScreen />);

    fireEvent.changeText(await screen.findByTestId('champ-libelle-lieu'), 'Maison de maman');
    fireEvent.changeText(screen.getByTestId('champ-adresse-lieu'), 'Chaussée de Wavre 200');
    fireEvent.changeText(screen.getByTestId('champ-consignes-lieu'), 'Sonner deux fois.');
    fireEvent.press(screen.getByTestId('bouton-ajouter-lieu'));

    /*
     * CE QUI COMPTE N'EST PAS L'ADRESSE, ce sont les CONSIGNES : elles se redonnaient oralement à
     * chaque nouveau prestataire, ou se perdaient.
     */
    await waitFor(() => {
      const envoi = mock.history.post.find((r) => r.url === '/client/places');
      expect(envoi).toBeDefined();
      expect(JSON.parse(envoi!.data).access_instructions).toBe('Sonner deux fois.');
    });
  });

  it('montre les consignes du lieu par défaut', async () => {
    monter(<PlacesScreen />);

    expect(await screen.findByTestId('lieu-4')).toBeTruthy();
    expect(screen.getByText('Par défaut')).toBeTruthy();
  });

  it('archive plutôt que de supprimer', async () => {
    monter(<PlacesScreen />);

    fireEvent.press(await screen.findByTestId('archiver-lieu-4'));

    // Les interventions passées portent ce lieu : l'effacer viderait l'historique de ses adresses.
    await waitFor(() => expect(mock.history.delete.length).toBe(1));
  });
});

describe('BudgetScreen — le budget entretien', () => {
  it('dit quand il n’y a aucun abonnement', async () => {
    monter(<BudgetScreen />);

    // « Vous n'avez aucun abonnement » est une réponse utile ; un bloc absent ne l'est pas.
    expect(await screen.findByTestId('comparatif')).toBeTruthy();
    expect(
      screen.getByText(/aucune intervention récurrente/i),
    ).toBeTruthy();
  });

  it('ventile par métier', async () => {
    monter(<BudgetScreen />);

    expect(await screen.findByTestId('metier-Nettoyage')).toBeTruthy();
  });
});

describe('ProtectionScreen — ma protection', () => {
  it('affiche les trois blocs, même vides', async () => {
    monter(<ProtectionScreen />);

    /*
     * UN ÉCRAN DONT LES BLOCS APPARAISSENT ET DISPARAISSENT selon ce qu'on possède fait douter de
     * ce qui manque — exactement ce qu'une page de protection doit éviter.
     */
    expect(await screen.findByTestId('assurance')).toBeTruthy();
    expect(screen.getByTestId('annulation')).toBeTruthy();
    expect(screen.getByTestId('reclamations')).toBeTruthy();
  });

  it('annonce une annulation sans frais', async () => {
    monter(<ProtectionScreen />);

    expect(await screen.findByTestId('annulation-12')).toBeTruthy();
    expect(screen.getByText('Sans frais')).toBeTruthy();
  });
});

describe('Joignabilité', () => {
  it('les trois écrans sont montés ET atteignables depuis le profil', () => {
    const fs = require('fs');
    const path = require('path');
    const src = path.join(__dirname, '..', '..');
    const lire = (rel: string) => fs.readFileSync(path.join(src, rel), 'utf8');

    const racine = lire('navigation/RootNavigator.tsx');
    const profil = lire('screens/ProfileScreen.tsx');

    for (const ecran of ['Places', 'Budget', 'Protection']) {
      // Déclarer n'est pas rendre joignable : `tsc` et Jest ne disent rien de la joignabilité d'un
      // écran, et c'est le mode d'échec documenté de ce dépôt.
      expect(racine).toContain(`name="${ecran}"`);
      expect(profil).toContain(`navigate('${ecran}')`);
    }
  });
});
