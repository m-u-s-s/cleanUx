import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { AnnulerLaMissionSheet } from '@brio/shared';

/**
 * ANNULER, SUR MOBILE.
 *
 * Deux garanties se testent ici, et ce sont celles qui décident si quelqu'un fait le bon geste :
 *
 *   - l'AIGUILLAGE se dit AVANT, et ne propose AUCUN bouton de confirmation. « Le travail ne
 *     correspond pas » renvoie vers le nouveau devis ; laisser le bouton reviendrait à offrir le
 *     mauvais geste à côté du bon ;
 *   - le MONTANT dépend du motif choisi, parce qu'un motif exempté met les frais à zéro.
 */

let mockQuestions: unknown[] = [];
let mockDevis: unknown = null;
const mockAnnuler = jest.fn();

jest.mock('@brio/shared/cancellation', () => ({
  useQuestionnaireDAnnulation: () => ({ data: mockQuestions }),
  useDevisDAnnulation: () => ({ data: mockDevis }),
  useAnnulerLaReservation: () => ({ mutate: mockAnnuler, isPending: false }),
}));

const QUESTION_ORDINAIRE = {
  code: 'client_cancel_why',
  label: 'Que se passe-t-il ?',
  help_text: null,
  options: [
    {
      code: 'client_no_longer_needed',
      label: 'Je n’ai plus besoin de ce service',
      outcome: 'cancel',
      requires_text: false,
      requires_proof: false,
      redirects: false,
    },
    {
      code: 'provider_scope_mismatch',
      label: 'Le travail ne correspond pas',
      outcome: 'redirect_requote',
      requires_text: true,
      requires_proof: false,
      redirects: true,
    },
  ],
};

describe('Annuler la mission, sur mobile', () => {
  beforeEach(() => {
    mockQuestions = [QUESTION_ORDINAIRE];
    mockDevis = null;
    mockAnnuler.mockClear();
  });

  const rendre = () =>
    render(
      <AnnulerLaMissionSheet
        audience="client"
        bookingId={77}
        onAnnulee={jest.fn()}
        onFermer={jest.fn()}
      />,
    );

  it('n’offre aucune confirmation tant qu’aucun motif n’est choisi', () => {
    rendre();

    expect(screen.queryByTestId('confirmer-annulation')).toBeNull();
  });

  it('annule avec le motif choisi', () => {
    rendre();

    fireEvent.press(screen.getByTestId('motif-client_no_longer_needed'));
    fireEvent.press(screen.getByTestId('confirmer-annulation'));

    expect(mockAnnuler).toHaveBeenCalledWith(
      expect.objectContaining({ reasonCode: 'client_no_longer_needed' }),
      expect.anything(),
    );
  });

  /**
   * L'AIGUILLAGE NE PROPOSE PAS D'ANNULER. Laisser le bouton reviendrait à offrir le mauvais geste
   * juste à côté du bon, au moment précis où l'on vient d'expliquer lequel est le bon.
   */
  it('renvoie ailleurs sans offrir d’annuler', () => {
    rendre();

    fireEvent.press(screen.getByTestId('motif-provider_scope_mismatch'));

    expect(screen.getByTestId('aiguillage')).toBeTruthy();
    expect(screen.getByText(/nouveau devis/)).toBeTruthy();
    expect(screen.queryByTestId('confirmer-annulation')).toBeNull();
    expect(mockAnnuler).not.toHaveBeenCalled();
  });

  it('montre les frais quand il y en a', () => {
    mockDevis = {
      fee_amount_cents: 1200,
      refund_amount_cents: 10800,
      currency: 'EUR',
      exempt_applied: false,
      tier_label: null,
      warnings: [],
    };
    rendre();

    fireEvent.press(screen.getByTestId('motif-client_no_longer_needed'));

    expect(screen.getByTestId('devis-annulation')).toBeTruthy();
    expect(screen.getByText(/Frais d’annulation/)).toBeTruthy();
  });

  /** LE TÉMOIN : un motif exonérant le dit, et n'annonce aucun frais. */
  it('annonce la gratuité quand le motif exonère', () => {
    mockDevis = {
      fee_amount_cents: 0,
      refund_amount_cents: 12000,
      currency: 'EUR',
      exempt_applied: true,
      tier_label: null,
      warnings: [],
    };
    rendre();

    fireEvent.press(screen.getByTestId('motif-client_no_longer_needed'));

    expect(screen.getByText('Aucun frais d’annulation.')).toBeTruthy();
    expect(screen.getByText('Motif exonérant appliqué.')).toBeTruthy();
  });
});
