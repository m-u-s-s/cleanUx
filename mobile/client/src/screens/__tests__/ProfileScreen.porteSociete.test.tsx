import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';

/**
 * LA PORTE VERS L'ESPACE SOCIÉTÉ CLIENTE.
 *
 * Elle se teste ici et pas seulement dans `CompanyReachability.test.ts` : celui-ci LIT le fichier,
 * celui-là le RÉSOUT. Un `is_entreprise` présent dans la source ne prouve pas que le bouton
 * apparaisse au bon compte — c'est exactement ce qui manquait côté prestataire, où la condition
 * était écrite, lisible, et fausse.
 */
const mockNavigate = jest.fn();
const mockAuth = { user: null as unknown, logout: jest.fn() };
const mockClearSpace = jest.fn();

jest.mock('@/auth', () => ({ useAuth: () => mockAuth }));
jest.mock('@/company/useClientSpacePreference', () => ({
  useClientSpacePreference: () => ({
    clear: mockClearSpace,
    space: 'clientCompany',
    isLoading: false,
    choose: jest.fn(),
  }),
}));
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
}));

import { ProfileScreen } from '../ProfileScreen';

/*
 * LE LIBELLÉ A CHANGÉ AVEC LA NATURE DE LA PORTE.
 *
 * L'espace société était une destination parmi d'autres — « Espace entreprise » poussait un écran
 * sur la pile personnelle. C'est devenu un ESPACE : sa propre pile, ses propres onglets, choisi au
 * démarrage et retenu. Le geste n'est donc plus « aller à » mais « changer d'espace », et le
 * bouton doit dire ce qu'il fait.
 */
const LIBELLE = 'Changer d’espace';

beforeEach(() => {
  mockNavigate.mockClear();
  mockClearSpace.mockClear();
});

describe('ProfileScreen — porte vers l’espace société', () => {
  it('propose l’espace à un membre de société cliente', () => {
    mockAuth.user = { is_entreprise: true, organization_type: 'client_company' };

    render(<ProfileScreen />);

    expect(screen.getByText(LIBELLE)).toBeTruthy();
  });

  it('le propose aussi à une organisation hybride, que `isClientCompany` reconnaît', () => {
    mockAuth.user = { is_entreprise: true, organization_type: 'hybrid' };

    render(<ProfileScreen />);

    expect(screen.getByText(LIBELLE)).toBeTruthy();
  });

  it('efface réellement le choix d’espace — un libellé seul ne fait rien', () => {
    mockAuth.user = { is_entreprise: true, organization_type: 'client_company' };

    render(<ProfileScreen />);
    fireEvent.press(screen.getByText(LIBELLE));

    // `clear()` repose la question au prochain rendu ; sans cet appel, le bouton serait décoratif
    // et le membre de société resterait enfermé du côté où il se trouve.
    expect(mockClearSpace).toHaveBeenCalledTimes(1);
  });

  it('ne l’impose pas à un particulier', () => {
    mockAuth.user = { is_entreprise: false, organization_type: null };

    render(<ProfileScreen />);

    expect(screen.queryByText(LIBELLE)).toBeNull();
  });

  it('ne l’impose pas non plus quand le serveur n’a pas encore répondu', () => {
    // `user` est nul le temps de `/auth/me` : proposer l'espace par défaut le ferait clignoter
    // chez un particulier à chaque lancement.
    mockAuth.user = null;

    render(<ProfileScreen />);

    expect(screen.queryByText(LIBELLE)).toBeNull();
  });
});
