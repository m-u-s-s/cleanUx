/**
 * LE MUR DE CONFIRMATION DOIT AGIR, PAS SEULEMENT S'AFFICHER.
 *
 * Le serveur laisse SEPT routes ouvertes à une adresse non confirmée. Trois d'entre elles sont la
 * seule issue possible, et cet écran est leur unique appelant : redemander l'e-mail, relire le
 * compte, partir. Un mur qui affiche trois boutons inertes serait pire que pas de mur — il
 * donnerait l'illusion d'une sortie.
 */
import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const mockPost = jest.fn();
const mockGet = jest.fn();
const mockSetUser = jest.fn();
const mockLogout = jest.fn();

jest.mock('@/api', () => ({
  __esModule: true,
  apiClient: {
    post: (...args: unknown[]) => mockPost(...args),
    get: (...args: unknown[]) => mockGet(...args),
  },
  ApiError: class ApiError extends Error {},
}));

jest.mock('../useAuth', () => ({
  __esModule: true,
  useAuth: () => ({
    user: { id: 1, email: 'nouveau@exemple.test' },
    isAuthenticated: true,
    isLoading: false,
    setUser: mockSetUser,
    logout: mockLogout,
  }),
}));

import { EcranConfirmationEmail } from '../EcranConfirmationEmail';

const afficher = () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <EcranConfirmationEmail />
    </QueryClientProvider>,
  );
};

beforeEach(() => {
  jest.clearAllMocks();
  mockPost.mockResolvedValue({ data: { ok: true, already_verified: false, message: 'Un nouvel e-mail vient de partir.' } });
  mockGet.mockResolvedValue({ data: { user: { id: 1, email_verified: false } } });
});

describe('EcranConfirmationEmail', () => {
  it('annonce l’adresse à confirmer', () => {
    const { getByText } = afficher();

    expect(getByText('nouveau@exemple.test')).toBeTruthy();
  });

  it('redemande l’e-mail au serveur, et répète ce qu’il répond', async () => {
    const { getByTestId, getByText } = afficher();

    fireEvent.press(getByTestId('bouton-renvoyer-email'));

    await waitFor(() => {
      expect(mockPost).toHaveBeenCalledWith('/auth/email/verification-notification');
    });

    await waitFor(() => {
      expect(getByText('Un nouvel e-mail vient de partir.')).toBeTruthy();
    });
  });

  it('relit le compte et le rend à l’application quand on dit avoir confirmé', async () => {
    mockGet.mockResolvedValue({ data: { user: { id: 1, email_verified: true } } });

    const { getByTestId } = afficher();

    fireEvent.press(getByTestId('bouton-relire-le-compte'));

    await waitFor(() => {
      expect(mockGet).toHaveBeenCalledWith('/auth/me');
    });

    // C'est `setUser` qui lève le mur : le résolveur d'espace relit le compte, pas cet écran.
    await waitFor(() => {
      expect(mockSetUser).toHaveBeenCalledWith({ id: 1, email_verified: true });
    });
  });

  it('le dit plutôt que de mentir quand le lien n’a pas encore été ouvert', async () => {
    const { getByTestId, getByText } = afficher();

    fireEvent.press(getByTestId('bouton-relire-le-compte'));

    await waitFor(() => {
      expect(getByText(/pas encore été ouvert/)).toBeTruthy();
    });
  });

  it('laisse partir', () => {
    const { getByTestId } = afficher();

    fireEvent.press(getByTestId('bouton-se-deconnecter'));

    expect(mockLogout).toHaveBeenCalled();
  });
});
