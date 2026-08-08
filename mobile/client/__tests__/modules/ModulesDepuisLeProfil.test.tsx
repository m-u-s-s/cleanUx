/**
 * LE RÉPERTOIRE DES MODULES DOIT ÊTRE ATTEIGNABLE DEPUIS CHAQUE PROFIL.
 *
 * Ce test PRESSE, il ne lit pas. Ce dépôt a déjà produit un test de joignabilité qui vérifiait
 * qu'une route était DÉCLARÉE — la route l'était, et personne ne pouvait l'atteindre.
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';

notifyManager.setScheduler((callback) => callback());

const mockNavigate = jest.fn();
const mockAuth = { user: null as unknown, logout: jest.fn() };

jest.mock('@/auth', () => ({ useAuth: () => mockAuth }));
jest.mock('@/company/useClientSpacePreference', () => ({
  useClientSpacePreference: () => ({
    space: 'personal',
    isLoading: false,
    choose: jest.fn(),
    clear: jest.fn(),
  }),
}));
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
}));

import { ProfileScreen } from '@/screens/ProfileScreen';
import { CompanyProfileScreen } from '@/screens/company/CompanyProfileScreen';

function monter(composant: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(<QueryClientProvider client={client}>{composant}</QueryClientProvider>);
}

beforeEach(() => {
  mockNavigate.mockClear();
  mockAuth.user = { id: 1, name: 'Cliente', is_entreprise: false };
});

describe('Modules, depuis chaque profil de l’application cliente', () => {
  it('le profil PERSONNEL y mène', () => {
    monter(<ProfileScreen />);

    fireEvent.press(screen.getByText('Modules'));

    expect(mockNavigate).toHaveBeenCalledWith('Modules');
  });

  it('le profil SOCIÉTÉ y mène', () => {
    mockAuth.user = { id: 2, name: 'Responsable', is_entreprise: true };

    monter(<CompanyProfileScreen />);

    fireEvent.press(screen.getByText('Modules'));

    expect(mockNavigate).toHaveBeenCalledWith('Modules');
  });

  it('la route Modules est montée dans les DEUX espaces', () => {
    // Un `navigate('Modules')` vers une route absente ne lève rien : il ne fait RIEN.
    const fs = require('fs');
    const path = require('path');
    const navigateur = fs.readFileSync(
      path.join(__dirname, '..', '..', 'src', 'navigation', 'RootNavigator.tsx'),
      'utf8',
    );

    expect(navigateur.match(/name="Modules"/g)?.length).toBe(2);
  });
});
