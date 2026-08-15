import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';

const mockMarkRead = jest.fn();
const mockOpenUrl = jest.fn().mockResolvedValue(true);

const mockNotif = {
  id: 'n-1',
  type: 'FinanceVirementRecu',
  type_key: 'finance',
  label: 'Finance',
  title: 'Virement reçu',
  body: 'Paiement de 168,00 € crédité.',
  severity: 'warning' as const,
  context: { invoice_number: 'FA-2026-0088' },
  action_url: 'http://brio.test/dashboard/employe/portefeuille',
  action_path: '/dashboard/employe/portefeuille',
  action_label: 'Ouvrir mon portefeuille',
  read_at: null,
  created_at: '2026-08-15T09:00:00Z',
};

jest.mock('@/notifications/hooks', () => ({
  useNotification: () => ({ data: mockNotif, isLoading: false, isError: false, refetch: jest.fn() }),
  useMarkRead: () => ({ mutate: mockMarkRead, isPending: false }),
}));

jest.mock('@/theme', () => ({
  ...jest.requireActual('@/theme'),
  useThemeColors: jest.requireActual('@/theme/useThemeColors').useThemeColors,
}));

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    Button: ({ label, onPress, testID }: any) => <Text testID={testID} onPress={onPress}>{label}</Text>,
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Skeleton: () => <View />,
    ErrorState: ({ message }: any) => <Text>{message}</Text>,
    DetailRow: ({ label, value }: any) => <Text>{`${label}: ${value}`}</Text>,
  };
});

import { Linking } from 'react-native';
import { NotificationDetailView } from '@/notifications/NotificationDetailView';

describe('NotificationDetailView', () => {
  beforeEach(() => {
    mockMarkRead.mockClear();
    mockOpenUrl.mockClear();
    // Espionner le vrai `Linking` plutôt que remplacer son module interne : le chemin
    // `react-native/Libraries/Linking/Linking` change entre versions, et le remplacer rendait
    // `Linking` indéfini au point d'appel.
    jest.spyOn(Linking, 'openURL').mockImplementation((url: string) => mockOpenUrl(url));
  });

  it('rend le contenu complet : libellé, titre, message, contexte et traçabilité', () => {
    const { getByText } = render(<NotificationDetailView id="n-1" onOpenPath={jest.fn()} />);

    expect(getByText('Finance')).toBeTruthy();
    expect(getByText('Virement reçu')).toBeTruthy();
    expect(getByText('Paiement de 168,00 € crédité.')).toBeTruthy();
    expect(getByText('Facture: FA-2026-0088')).toBeTruthy();
    expect(getByText('Référence: n-1')).toBeTruthy();
    expect(getByText('Source: FinanceVirementRecu')).toBeTruthy();
  });

  /** La raison d'être de la fiche : dire où aller, et le dire par sa destination. */
  it('porte le lien de résolution nommé par sa destination', () => {
    const onOpenPath = jest.fn();
    const { getByTestId } = render(<NotificationDetailView id="n-1" onOpenPath={onOpenPath} />);

    fireEvent.press(getByTestId('notification-resolution'));

    expect(onOpenPath).toHaveBeenCalledWith('/dashboard/employe/portefeuille', 'Ouvrir mon portefeuille');
    // La page appartient à l'application : elle passe par l'hôte WebView, pas par le navigateur,
    // qui présenterait un écran de connexion.
    expect(mockOpenUrl).not.toHaveBeenCalled();
  });

  it('ouvre le navigateur quand la cible est hors application', () => {
    const externe = { ...mockNotif, action_path: '', action_url: 'https://exemple-externe.test/aide' };
    jest.spyOn(require('@/notifications/hooks'), 'useNotification').mockReturnValue({
      data: externe, isLoading: false, isError: false, refetch: jest.fn(),
    } as never);

    const onOpenPath = jest.fn();
    const { getByTestId } = render(<NotificationDetailView id="n-1" onOpenPath={onOpenPath} />);

    fireEvent.press(getByTestId('notification-resolution'));

    expect(onOpenPath).not.toHaveBeenCalled();
    expect(mockOpenUrl).toHaveBeenCalledWith('https://exemple-externe.test/aide');
  });

  /**
   * Ouvrir vaut lecture, mais posé PAR LE CLIENT : `GET /notifications/{id}` ne modifie rien.
   * L'endpoint existait côté serveur sans aucun appelant mobile — le compteur ne redescendait
   * qu'en marquant tout d'un coup.
   */
  it('marque la notification comme lue à l’ouverture', async () => {
    jest.spyOn(require('@/notifications/hooks'), 'useNotification').mockReturnValue({
      data: mockNotif, isLoading: false, isError: false, refetch: jest.fn(),
    } as never);

    render(<NotificationDetailView id="n-1" onOpenPath={jest.fn()} />);

    await waitFor(() => expect(mockMarkRead).toHaveBeenCalledWith('n-1'));
  });

  it('ne remarque pas comme lue une notification déjà lue', async () => {
    jest.spyOn(require('@/notifications/hooks'), 'useNotification').mockReturnValue({
      data: { ...mockNotif, read_at: '2026-08-15T10:00:00Z' }, isLoading: false, isError: false, refetch: jest.fn(),
    } as never);

    render(<NotificationDetailView id="n-1" onOpenPath={jest.fn()} />);

    await waitFor(() => expect(mockMarkRead).not.toHaveBeenCalled());
  });
});
