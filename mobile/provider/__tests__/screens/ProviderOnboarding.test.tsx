/**
 * Le cockpit de vérification garde l'entrée de l'application.
 *
 * Sans cette garde, un compte tout juste créé atterrissait sur le tableau de bord où chaque appel
 * échouait en 403 — le middleware provider.approved le bloque tant qu'il n'est pas approuvé —
 * sans qu'on lui dise jamais quoi faire.
 *
 * L'écran présentait UNE étape à la fois, la première non terminée, dans un ordre imposé : un
 * prestataire bloqué sur une pièce manquante ne pouvait rien faire avancer d'autre. Chaque
 * vérification est maintenant une carte, ouvrable dans l'ordre voulu — sauf dépendance réelle du
 * moteur, qui verrouille en disant pourquoi.
 *
 * Ce qui est vérifié ici : l'ordre libre, le verrouillage motivé, l'état d'une pièce refusée, et
 * le fait qu'une erreur de chargement n'enferme pas l'utilisateur hors de son app.
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
  const { View, Text, TouchableOpacity, TextInput: RNTextInput } = require('react-native');
  const ReactLocal = require('react');
  return {
    Button: ({ label, onPress, loading }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label} disabled={loading}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    TextInput: ReactLocal.forwardRef(({ label, value, onChangeText }: any, ref: any) => (
      <RNTextInput ref={ref} accessibilityLabel={label} value={value} onChangeText={onChangeText} />
    )),
    Icon: () => <View />,
  };
});

jest.mock('@/theme', () => ({
  colors: {
    brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5' },
    success: { 50: '#ecfdf5', 600: '#059669', 700: '#047857' },
    warning: { 50: '#fffbeb', 700: '#b45309' },
    surface: { 100: '#f5f5f5', 200: '#e5e5e5', 300: '#d4d4d4', 400: '#a3a3a3', 500: '#737373', 600: '#525252', 700: '#404040' },
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
import { ProviderOnboardingScreen } from '@/screens/onboarding/ProviderOnboardingScreen';

const apiMock = new MockAdapter(apiClient);

/** Les dépendances réelles du parcours seedé : voir ProviderOnboardingJourneySeeder. */
const DEPENDS_ON: Record<string, string[]> = {
  profile_complete: [],
  contract_sign: ['profile_complete'],
  kyc_check: ['profile_complete'],
  document_upload: ['kyc_check'],
  skill_declare: ['profile_complete'],
};

/** Réponse de /v2/onboarding/me : `done` liste les étapes déjà terminées. */
function progress(done: string[] = []) {
  const codes = ['profile_complete', 'contract_sign', 'kyc_check', 'document_upload', 'skill_declare'];

  return {
    progress: { id: 1, status: 'in_progress', percent_complete: 0, current_step_code: null, completed_at: null },
    journey: { code: 'provider_default', name: 'Vérification prestataire', role: 'provider' },
    steps: codes.map((code, i) => ({
      id: i + 1,
      position: i + 1,
      code,
      label: code,
      description: null,
      step_type: code,
      required: true,
      is_skippable: false,
      depends_on: DEPENDS_ON[code],
      completion_status: done.includes(code) ? 'completed' : 'pending',
      completed_at: null,
    })),
  };
}

function serveDocuments(requirements: unknown[] = []) {
  apiMock.onGet('/provider/onboarding/documents').reply(200, { ok: true, requirements, documents: [] });
}

function renderScreen() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
  return render(
    <QueryClientProvider client={client}>
      <ProviderOnboardingScreen />
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  apiMock.reset();
  serveDocuments();
});

describe('Cockpit de vérification prestataire', () => {
  it('présente toutes les vérifications, pas seulement la première', async () => {
    apiMock.onGet('/v2/onboarding/me').reply(200, progress());

    renderScreen();

    await waitFor(() => expect(screen.getByTestId('onboarding-step-profile_complete')).toBeTruthy());
    expect(screen.getByTestId('onboarding-step-skill_declare')).toBeTruthy();
    // Les cartes sont fermées : le formulaire n'apparaît qu'à l'ouverture.
    expect(screen.queryByText('Vos coordonnées')).toBeNull();
  });

  it("ouvre le formulaire de la carte touchée", async () => {
    apiMock.onGet('/v2/onboarding/me').reply(200, progress());

    renderScreen();
    await waitFor(() => screen.getByTestId('onboarding-card-profile_complete'));

    fireEvent.press(screen.getByTestId('onboarding-card-profile_complete'));

    await waitFor(() => expect(screen.getByText('Vos coordonnées')).toBeTruthy());
  });

  /**
   * Le point de la refonte : plus d'ordre imposé. Les métiers ne dépendent que du profil, on
   * doit donc pouvoir les déclarer sans avoir signé le contrat.
   */
  it('laisse ouvrir une carte plus loin dans le parcours', async () => {
    apiMock.onGet('/v2/onboarding/me').reply(200, progress(['profile_complete']));

    renderScreen();
    await waitFor(() => screen.getByTestId('onboarding-card-skill_declare'));

    fireEvent.press(screen.getByTestId('onboarding-card-skill_declare'));

    await waitFor(() => expect(screen.getByText('Vos métiers')).toBeTruthy());
  });

  /** Une dépendance non satisfaite verrouille, et dit laquelle. */
  it('verrouille une carte dont la dépendance manque, en expliquant pourquoi', async () => {
    apiMock.onGet('/v2/onboarding/me').reply(200, progress());

    renderScreen();
    await waitFor(() => screen.getByTestId('onboarding-card-document_upload'));

    // document_upload dépend de kyc_check, non terminé.
    expect(screen.getByText(/Terminez d'abord : kyc_check/)).toBeTruthy();

    fireEvent.press(screen.getByTestId('onboarding-card-document_upload'));
    // La carte ne s'ouvre pas : elle mènerait à un formulaire voué à un 422.
    expect(screen.queryByText('Vos justificatifs')).toBeNull();
  });

  it('marque comme vérifiée une étape terminée et ne la rouvre pas', async () => {
    apiMock.onGet('/v2/onboarding/me').reply(200, progress(['profile_complete']));

    renderScreen();
    await waitFor(() => screen.getByTestId('onboarding-card-profile_complete'));

    fireEvent.press(screen.getByTestId('onboarding-card-profile_complete'));

    expect(screen.queryByText('Vos coordonnées')).toBeNull();
    expect(screen.getAllByText('Vérifié').length).toBeGreaterThan(0);
  });

  /**
   * Le parcours seul ne connaît que « déposé » : une pièce refusée y passerait pour en règle.
   * C'est l'état du document qui fait foi, avec son motif — sans quoi la même pièce est
   * redéposée puis refusée une seconde fois.
   */
  it('signale une pièce refusée avec son motif', async () => {
    apiMock.onGet('/v2/onboarding/me').reply(200, progress(['profile_complete', 'kyc_check']));
    serveDocuments([
      {
        type: 'identity_card',
        label: "Pièce d'identité",
        help: '',
        required: true,
        accepts: ['identity_card'],
        document: {
          id: 1,
          type: 'identity_card',
          status: 'rejected',
          file_name: 'piece.jpg',
          rejection_reason: 'Document illisible.',
        },
      },
    ]);

    renderScreen();

    await waitFor(() => expect(screen.getByText('À corriger')).toBeTruthy());
    expect(screen.getByTestId('onboarding-rejection-reason')).toBeTruthy();
  });

  it('annonce le dossier complet quand toutes les étapes sont terminées', async () => {
    apiMock.onGet('/v2/onboarding/me').reply(200, progress([
      'profile_complete', 'contract_sign', 'kyc_check', 'document_upload', 'skill_declare',
    ]));

    renderScreen();

    await waitFor(() => expect(screen.getByTestId('onboarding-complete')).toBeTruthy());
  });

  /**
   * Le serveur reste seul juge : il revalide chaque complétion. Son refus doit être montré tel
   * quel, plutôt qu'un message générique qui laisserait l'utilisateur deviner.
   */
  it("affiche le refus du serveur plutôt qu'un message générique", async () => {
    apiMock.onGet('/v2/onboarding/me').reply(200, progress(['profile_complete']));
    apiMock.onPost('/v2/onboarding/steps/2/complete').reply(422, {
      ok: false,
      // Forme réelle : ValidationException::withMessages normalise chaque message en tableau.
      errors: { contract: ['Version contrat acceptée (0.9) ≠ version requise (1.0).'] },
    });

    renderScreen();
    await waitFor(() => screen.getByTestId('onboarding-card-contract_sign'));
    fireEvent.press(screen.getByTestId('onboarding-card-contract_sign'));

    await waitFor(() => screen.getByTestId('onboarding-accept-contract'));
    fireEvent.press(screen.getByTestId('onboarding-accept-contract'));
    fireEvent.press(screen.getByLabelText('Signer et continuer'));

    await waitFor(() => expect(screen.getByText(/version requise/i)).toBeTruthy());
  });

  it("n'appelle pas le serveur tant que le contrat n'est pas accepté", async () => {
    apiMock.onGet('/v2/onboarding/me').reply(200, progress(['profile_complete']));

    renderScreen();
    await waitFor(() => screen.getByTestId('onboarding-card-contract_sign'));
    fireEvent.press(screen.getByTestId('onboarding-card-contract_sign'));

    await waitFor(() => screen.getByLabelText('Signer et continuer'));
    fireEvent.press(screen.getByLabelText('Signer et continuer'));

    await waitFor(() => expect(screen.getByText(/devez accepter le contrat/i)).toBeTruthy());
    expect(apiMock.history['post']!.filter(c => c.url?.includes('/complete'))).toHaveLength(0);
  });

  /** Une panne de chargement ne doit pas laisser un écran vide sans issue. */
  it('propose de réessayer quand la progression ne se charge pas', async () => {
    apiMock.onGet('/v2/onboarding/me').reply(500);

    renderScreen();

    await waitFor(() => expect(screen.getByTestId('onboarding-error')).toBeTruthy());
    expect(screen.getByLabelText('Réessayer')).toBeTruthy();
  });
});
