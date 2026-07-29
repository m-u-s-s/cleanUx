/**
 * Étape « vérification d'identité ».
 *
 * `POST /provider/kyc/start` rendait déjà `hosted_flow_url` — l'adresse du parcours biométrique —
 * mais elle n'était JAMAIS ouverte : l'écran affichait « vérification lancée » alors que rien ne
 * s'était produit, et le prestataire attendait indéfiniment une décision qui ne viendrait jamais.
 * `GET /provider/kyc/status` existait de la même façon, appelé nulle part.
 *
 * Ce que ces tests verrouillent : l'adresse est réellement ouverte, l'état affiché est celui du
 * serveur, et l'étape ne se valide pas tant que l'identité n'est pas confirmée.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  default: {
    addEventListener: jest.fn(() => () => undefined),
    fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  },
}));

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity } = require('react-native');
  return {
    Button: ({ label, onPress, loading }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label} disabled={loading}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    TextInput: () => <View />,
    Icon: () => <View />,
  };
});

jest.mock('@/theme', () => ({
  colors: {
    brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5' },
    success: { 600: '#059669' },
    surface: { 200: '#e5e5e5', 400: '#a3a3a3', 500: '#737373', 600: '#525252', 700: '#404040' },
    danger: { 50: '#fef2f2', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c' },
    mode: { tool: { ink: '#0f172a', muted: '#64748b' } },
  },
  radius: { md: 14, lg: 22, pill: 999 },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24, xl: 32 },
  typography: {
    fontSize: { xs: 12, sm: 14, base: 16, lg: 18, xl: 20, '2xl': 24 },
    fontWeight: { medium: '500', semibold: '600', bold: '700' },
  },
}));

import { Linking } from 'react-native';
import { apiClient } from '@/api';
import { KycStep } from '@/screens/onboarding/steps';

/**
 * On espionne le Linking réellement importé par l'écran plutôt que de substituer un module :
 * un faux posé sur un autre chemin de résolution resterait vert même si l'écran cessait
 * d'ouvrir quoi que ce soit.
 */
const mockOpenURL = jest.spyOn(Linking, 'openURL').mockResolvedValue(true);

const apiMock = new MockAdapter(apiClient);
const HOSTED_FLOW = 'https://id.onfido.example/flow/abc123';

function serveStatus(status: Record<string, unknown>) {
  apiMock.onGet('/provider/kyc/status').reply(200, status);
}

function renderStep(onDone = jest.fn()) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
  render(
    <QueryClientProvider client={client}>
      <KycStep onDone={onDone} submitting={false} error={null} />
    </QueryClientProvider>,
  );

  return onDone;
}

beforeEach(() => {
  apiMock.reset();
  mockOpenURL.mockClear();
  serveStatus({ has_verification: false });
});

describe("Étape vérification d'identité", () => {
  /** Le défaut central : l'adresse rendue par le serveur n'était jamais ouverte. */
  it('ouvre le parcours biométrique renvoyé par le serveur', async () => {
    apiMock.onPost('/provider/kyc/start').reply(201, {
      verification_id: 1,
      status: 'pending',
      decision: null,
      hosted_flow_url: HOSTED_FLOW,
    });

    renderStep();
    await waitFor(() => screen.getByLabelText('Démarrer la vérification'));

    fireEvent.press(screen.getByLabelText('Démarrer la vérification'));

    await waitFor(() => expect(mockOpenURL).toHaveBeenCalledWith(HOSTED_FLOW));
  });

  /**
   * En mode simulé, aucun parcours hébergé n'est rendu : l'écran ne doit ni planter ni prétendre
   * ouvrir quoi que ce soit.
   */
  it("n'ouvre rien quand le serveur ne fournit pas d'adresse", async () => {
    apiMock.onPost('/provider/kyc/start').reply(201, {
      verification_id: 1,
      status: 'pending',
      decision: null,
      hosted_flow_url: null,
    });

    renderStep();
    await waitFor(() => screen.getByLabelText('Démarrer la vérification'));
    fireEvent.press(screen.getByLabelText('Démarrer la vérification'));

    await waitFor(() => {
      expect(apiMock.history['post']!.filter(c => c.url === '/provider/kyc/start')).toHaveLength(1);
    });
    expect(mockOpenURL).not.toHaveBeenCalled();
  });

  it("affiche l'attente tant que le tiers n'a pas tranché", async () => {
    serveStatus({ has_verification: true, status: 'in_review', decision: null });

    renderStep();

    await waitFor(() => expect(screen.getByTestId('kyc-pending')).toBeTruthy());
    // Rien à valider : l'étape ne passera qu'à la décision.
    expect(screen.queryByLabelText('Continuer')).toBeNull();
  });

  it("ne valide pas l'étape tant que l'identité n'est pas confirmée", async () => {
    serveStatus({ has_verification: true, status: 'in_review', decision: null });

    const onDone = renderStep();
    await waitFor(() => screen.getByTestId('kyc-pending'));

    expect(onDone).not.toHaveBeenCalled();
  });

  it("propose de continuer une fois l'identité vérifiée", async () => {
    serveStatus({ has_verification: true, status: 'complete', decision: 'clear' });

    const onDone = renderStep();

    await waitFor(() => expect(screen.getByTestId('kyc-verified')).toBeTruthy());
    fireEvent.press(screen.getByLabelText('Continuer'));
    expect(onDone).toHaveBeenCalled();
  });

  /** `provider_verification_status` fait foi quand la décision est portée par le profil. */
  it('reconnaît un profil déjà vérifié', async () => {
    serveStatus({ has_verification: false, provider_verification_status: 'verified' });

    renderStep();

    await waitFor(() => expect(screen.getByTestId('kyc-verified')).toBeTruthy());
  });

  /** Un refus doit être dit avec son motif, et laisser relancer. */
  it('annonce un refus avec son motif et permet de recommencer', async () => {
    serveStatus({
      has_verification: true,
      status: 'complete',
      decision: 'rejected',
      rejection_reason: 'Le document fourni est expiré',
    });

    renderStep();

    await waitFor(() => expect(screen.getByTestId('kyc-refused')).toBeTruthy());
    expect(screen.getByText(/document fourni est expiré/i)).toBeTruthy();
    expect(screen.getByLabelText('Reprendre la vérification')).toBeTruthy();
  });

  it("signale l'échec du démarrage sans laisser croire à une vérification lancée", async () => {
    apiMock.onPost('/provider/kyc/start').reply(422, { ok: false, error: 'Provider indisponible' });

    renderStep();
    await waitFor(() => screen.getByLabelText('Démarrer la vérification'));
    fireEvent.press(screen.getByLabelText('Démarrer la vérification'));

    await waitFor(() => expect(screen.getByText(/impossible de démarrer/i)).toBeTruthy());
    expect(screen.queryByTestId('kyc-pending')).toBeNull();
  });
});
