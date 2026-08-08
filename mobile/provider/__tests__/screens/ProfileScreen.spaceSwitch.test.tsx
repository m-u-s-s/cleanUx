/**
 * La porte de sortie de l'espace prestataire.
 *
 * POURQUOI CE FICHIER EXISTE. Le 2026-08-05, un parcours d'accessibilité a montré que
 * `clear()` — le seul moyen de revenir au sélecteur d'espace — n'était appelé QUE dans
 * `AdminProfileScreen`. Un compte à double casquette qui choisissait « prestataire » une fois
 * ne pouvait plus jamais atteindre la console d'administration : ni ses quatre onglets, ni ses
 * écrans, hors réinstallation. `useSpacePreference` documentait pourtant l'inverse — « le choix
 * reste réversible depuis le profil ».
 *
 * Ce test fige les deux moitiés de la règle : la sortie existe pour qui a les deux rôles, et
 * elle n'encombre pas le profil de qui n'en a qu'un.
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';

const mockClearSpace = jest.fn();
const mockAuth = { user: null as unknown, logout: jest.fn() };

/*
 * `can` REND LA VRAIE FONCTION, pas un bouchon.
 *
 * Les écrans société conditionnent désormais leurs boutons et leurs onglets par les clés que
 * le serveur déclare dans `/auth/me`. `can` est une fonction PURE sur l'objet utilisateur : la
 * bouchonner reviendrait à tester le bouchon, et masquerait le défaut-refus — le comportement
 * qui protège une application plus ancienne qu'une clé nouvelle.
 *
 * Le mock d'un baril doit rendre TOUT ce que le module sous test en importe : sans cette ligne,
 * `can` vaut `undefined` et le rendu casse sur un `TypeError` sans rapport apparent.
 */
jest.mock('@/auth', () => ({
  useAuth: () => mockAuth,
  can: jest.requireActual('../../../shared/src/auth/permissions').can,
}));
jest.mock('@/admin/useSpacePreference', () => ({
  useSpacePreference: () => ({ clear: mockClearSpace, space: 'provider', isLoading: false, choose: jest.fn() }),
}));
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
}));

import { ProfileScreen } from '@/screens/ProfileScreen';

const LIBELLE = 'Changer d’espace';

beforeEach(() => {
  mockClearSpace.mockClear();
});

describe('ProfileScreen — bascule d’espace', () => {
  it('propose la sortie à un compte administrateur ET prestataire', () => {
    mockAuth.user = { is_admin: true, is_provider: true };

    render(<ProfileScreen />);

    expect(screen.getByText(LIBELLE)).toBeTruthy();
  });

  it('appelle clear() — sans quoi le bouton ne ramènerait nulle part', () => {
    mockAuth.user = { is_admin: true, is_provider: true };

    render(<ProfileScreen />);
    fireEvent.press(screen.getByText(LIBELLE));

    expect(mockClearSpace).toHaveBeenCalledTimes(1);
  });

  it('ne la propose pas à un prestataire seul', () => {
    mockAuth.user = { is_admin: false, is_provider: true };

    render(<ProfileScreen />);

    expect(screen.queryByText(LIBELLE)).toBeNull();
  });
});
