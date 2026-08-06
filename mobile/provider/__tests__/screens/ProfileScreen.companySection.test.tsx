import React from 'react';
import { render } from '@testing-library/react-native';

import { ProfileScreen } from '@/screens/ProfileScreen';

/**
 * LA SECTION « ESPACE SOCIÉTÉ » NE POUVAIT JAMAIS S'AFFICHER.
 *
 * La condition d'origine exigeait `is_entreprise === true` ET
 * `organization_type === 'provider_company'`. Or, côté serveur, `User::isEntreprise()` retourne
 * `isClientCompany()` : le drapeau désigne une société CLIENTE, pas l'appartenance à une société.
 *
 * Les deux conditions étaient donc mutuellement exclusives — être une société cliente ET
 * prestataire — et les cinq écrans natifs restaient invisibles pour tout le monde. Le nom
 * `is_entreprise` trompe sur ce qu'il désigne ; c'est ce qui m'a induit en erreur.
 *
 * Aucun test ne couvrait cette condition : les tests d'écran vérifiaient le rendu de chaque module
 * société, jamais la porte qui y mène. Ceux-ci la figent, dans les deux sens.
 */

const mockUser: { value: Record<string, unknown> | null } = { value: null };

jest.mock('@/auth', () => ({
  useAuth: () => ({ user: mockUser.value, logout: jest.fn() }),
}));

jest.mock('@/admin/useSpacePreference', () => ({
  useSpacePreference: () => ({ clear: jest.fn() }),
}));

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
}));

describe('ProfileScreen — porte de l’espace société', () => {
  it("affiche les écrans société à un membre d'une société PRESTATAIRE", () => {
    mockUser.value = {
      name: 'Chef Bruxelles',
      organization_type: 'provider_company',
      // `is_entreprise` est FAUX pour ces comptes : le serveur ne le met à vrai que pour une
      // société CLIENTE. L'exiger ici rendait la section inatteignable.
      is_entreprise: false,
    };

    const { getByText } = render(<ProfileScreen />);

    getByText('Répartition');
    getByText('Équipes terrain');
    getByText('Canaux');
  });

  it("ne les propose pas à un prestataire indépendant", () => {
    mockUser.value = { name: 'Indépendant', organization_type: null, is_entreprise: false };

    const { queryByText } = render(<ProfileScreen />);

    // Les proposer donnerait des liens qui répondent 403 à qui les ouvre.
    expect(queryByText('Répartition')).toBeNull();
    expect(queryByText('Équipes terrain')).toBeNull();
  });

  it("ne les propose pas à un membre d'une société CLIENTE", () => {
    mockUser.value = {
      name: 'Acheteuse',
      organization_type: 'client_company',
      is_entreprise: true,
    };

    const { queryByText } = render(<ProfileScreen />);

    expect(queryByText('Répartition')).toBeNull();
  });
});
