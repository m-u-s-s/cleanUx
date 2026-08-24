/**
 * L'onglet Missions montre les missions acceptées, pas seulement les offres en attente.
 *
 * Défaut corrigé : l'onglet affichait la boîte de réception, qui ne liste que les OFFRES
 * (`assignment_status = 'assigned'`). Accepter une mission la faisait donc disparaître de
 * l'application — elle restait visible le temps de la navigation qui suit l'acceptation, puis un
 * retour en arrière et le prestataire n'avait plus aucun moyen de retrouver ce qu'il venait de
 * prendre. L'écran qui liste les missions actives existait pourtant, complet, mais n'était monté
 * nulle part.
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react-native';

const mockGet = jest.fn();
const mockNavigate = jest.fn();

jest.mock('@/api', () => ({ apiClient: { get: (...args: unknown[]) => mockGet(...args) } }));

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
}));

jest.mock('react-native-reanimated', () => {
  const { View } = require('react-native');

  return {
    __esModule: true,
    default: { View: ({ children }: any) => <View>{children}</View> },
    FadeIn: { duration: () => ({}) },
  };
});

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity } = require('react-native');

  return {
    // Crochet d'accessibilite, pas un composant : l'ecran ne s'anime pas sous test.
    useEntree: () => undefined,
    useDuree: () => 0,
    Screen: ({ children, testID }: any) => <View testID={testID}>{children}</View>,
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Skeleton: () => <View />,
    AnimatedListItem: ({ children }: any) => <View>{children}</View>,
    EmptyState: ({ title, actionLabel, onAction }: any) => (
      <View>
        <Text>{title}</Text>
        {actionLabel && (
          <TouchableOpacity onPress={onAction} accessibilityLabel={actionLabel}>
            <Text>{actionLabel}</Text>
          </TouchableOpacity>
        )}
      </View>
    ),
  };
});

jest.mock('@/theme', () => ({
  colors: { brand: { 500: '#6366f1' }, surface: { 400: '#94a3b8', 500: '#64748b', 900: '#0f172a' } },
  spacing: { xs: 4, sm: 8, md: 16, xl: 32 },
  typography: { fontSize: { xs: 12, sm: 14, base: 16, xl: 20 }, fontWeight: { bold: '700', semibold: '600', medium: '500' } },
  radius: { md: 14 },
  shadows: { xs: {}, soft: {} },
  // Le thème réel plutôt qu'un objet partiel écrit à la main : il n'a aucun effet de bord,
  // et un stub partiel périme à chaque jeton ajouté — c'est ce qui a cassé ces tests quand
  // les teintes sont apparues.
  useThemeColors: jest.requireActual('@/theme/useThemeColors').useThemeColors,
}));

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MissionsScreen } from '@/screens/MissionsScreen';

function renderTab() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <MissionsScreen />
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  mockGet.mockReset();
  mockNavigate.mockReset();
});

describe('Onglet Missions', () => {
  it('liste les missions actives du prestataire', async () => {
    mockGet.mockResolvedValue({
      data: {
        data: [{
          id: 4,
          status: 'en_route',
          service_name: 'Babysitting ponctuel',
          client_name: 'TestR',
          address: '1 rue Test',
          city: 'Bruxelles',
          scheduled_date: '2026-08-01',
          scheduled_time: '09:00',
        }],
      },
    });

    renderTab();

    await waitFor(() => expect(screen.getByText('Babysitting ponctuel')).toBeTruthy());
  });

  /**
   * Le cœur de la régression : c'est `/provider/missions/active` qui rend les missions
   * ACCEPTÉES. La boîte de réception ne renvoie que les offres en attente, et une mission
   * acceptée n'y figure plus.
   */
  it('interroge les missions actives, pas la boîte de réception', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } });

    renderTab();

    await waitFor(() => expect(mockGet).toHaveBeenCalledWith('/provider/missions/active'));
    expect(mockGet).not.toHaveBeenCalledWith('/provider/assignments/inbox');
  });

  /** Sans mission acceptée, l'onglet doit dire où trouver les propositions. */
  it('renvoie vers les offres quand il n’y a rien à faire', async () => {
    mockGet.mockResolvedValue({ data: { data: [] } });

    renderTab();

    await waitFor(() => expect(screen.getByText('Aucune mission active')).toBeTruthy());
    expect(screen.getByLabelText('Voir les missions disponibles')).toBeTruthy();
  });
});
