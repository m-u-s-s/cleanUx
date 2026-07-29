/**
 * Étape « justificatifs » : les pièces demandées, leur envoi, leur état.
 *
 * Elle fut d'abord un cul-de-sac — elle renvoyait vers le support faute de sélecteur natif — puis
 * un second : un seul type était accepté, `identity_card` écrit en dur, alors que la validation
 * finale du dossier EXIGE aussi une assurance. Un peintre pouvait donc compléter son parcours et
 * rester impossible à approuver, sans que rien ne l'indique nulle part.
 *
 * La liste vient maintenant du serveur, dérivée des métiers déclarés, et chaque pièce porte son
 * propre état. Les contrôles locaux d'origine — format, taille, annulation, permission, échec
 * d'envoi — restent vérifiés ici : ils évitent des allers-retours réseau voués à un 422.
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

/**
 * Exigences telles que le serveur les renvoie. Deux pièces, dont une seule universelle : c'est
 * exactement ce que l'ancien écran ne savait pas représenter.
 */
const IDENTITY = {
  type: 'identity_card',
  label: "Pièce d'identité",
  help: 'Les quatre coins visibles.',
  required: true,
  accepts: ['identity_card', 'passport', 'residence_permit'],
  document: null,
};

const INSURANCE = {
  type: 'insurance',
  label: 'Assurance responsabilité civile professionnelle',
  help: 'Attestation en cours de validité.',
  required: true,
  accepts: ['insurance'],
  document: null,
};

function serveRequirements(...items: unknown[]) {
  apiMock.onGet('/provider/onboarding/documents').reply(200, {
    ok: true,
    requirements: items,
    documents: [],
  });
}

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

/** Attend que la liste servie par le serveur soit rendue. */
async function ready() {
  await waitFor(() => screen.getByTestId('onboarding-document-identity_card'));
}

function uploadCalls() {
  return apiMock.history['post']!.filter(c => c.url === '/provider/onboarding/documents');
}

const A_VALID_FILE = { uri: 'file:///piece.jpg', name: 'piece.jpg', mimeType: 'image/jpeg', size: 1024 };

beforeEach(() => {
  apiMock.reset();
  mockPick.image = null;
  mockPick.document = null;
  serveRequirements(IDENTITY);
});

describe('Étape justificatifs', () => {
  it('envoie la pièce photographiée', async () => {
    mockPick.image = A_VALID_FILE;
    apiMock.onPost('/provider/onboarding/documents').reply(201, { ok: true });

    renderStep();
    await ready();

    fireEvent.press(screen.getByLabelText('Prendre en photo'));
    await waitFor(() => screen.getByText('piece.jpg'));

    fireEvent.press(screen.getByLabelText('Envoyer'));

    await waitFor(() => expect(uploadCalls()).toHaveLength(1));
  });

  it('accepte aussi un fichier déjà scanné', async () => {
    mockPick.document = { uri: 'file:///piece.pdf', name: 'piece.pdf', mimeType: 'application/pdf', size: 2048 };
    apiMock.onPost('/provider/onboarding/documents').reply(201, { ok: true });

    renderStep();
    await ready();

    fireEvent.press(screen.getByLabelText('Choisir un fichier'));
    await waitFor(() => screen.getByText('piece.pdf'));

    fireEvent.press(screen.getByLabelText('Envoyer'));
    await waitFor(() => expect(uploadCalls()).toHaveLength(1));
  });

  /**
   * Ce que le serveur refuserait est refusé ici : inutile de faire remonter le fichier pour
   * recevoir un 422.
   */
  it('refuse un format non accepté sans rien envoyer', async () => {
    mockPick.document = { uri: 'file:///piece.docx', name: 'piece.docx', mimeType: 'application/msword', size: 500 };

    renderStep();
    await ready();
    fireEvent.press(screen.getByLabelText('Choisir un fichier'));

    await waitFor(() => expect(screen.getByText(/format non accepté/i)).toBeTruthy());
    expect(uploadCalls()).toHaveLength(0);
  });

  it('refuse un fichier au-delà de 10 Mo sans rien envoyer', async () => {
    mockPick.document = { uri: 'file:///gros.pdf', name: 'gros.pdf', mimeType: 'application/pdf', size: 11 * 1024 * 1024 };

    renderStep();
    await ready();
    fireEvent.press(screen.getByLabelText('Choisir un fichier'));

    await waitFor(() => expect(screen.getByText(/trop volumineux/i)).toBeTruthy());
    expect(uploadCalls()).toHaveLength(0);
  });

  /** Une annulation n'est pas une erreur : l'écran doit rester silencieux. */
  it("ne signale rien quand l'utilisateur annule la sélection", async () => {
    mockPick.image = null;

    renderStep();
    await ready();
    fireEvent.press(screen.getByLabelText('Prendre en photo'));

    await waitFor(() => expect(screen.getByLabelText('1 pièce(s) manquante(s)')).toBeTruthy());
    expect(screen.queryByLabelText('Envoyer')).toBeNull();
  });

  /** Un refus de permission doit être expliqué, pas avalé. */
  it('affiche le refus de permission', async () => {
    mockPick.image = new Error("Autorisez l'accès à l'appareil photo pour prendre votre pièce en photo.");

    renderStep();
    await ready();
    fireEvent.press(screen.getByLabelText('Prendre en photo'));

    await waitFor(() => expect(screen.getByText(/autorisez l'accès à l'appareil photo/i)).toBeTruthy());
  });

  it("signale un échec d'envoi sans valider l'étape", async () => {
    mockPick.image = A_VALID_FILE;
    apiMock.onPost('/provider/onboarding/documents').reply(500);

    const onDone = renderStep();
    await ready();

    fireEvent.press(screen.getByLabelText('Prendre en photo'));
    await waitFor(() => screen.getByText('piece.jpg'));
    fireEvent.press(screen.getByLabelText('Envoyer'));

    await waitFor(() => expect(screen.getByText(/l'envoi a échoué/i)).toBeTruthy());
    expect(onDone).not.toHaveBeenCalled();
  });

  /**
   * Le défaut central de l'ancien écran : il ne demandait que la pièce d'identité, si bien qu'un
   * dossier paraissait complet tout en étant impossible à approuver faute d'assurance.
   */
  it('demande aussi les pièces propres au métier', async () => {
    serveRequirements(IDENTITY, INSURANCE);

    renderStep();
    await ready();

    expect(screen.getByTestId('onboarding-document-insurance')).toBeTruthy();
    expect(screen.getByLabelText('2 pièce(s) manquante(s)')).toBeTruthy();
  });

  /** Un refus sans motif fait redéposer la même pièce, puis refuser une seconde fois. */
  it('affiche le motif de refus et rouvre le dépôt', async () => {
    serveRequirements({
      ...IDENTITY,
      document: {
        id: 1,
        type: 'identity_card',
        status: 'rejected',
        file_name: 'piece.jpg',
        rejection_reason: 'Document illisible : les quatre coins doivent être visibles.',
      },
    });

    renderStep();
    await ready();

    expect(screen.getByTestId('document-rejected-identity_card')).toBeTruthy();
    expect(screen.getByText(/quatre coins doivent être visibles/i)).toBeTruthy();
    // Une pièce refusée ne compte pas comme fournie.
    expect(screen.getByLabelText('1 pièce(s) manquante(s)')).toBeTruthy();
    expect(screen.getByLabelText('Prendre en photo')).toBeTruthy();
  });

  it('ne redemande pas une pièce déjà en cours de vérification', async () => {
    serveRequirements({
      ...IDENTITY,
      document: {
        id: 1,
        type: 'identity_card',
        status: 'pending_review',
        file_name: 'piece.jpg',
        rejection_reason: null,
      },
    });

    const onDone = renderStep();
    await ready();

    expect(screen.getByText(/en cours de vérification/i)).toBeTruthy();
    expect(screen.queryByLabelText('Prendre en photo')).toBeNull();

    fireEvent.press(screen.getByLabelText('Continuer'));
    expect(onDone).toHaveBeenCalled();
  });
});
