/**
 * LE RETRAIT DU CONSENTEMENT DOIT ETRE EXERCABLE, PAS SEULEMENT ECRIT.
 *
 * `useWithdrawFaceConsent` et la route `POST /provider/face-check/consent/withdraw` existaient
 * depuis le debut ; l'ecran, non. Le hook n'avait AUCUN appelant : le droit etait code et
 * inaccessible.
 *
 * Ce fichier verifie les deux moities — l'ecran appelle bien le serveur, ET une porte y mene.
 * La seconde compte autant : un ecran qu'aucune navigation n'atteint est le mode d'echec
 * dominant de ce depot.
 */
import React from 'react';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { Alert } from 'react-native';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const mockPost = jest.fn();

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ goBack: jest.fn(), navigate: jest.fn() }),
}));

jest.mock('@/api', () => ({
  __esModule: true,
  apiClient: {
    get: jest.fn().mockResolvedValue({ data: { data: { required: true, consent_version: 'v2' } } }),
    post: (...args: unknown[]) => mockPost(...args),
  },
}));

import { FaceConsentScreen } from '@/screens/faceCheck/FaceConsentScreen';

const afficher = () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <FaceConsentScreen />
    </QueryClientProvider>,
  );
};

describe('retrait du consentement au controle facial', () => {
  beforeEach(() => {
    mockPost.mockReset();
    mockPost.mockResolvedValue({ data: { data: { message: 'ok' } } });
  });

  it('annonce la consequence AVANT d’appeler le serveur', async () => {
    const alerte = jest.spyOn(Alert, 'alert').mockImplementation(() => {});

    const { getByText } = afficher();

    fireEvent.press(getByText('Retirer mon consentement'));

    // La confirmation s'ouvre, et rien n'est encore parti.
    expect(alerte).toHaveBeenCalled();
    expect(mockPost).not.toHaveBeenCalled();

    // Le texte de la confirmation dit ce qu'on perd.
    const [, corps] = alerte.mock.calls[0] as [string, string];

    expect(corps).toContain('supprimé');
    expect(corps).toContain('plus intervenir');

    alerte.mockRestore();
  });

  it('appelle la route de retrait quand on confirme', async () => {
    const alerte = jest.spyOn(Alert, 'alert').mockImplementation((_t, _m, boutons) => {
      // Le second bouton est l'action destructrice ; le premier annule.
      const destructif = (boutons ?? []).find(b => b.style === 'destructive');

      destructif?.onPress?.();
    });

    const { getByText } = afficher();

    fireEvent.press(getByText('Retirer mon consentement'));

    await waitFor(() => expect(mockPost).toHaveBeenCalledWith(
      '/provider/face-check/consent/withdraw',
      { confirm: true },
    ));

    alerte.mockRestore();
  });

  /*
   * TEMOIN. Sans lui, le test ci-dessus passerait au vert si `mockPost` etait appele par
   * n'importe quoi d'autre : il mesurerait un appel, pas LE bon.
   */
  it('temoin : rien ne part tant qu’on n’a pas confirme', async () => {
    const alerte = jest.spyOn(Alert, 'alert').mockImplementation((_t, _m, boutons) => {
      const annuler = (boutons ?? []).find(b => b.style === 'cancel');

      annuler?.onPress?.();
    });

    const { getByText } = afficher();

    fireEvent.press(getByText('Retirer mon consentement'));

    await waitFor(() => expect(alerte).toHaveBeenCalled());
    expect(mockPost).not.toHaveBeenCalled();

    alerte.mockRestore();
  });

  it('est joignable : la route est montee ET une porte y mene', () => {
    const src = join(__dirname, '..', '..', 'src');
    const lire = (p: string) => readFileSync(join(src, p), 'utf8');

    // `tsc` et jest ne disent rien de la joignabilite : seule la navigation le dit.
    expect(lire('navigation/RootNavigator.tsx')).toContain('name="FaceConsent"');
    expect(lire('navigation/types.ts')).toMatch(/FaceConsent\s*:/);
    expect(lire('screens/ProfileScreen.tsx')).toContain("screen: 'FaceConsent'");
  });
});
