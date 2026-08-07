/**
 * LA PORTE VERS L'ESPACE SOCIÉTÉ.
 *
 * POURQUOI CE FICHIER EXISTE. Les cinq écrans société sont complets, montés dans `RootNavigator`
 * et couverts par `CompanyScreens.test.tsx` — qui les monte DIRECTEMENT. Aucun test n'exerçait le
 * seul chemin par lequel un utilisateur peut les atteindre, et ce chemin était condamné :
 *
 *     user?.is_entreprise === true && user?.organization_type === 'provider_company'
 *
 * `is_entreprise` est servi par `User::isEntreprise()`, qui délègue à `isClientCompany()` : vrai
 * pour `client_company` et `hybrid`, faux pour `provider_company`. La conjonction n'était donc
 * satisfiable par AUCUNE valeur d'`organization_type` — mesuré sur `/api/auth/me` :
 * `is_entreprise: false`, `organization_type: 'provider_company'`.
 *
 * Résultat : cinq écrans livrés, testés, joignables par personne. Même famille que les écrans
 * orphelins du 2026-07-30 — un signal vert ne dit rien de la joignabilité.
 *
 * Ce test fige la règle par son ÉNONCÉ MÉTIER : c'est le type de l'organisation qui décide, parce
 * que c'est lui qui distingue une société prestataire d'une société cliente. Le seul contrat que
 * l'API `/provider/company/*` impose est une organisation active (`CompanyController::
 * organisationActive()`), et `hybrid` en est une.
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';

const mockNavigate = jest.fn();
const mockAuth = { user: null as unknown, logout: jest.fn() };

jest.mock('@/auth', () => ({ useAuth: () => mockAuth }));
jest.mock('@/admin/useSpacePreference', () => ({
  useSpacePreference: () => ({ clear: jest.fn(), space: 'provider', isLoading: false, choose: jest.fn() }),
}));
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
}));

import { ProfileScreen } from '@/screens/ProfileScreen';

/** Les cinq écrans, par le libellé que l'utilisateur lit réellement. */
const LIBELLES = ['Répartition', 'Équipe', 'Équipes terrain', 'Tâches', 'Canaux'];

beforeEach(() => {
  mockNavigate.mockClear();
});

describe('ProfileScreen — porte vers l’espace société', () => {
  it('ouvre les cinq écrans à un membre de société prestataire', () => {
    // Exactement ce que `/api/auth/me` renvoie pour ce compte : le booléen société CLIENTE est
    // faux, et c'est normal — il ne dit rien d'une société prestataire.
    mockAuth.user = { is_provider: true, is_entreprise: false, organization_type: 'provider_company' };

    render(<ProfileScreen />);

    for (const libelle of LIBELLES) {
      expect(screen.getByText(libelle)).toBeTruthy();
    }
  });

  it('les ouvre à une organisation hybride, comme la garde web le fait déjà', () => {
    /*
     * VÉRIFIÉ PLUTÔT QUE SUPPOSÉ, ET LA VÉRIFICATION A RENVERSÉ L'ARGUMENT.
     *
     * On a d'abord retenu `provider_company` seul, au motif de s'aligner sur
     * `EnsureOrganizationType`. Mais cette garde, appelée avec `provider`, délègue à
     * `OrganizationType::isProvider()` — qui rend vrai pour `PROVIDER_COMPANY`, `PROVIDER_SOLO` ET
     * `HYBRID`.
     *
     * La condition mobile était donc PLUS ÉTROITE que la surface dont elle prétendait suivre la
     * règle : une organisation hybride ouvre l'espace société sur le web et se le voyait refuser
     * dans l'application. Inclure `hybrid` rapproche les deux au lieu de les éloigner.
     */
    mockAuth.user = { is_provider: true, is_entreprise: true, organization_type: 'hybrid' };

    render(<ProfileScreen />);

    for (const libelle of LIBELLES) {
      expect(screen.getByText(libelle)).toBeTruthy();
    }
  });

  it('ne les ouvre pas à un indépendant à structure légale — divergence assumée', () => {
    /*
     * `EnsureOrganizationType` admet `provider_solo` ; ici non, et c'est un CHOIX.
     *
     * Un indépendant à structure légale n'a ni équipe à répartir, ni équipes terrain, ni canaux
     * d'équipe : lui proposer ces six écrans remplirait son profil de surfaces vides. La
     * divergence est notée dans le composant pour qu'on ne la prenne pas pour un oubli.
     */
    mockAuth.user = { is_provider: true, is_entreprise: false, organization_type: 'provider_solo' };

    render(<ProfileScreen />);

    for (const libelle of LIBELLES) {
      expect(screen.queryByText(libelle)).toBeNull();
    }
  });

  it('navigue vers l’écran demandé, et pas seulement en affiche le nom', () => {
    mockAuth.user = { is_provider: true, is_entreprise: false, organization_type: 'provider_company' };

    render(<ProfileScreen />);
    fireEvent.press(screen.getByText('Répartition'));

    // Le libellé seul ne prouve rien : un bouton peut être rendu et ne mener nulle part.
    expect(mockNavigate).toHaveBeenCalledWith('CompanyDispatch');
  });

  it('ne les propose pas à un prestataire indépendant', () => {
    mockAuth.user = { is_provider: true, is_entreprise: false, organization_type: null };

    render(<ProfileScreen />);

    for (const libelle of LIBELLES) {
      expect(screen.queryByText(libelle)).toBeNull();
    }
  });

  it('laisse au gérant un chemin de RETOUR vers son espace société', () => {
    /*
     * Le piège qu'ouvre un troisième espace.
     *
     * Un gérant qui choisit « terrain » au sélecteur — parce qu'il intervient lui-même ce jour-là —
     * n'avait plus aucun moyen de revenir : « Changer d'espace » ne s'affichait qu'aux comptes
     * administrateur ET prestataire. Exactement l'enfermement que `clear()` avait corrigé pour la
     * console d'administration, rejoué par le nouvel espace.
     */
    mockAuth.user = {
      is_admin: false,
      is_provider: true,
      can_manage_company: true,
      organization_type: 'provider_company',
    };

    render(<ProfileScreen />);

    expect(screen.getByText('Changer d’espace')).toBeTruthy();
  });

  it('n’encombre pas le profil d’un prestataire qui n’a qu’un espace', () => {
    mockAuth.user = { is_admin: false, is_provider: true, can_manage_company: false, organization_type: null };

    render(<ProfileScreen />);

    expect(screen.queryByText('Changer d’espace')).toBeNull();
  });

  it('ne les propose pas à un membre de société CLIENTE', () => {
    // Ce compte-là a bien `is_entreprise: true` — c'est précisément ce que le drapeau signifie.
    // Son espace société est ailleurs ; ces cinq écrans ne sont pas les siens.
    mockAuth.user = { is_provider: true, is_entreprise: true, organization_type: 'client_company' };

    render(<ProfileScreen />);

    for (const libelle of LIBELLES) {
      expect(screen.queryByText(libelle)).toBeNull();
    }
  });
});
