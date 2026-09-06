import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import { PlacesScreen } from '../PlacesScreen';
import { apiClient } from '@/api';

jest.mock('@/api', () => ({
  __esModule: true,
  apiClient: { get: jest.fn(), post: jest.fn(), delete: jest.fn() },
}));

jest.mock('@tanstack/react-query', () => ({
  __esModule: true,
  useQuery: () => ({ data: [], refetch: jest.fn(), isRefetching: false }),
  useQueryClient: () => ({ invalidateQueries: jest.fn() }),
  useMutation: ({ mutationFn }: any) => ({
    mutate: () => void mutationFn(),
    isPending: false,
  }),
}));

/**
 * SANS CODE POSTAL, UN LIEU N'A PAS DE ZONE — et sans zone, ni prix local ni prestataire.
 *
 * L'écran déclarait `city` et `postal_code` dans son type, les AFFICHAIT dans la liste, et ne
 * les collectait nulle part. L'API les accepte pourtant, et `ClientPlaceService` en déduit la
 * zone. Mesuré le 2026-09-06 : lieu créé depuis l'application → `service_zone_id` nul.
 */
describe('PlacesScreen — le code postal part au serveur', () => {
  beforeEach(() => jest.clearAllMocks());

  it('envoie le code postal et la ville saisis', async () => {
    const { getByTestId } = render(<PlacesScreen />);

    fireEvent.changeText(getByTestId('champ-libelle-lieu'), 'Chez moi');
    fireEvent.changeText(getByTestId('champ-adresse-lieu'), 'Rue de la Loi 16');
    fireEvent.changeText(getByTestId('champ-code-postal-lieu'), '1000');
    fireEvent.changeText(getByTestId('champ-ville-lieu'), 'Bruxelles');
    fireEvent.press(getByTestId('bouton-ajouter-lieu'));

    await waitFor(() => expect(apiClient.post).toHaveBeenCalled());

    expect((apiClient.post as jest.Mock).mock.calls[0][1]).toEqual(
      expect.objectContaining({ postal_code: '1000', city: 'Bruxelles' }),
    );
  });

  it('témoin : laissés vides, ils partent à null plutôt qu’en chaîne vide', async () => {
    const { getByTestId } = render(<PlacesScreen />);

    fireEvent.changeText(getByTestId('champ-libelle-lieu'), 'Sans code');
    fireEvent.changeText(getByTestId('champ-adresse-lieu'), 'Quelque part');
    fireEvent.press(getByTestId('bouton-ajouter-lieu'));

    await waitFor(() => expect(apiClient.post).toHaveBeenCalled());

    expect((apiClient.post as jest.Mock).mock.calls[0][1]).toEqual(
      expect.objectContaining({ postal_code: null, city: null }),
    );
  });
});
