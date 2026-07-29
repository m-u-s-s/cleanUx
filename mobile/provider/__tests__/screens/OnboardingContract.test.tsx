/**
 * Étape « contrat prestataire ».
 *
 * L'écran affichait un texte codé en dur et « signait » en transmettant un numéro de version :
 * aucune signature n'existait en base, donc aucune piste d'audit — alors que Contracts v2
 * (modèles versionnés, rendu personnalisé, signature horodatée avec empreinte, PDF) était
 * entièrement construit et n'était appelé de nulle part.
 *
 * Deux chemins, tous deux vérifiés ici : la vraie signature là où un modèle est publié, et le
 * repli par version ailleurs — un déploiement sans modèle seedé doit rester praticable plutôt
 * que d'enfermer le prestataire sur une étape infranchissable.
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

jest.mock('@/auth', () => {
  const actual = jest.requireActual('@/auth');
  return { ...actual, useAuth: () => ({ user: { id: 7, name: 'Jean Dupont' }, setUser: jest.fn() }) };
});

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
    success: { 50: '#ecfdf5', 600: '#059669', 700: '#047857' },
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

import { apiClient } from '@/api';
import { ContractStep } from '@/screens/onboarding/steps';

const apiMock = new MockAdapter(apiClient);
const DOCUMENT_ID = 55;

function serveTemplate() {
  apiMock.onPost('/v2/contracts/documents').reply(201, {
    ok: true,
    document: {
      id: DOCUMENT_ID,
      code: 'provider_agreement',
      body_rendered_html: '<p>Contrat de <strong>Jean Dupont</strong>.</p><p>Article 1 &amp; suivants.</p>',
      status: 'ready',
    },
  });
}

function renderStep(onDone = jest.fn()) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
  render(
    <QueryClientProvider client={client}>
      <ContractStep onDone={onDone} submitting={false} error={null} />
    </QueryClientProvider>,
  );

  return onDone;
}

function signCalls() {
  return apiMock.history['post']!.filter(c => c.url === `/v2/contracts/documents/${DOCUMENT_ID}/sign`);
}

beforeEach(() => apiMock.reset());

describe('Étape contrat', () => {
  /** Le texte affiché doit être celui du serveur, personnalisé, et non un décor figé. */
  it('affiche le contrat rendu par le serveur', async () => {
    serveTemplate();

    renderStep();

    await waitFor(() => expect(screen.getByText(/Contrat de Jean Dupont/)).toBeTruthy());
    // Le balisage est retiré, les entités décodées.
    expect(screen.getByText(/Article 1 & suivants/)).toBeTruthy();
    expect(screen.queryByText(/<strong>/)).toBeNull();
  });

  /** Le défaut central : aucune signature n'était jamais enregistrée. */
  it('enregistre une vraie signature puis valide l’étape', async () => {
    serveTemplate();
    apiMock.onPost(`/v2/contracts/documents/${DOCUMENT_ID}/sign`).reply(201, { ok: true });

    const onDone = renderStep();
    await waitFor(() => screen.getByText(/Contrat de Jean Dupont/));

    fireEvent.press(screen.getByTestId('onboarding-accept-contract'));
    fireEvent.press(screen.getByLabelText('Signer et continuer'));

    await waitFor(() => expect(signCalls()).toHaveLength(1));
    expect(JSON.parse(signCalls()[0]!.data).signer_name).toBe('Jean Dupont');
    expect(onDone).toHaveBeenCalledWith({ template_code: 'provider_agreement' });
  });

  it("n'appelle pas le serveur tant que le contrat n'est pas accepté", async () => {
    serveTemplate();

    const onDone = renderStep();
    await waitFor(() => screen.getByText(/Contrat de Jean Dupont/));

    fireEvent.press(screen.getByLabelText('Signer et continuer'));

    await waitFor(() => expect(screen.getByText(/devez accepter le contrat/i)).toBeTruthy());
    expect(signCalls()).toHaveLength(0);
    expect(onDone).not.toHaveBeenCalled();
  });

  it("ne valide pas l'étape quand la signature échoue", async () => {
    serveTemplate();
    apiMock.onPost(`/v2/contracts/documents/${DOCUMENT_ID}/sign`).reply(500);

    const onDone = renderStep();
    await waitFor(() => screen.getByText(/Contrat de Jean Dupont/));

    fireEvent.press(screen.getByTestId('onboarding-accept-contract'));
    fireEvent.press(screen.getByLabelText('Signer et continuer'));

    await waitFor(() => expect(screen.getByText(/signature a échoué/i)).toBeTruthy());
    expect(onDone).not.toHaveBeenCalled();
  });

  /**
   * Là où aucun modèle n'est publié, l'étape doit rester franchissable : le validateur bascule
   * lui aussi sur la version dans ce cas.
   */
  it('retombe sur la version quand aucun modèle n’est publié', async () => {
    apiMock.onPost('/v2/contracts/documents').reply(422, { ok: false, errors: { template_code: ['inconnu'] } });

    const onDone = renderStep();

    await waitFor(() => expect(screen.getByText(/qualité d'indépendant/)).toBeTruthy());
    fireEvent.press(screen.getByTestId('onboarding-accept-contract'));
    fireEvent.press(screen.getByLabelText('Signer et continuer'));

    await waitFor(() => expect(onDone).toHaveBeenCalledWith({ terms_accepted_version: '1.0' }));
    expect(signCalls()).toHaveLength(0);
  });
});
