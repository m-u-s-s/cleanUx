/**
 * L'écran de langue n'appliquait rien : il écrivait `locale` sur le serveur et l'application
 * restait en français, y compris cet écran-là.
 */
import React from 'react';
import { Alert } from 'react-native';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { LanguageScreen } from '@/screens/LanguageScreen';
import { choisirLaLangue, langueActuelle } from '@/i18n';

const mockPut = jest.fn().mockResolvedValue({ data: { user: {} } });

// `langue.ts` importe `../api/client` : c'est CE module qu'il faut bouchonner, pas la barrique.
jest.mock('@/api/client', () => ({
  __esModule: true,
  apiClient: { put: (...a: unknown[]) => mockPut(...a) },
}));

jest.mock('@/auth', () => ({
  __esModule: true,
  useAuth: () => ({ user: { id: 1, locale: 'fr' }, setUser: jest.fn() }),
}));

const navigation = { goBack: jest.fn() } as never;

describe('l’écran de langue', () => {
  beforeEach(async () => {
    jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);
    mockPut.mockClear();
    // L'état de langue vit au niveau du module : chaque test repart du français.
    await choisirLaLangue('fr');
    mockPut.mockClear();
  });

  afterEach(() => jest.restoreAllMocks());

  it('applique le choix DANS l’application, pas seulement au serveur', async () => {
    const { getByTestId } = render(<LanguageScreen navigation={navigation} />);

    fireEvent.press(getByTestId('langue-nl'));
    fireEvent.press(getByTestId('langue-enregistrer'));

    await waitFor(() => expect(langueActuelle()).toBe('nl'));

    expect(mockPut).toHaveBeenCalledWith('/client/profile', { locale: 'nl' });
  });

  it('l’écran lui-même se traduit', async () => {
    await choisirLaLangue('nl');

    const { getByText, queryByText } = render(<LanguageScreen navigation={navigation} />);

    await waitFor(() => expect(getByText('Taal')).toBeTruthy());

    expect(queryByText('Langue')).toBeNull();
  });

  /** LE TÉMOIN : en français, c'est bien « Langue » qui s'affiche — la mesure compare. */
  it('témoin : en français l’écran affiche son titre français', async () => {
    const { getByText } = render(<LanguageScreen navigation={navigation} />);

    await waitFor(() => expect(getByText('Langue')).toBeTruthy());
  });
});
