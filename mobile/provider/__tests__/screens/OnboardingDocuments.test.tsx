/**
 * Étape « justificatifs » : sélection de la pièce d'identité et envoi réel.
 *
 * Elle était jusqu'ici un cul-de-sac — elle renvoyait vers le support faute de sélecteur natif.
 * C'était le dernier verrou empêchant un prestataire de compléter son dossier en autonomie.
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

/** Pilote les sélecteurs natifs, absents de l'environnement de test. */
const mockPick: { image: any; document: any } = { image: null, document: null };

jest.mock('@/screens/onboarding/documentPicker', () => {
  const actual = jest.requireActual('@/screens/onboarding/documentPicker');
  return {
    ...actual,
    pickImage: jest.fn(async () => {
      if (mockPick.image instanceof Error) throw mockPick.image;
      return mockPick.image;
    }),
    pickDocument: jest.fn(async () => {
      if (mockPick.document instanceof Error) throw mockPick.document;
      return mockPick.document;
    }),
  };
});

import { apiClient } from '@/api';
import { DocumentsStep } from '@/screens/onboarding/steps';

const apiMock = new MockAdapter(apiClient);

function renderStep(onDone = jest.fn()) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
  render(
    <QueryClientProvider client={client}>
      <DocumentsStep onDone={onDone} submitting={false} error={null} />
    </QueryClientProvider>,
  );

  return onDone;
}

const A_VALID_FILE = { uri: 'file:///piece.jpg', name: 'piece.jpg', mimeType: 'image/jpeg', size: 1024 };

beforeEach(() => {
  apiMock.reset();
  mockPick.image = null;
  mockPick.document = null;
});

describe('Étape justificatifs', () => {
  it("envoie la pièce photographiée et valide l'étape", async () => {
    mockPick.image = A_VALID_FILE;
    apiMock.onPost('/provider/onboarding/documents').reply(201, { ok: true });

    const onDone = renderStep();

    fireEvent.press(screen.getByLabelText('Prendre en photo'));
    await waitFor(() => screen.getByTestId('onboarding-document-picked'));

    fireEvent.press(screen.getByLabelText('Envoyer et continuer'));

    await waitFor(() => expect(onDone).toHaveBeenCalled());
    expect(apiMock.history['post']!.filter(c => c.url === '/provider/onboarding/documents')).toHaveLength(1);
  });

  it('accepte aussi un fichier déjà scanné', async () => {
    mockPick.document = { uri: 'file:///piece.pdf', name: 'piece.pdf', mimeType: 'application/pdf', size: 2048 };
    apiMock.onPost('/provider/onboarding/documents').reply(201, { ok: true });

    const onDone = renderStep();

    fireEvent.press(screen.getByLabelText('Choisir un fichier'));
    await waitFor(() => screen.getByText('piece.pdf'));

    fireEvent.press(screen.getByLabelText('Envoyer et continuer'));
    await waitFor(() => expect(onDone).toHaveBeenCalled());
  });

  /**
   * Ce que le serveur refuserait est refusé ici : inutile de faire remonter le fichier pour
   * recevoir un 422.
   */
  it('refuse un format non accepté sans rien envoyer', async () => {
    mockPick.document = { uri: 'file:///piece.docx', name: 'piece.docx', mimeType: 'application/msword', size: 500 };

    renderStep();
    fireEvent.press(screen.getByLabelText('Choisir un fichier'));

    await waitFor(() => expect(screen.getByText(/format non accepté/i)).toBeTruthy());
    expect(apiMock.history['post'] ?? []).toHaveLength(0);
  });

  it('refuse un fichier au-delà de 10 Mo sans rien envoyer', async () => {
    mockPick.document = { uri: 'file:///gros.pdf', name: 'gros.pdf', mimeType: 'application/pdf', size: 11 * 1024 * 1024 };

    renderStep();
    fireEvent.press(screen.getByLabelText('Choisir un fichier'));

    await waitFor(() => expect(screen.getByText(/trop volumineux/i)).toBeTruthy());
    expect(apiMock.history['post'] ?? []).toHaveLength(0);
  });

  /** Une annulation n'est pas une erreur : l'écran doit rester silencieux. */
  it("ne signale rien quand l'utilisateur annule la sélection", async () => {
    mockPick.image = null;

    renderStep();
    fireEvent.press(screen.getByLabelText('Prendre en photo'));

    await waitFor(() => expect(screen.getByLabelText('Ajoutez votre pièce')).toBeTruthy());
    expect(screen.queryByTestId('onboarding-document-picked')).toBeNull();
  });

  /** Un refus de permission doit être expliqué, pas avalé. */
  it('affiche le refus de permission', async () => {
    mockPick.image = new Error("Autorisez l'accès à l'appareil photo pour prendre votre pièce en photo.");

    renderStep();
    fireEvent.press(screen.getByLabelText('Prendre en photo'));

    await waitFor(() => expect(screen.getByText(/autorisez l'accès à l'appareil photo/i)).toBeTruthy());
  });

  it("signale un échec d'envoi sans valider l'étape", async () => {
    mockPick.image = A_VALID_FILE;
    apiMock.onPost('/provider/onboarding/documents').reply(500);

    const onDone = renderStep();

    fireEvent.press(screen.getByLabelText('Prendre en photo'));
    await waitFor(() => screen.getByTestId('onboarding-document-picked'));
    fireEvent.press(screen.getByLabelText('Envoyer et continuer'));

    await waitFor(() => expect(screen.getByText(/l'envoi a échoué/i)).toBeTruthy());
    expect(onDone).not.toHaveBeenCalled();
  });
});
