/**
 * LE RÉPERTOIRE DES MODULES DOIT ÊTRE ATTEIGNABLE DEPUIS CHAQUE PROFIL.
 *
 * L'application exposait une poignée d'écrans et laissait le reste inatteignable depuis le
 * téléphone. Le web a sa page Modules ; le mobile n'avait rien d'équivalent.
 *
 * CE TEST PRESSE, IL NE LIT PAS. Ce dépôt a déjà produit un test de joignabilité qui vérifiait
 * qu'une route était DÉCLARÉE — la route l'était, et personne ne pouvait l'atteindre. On monte
 * donc l'écran et on appuie.
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';

notifyManager.setScheduler((callback) => callback());

const mockNavigate = jest.fn();
const mockAuth = { user: null as unknown, logout: jest.fn() };

jest.mock('@/auth', () => ({ useAuth: () => mockAuth }));
jest.mock('@/admin/useSpacePreference', () => ({
  useSpacePreference: () => ({ space: 'provider', isLoading: false, choose: jest.fn(), clear: jest.fn() }),
}));
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
}));

import { ProfileScreen } from '@/screens/ProfileScreen';
import { AdminProfileScreen } from '@/admin/AdminProfileScreen';
import { CompanyProfileScreen } from '@/screens/company/CompanyProfileScreen';

function monter(composant: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(<QueryClientProvider client={client}>{composant}</QueryClientProvider>);
}

beforeEach(() => {
  mockNavigate.mockClear();
  mockAuth.user = { id: 1, name: 'Test', is_provider: true, is_admin: false };
});

describe('Modules, depuis chaque profil de l’application prestataire', () => {
  it('le profil TERRAIN y mène', () => {
    monter(<ProfileScreen />);

    fireEvent.press(screen.getByText('Modules'));

    expect(mockNavigate).toHaveBeenCalledWith('Modules');
  });

  it('le profil ADMINISTRATION y mène', () => {
    mockAuth.user = { id: 2, name: 'Admin', is_admin: true };

    monter(<AdminProfileScreen />);

    fireEvent.press(screen.getByText('Modules'));

    expect(mockNavigate).toHaveBeenCalledWith('Modules');
  });

  it('le profil SOCIÉTÉ y mène', () => {
    mockAuth.user = { id: 3, name: 'Gérante', is_provider: true, can_manage_company: true };

    monter(<CompanyProfileScreen />);

    fireEvent.press(screen.getByText('Modules'));

    expect(mockNavigate).toHaveBeenCalledWith('Modules');
  });

  it('la route Modules est montée dans la pile racine', () => {
    // Un `navigate('Modules')` vers une route absente ne lève rien : il ne fait RIEN. C'est ce qui
    // rend ce genre de lien mort si discret.
    const fs = require('fs');
    const path = require('path');
    const navigateur = fs.readFileSync(
      path.join(__dirname, '..', '..', 'src', 'navigation', 'RootNavigator.tsx'),
      'utf8',
    );

    expect(navigateur).toContain('name="Modules"');
  });
});
