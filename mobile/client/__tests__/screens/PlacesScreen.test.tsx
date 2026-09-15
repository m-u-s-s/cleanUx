import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const mockGet = jest.fn();
const mockPost = jest.fn();
const mockDelete = jest.fn();

jest.mock('@/api', () => ({
  __esModule: true,
  apiClient: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
    delete: (...args: unknown[]) => mockDelete(...args),
  },
}));

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    Button: ({ label, onPress, testID, disabled }: any) => (
      <Text onPress={disabled ? undefined : onPress} testID={testID}>{label}</Text>
    ),
    Badge: ({ label }: any) => <Text>{label}</Text>,
    EmptyState: ({ title }: any) => <Text>{title}</Text>,
  };
});

import { PlacesScreen } from '@/screens/PlacesScreen';

const lieu = (id: number, label: string, is_default: boolean) => ({
  id, label, address: `Rue ${id}`, city: 'Bruxelles', postal_code: '1000',
  floor: null, access_instructions: null, alarm_code_required: false, is_default,
});

const monter = () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  const invalider = jest.spyOn(client, 'invalidateQueries');
  const ecran = render(
    <QueryClientProvider client={client}>
      <PlacesScreen />
    </QueryClientProvider>,
  );

  return { ecran, invalider };
};

const aInvalide = (appels: unknown[][], cle: unknown[]) =>
  appels.some(([filtre]) => JSON.stringify((filtre as { queryKey?: unknown } | undefined)?.queryKey) === JSON.stringify(cle));

beforeEach(() => {
  mockGet.mockReset().mockResolvedValue({ data: { data: [lieu(1, 'Chez moi', true), lieu(2, 'Bureau', false)] } });
  mockPost.mockReset().mockResolvedValue({ data: {} });
  mockDelete.mockReset().mockResolvedValue({ data: {} });
});

/*
 * LE CATALOGUE NATIF SUIT LE CARNET. Sa zone vient du lieu par défaut (à défaut d'un panier ouvert
 * avec adresse) : un carnet qui change peut changer les métiers proposés et leurs prix, et une
 * réponse gardée cinq minutes en cache les montrerait encore.
 */
describe('PlacesScreen — le catalogue suit le carnet', () => {
  it('choisir un autre lieu par défaut redemande le catalogue', async () => {
    const { ecran, invalider } = monter();

    fireEvent.press(await ecran.findByTestId('defaut-lieu-2'));

    await waitFor(() => expect(aInvalide(invalider.mock.calls, ['catalogue'])).toBe(true));
    expect(mockPost).toHaveBeenCalledWith('/client/places/2/default', {});
    expect(aInvalide(invalider.mock.calls, ['client', 'places'])).toBe(true);
  });

  it('archiver un lieu redemande le catalogue', async () => {
    const { ecran, invalider } = monter();

    fireEvent.press(await ecran.findByTestId('archiver-lieu-1'));

    await waitFor(() => expect(aInvalide(invalider.mock.calls, ['catalogue'])).toBe(true));
    expect(mockDelete).toHaveBeenCalledWith('/client/places/1');
  });

  it('ajouter un lieu redemande le catalogue', async () => {
    const { ecran, invalider } = monter();

    await ecran.findByTestId('lieu-1');
    fireEvent.changeText(ecran.getByTestId('champ-libelle-lieu'), 'Maison de campagne');
    fireEvent.changeText(ecran.getByTestId('champ-adresse-lieu'), 'Chemin du Moulin 3');
    fireEvent.press(ecran.getByTestId('bouton-ajouter-lieu'));

    await waitFor(() => expect(aInvalide(invalider.mock.calls, ['catalogue'])).toBe(true));
    expect(mockPost).toHaveBeenCalledWith('/client/places', expect.objectContaining({ label: 'Maison de campagne' }));
  });

  it('témoin : ouvrir le carnet sans rien changer ne touche pas au catalogue', async () => {
    const { ecran, invalider } = monter();

    await ecran.findByTestId('lieu-2');

    expect(aInvalide(invalider.mock.calls, ['catalogue'])).toBe(false);
  });
});
