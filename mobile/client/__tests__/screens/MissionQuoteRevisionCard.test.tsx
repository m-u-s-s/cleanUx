import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { MissionQuoteRevisionCard } from '@/screens/components/MissionQuoteRevisionCard';

/**
 * LE NOUVEAU DEVIS VU DU SALON.
 *
 * Deux garanties se testent ici, et ce sont celles qui décident si le client accepte ou refuse par
 * réflexe : les DEUX totaux sont montrés, et la remise est NOMMÉE. Un chiffre plus élevé sans
 * explication sur la réduction disparue se refuse sans être lu.
 *
 * Et le refus ouvre une QUESTION : « continuez » et « arrêtez » n'ont pas le même coût, ce n'est
 * pas à l'application de trancher.
 */

let mockRevision: unknown = null;
const mockRepondre = jest.fn();

jest.mock('@/booking/onsite', () => ({
  useRevisionDeDevis: () => ({ data: mockRevision }),
  useRepondreALaRevision: () => ({ mutate: mockRepondre, isPending: false }),
}));

jest.mock('@/ui', () => {
  const { Text, TouchableOpacity, View } = require('react-native');

  return {
    Button: ({ label, onPress, testID }: any) => (
      <TouchableOpacity onPress={onPress} testID={testID}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    // Traversante : elle rend son titre et ses enfants. Un bouchon qui les avalerait ferait
    // passer au vert une carte devenue vide.
    CarteDeMission: ({ titre, children, testID }: any) => (
      <View testID={testID}>
        {titre ? <Text>{titre}</Text> : null}
        {children}
      </View>
    ),
  };
});

const REVISION = {
  id: 9,
  status: 'proposed',
  awaiting_client: true,
  original_total: 50,
  revised_total: 300,
  currency: 'EUR',
  breakdown: { promo: { code: 'REMISE20', discount_cents: 6000 } },
  reason_text: 'Deux cents mètres carrés, pas vingt.',
  evidence_media_ids: [1],
  window_closes_at: null,
};

describe('Nouveau devis, côté client', () => {
  beforeEach(() => {
    mockRevision = null;
    mockRepondre.mockClear();
  });

  /** LE TÉMOIN : sans proposition vivante, la carte n'existe pas. */
  it('ne s’affiche pas sans révision en attente', () => {
    render(<MissionQuoteRevisionCard bookingId={77} />);

    expect(screen.queryByTestId('revision-de-devis')).toBeNull();
  });

  it('montre les deux totaux et nomme la remise', () => {
    mockRevision = REVISION;
    render(<MissionQuoteRevisionCard bookingId={77} />);

    expect(screen.getByTestId('revision-de-devis')).toBeTruthy();
    expect(screen.getByText(/Devis d’origine/)).toBeTruthy();
    expect(screen.getByText(/REMISE20/)).toBeTruthy();
    expect(screen.getByText('Deux cents mètres carrés, pas vingt.')).toBeTruthy();
  });

  it('accepte en un geste', () => {
    mockRevision = REVISION;
    render(<MissionQuoteRevisionCard bookingId={77} />);

    fireEvent.press(screen.getByTestId('revision-accepter'));

    expect(mockRepondre).toHaveBeenCalledWith(
      expect.objectContaining({ revisionId: 9, accepte: true }),
      expect.anything(),
    );
  });

  /**
   * LE REFUS N'ENVOIE RIEN TOUT DE SUITE : il ouvre la question. Envoyer directement choisirait à
   * la place du client entre continuer et arrêter, qui n'ont pas le même coût pour lui.
   */
  it('ouvre le choix avant d’envoyer un refus', () => {
    mockRevision = REVISION;
    render(<MissionQuoteRevisionCard bookingId={77} />);

    fireEvent.press(screen.getByTestId('revision-refuser'));

    expect(mockRepondre).not.toHaveBeenCalled();
    expect(screen.getByTestId('revision-choix')).toBeTruthy();
  });

  it('transmet la décision du client', () => {
    mockRevision = REVISION;
    render(<MissionQuoteRevisionCard bookingId={77} />);

    fireEvent.press(screen.getByTestId('revision-refuser'));
    fireEvent.press(screen.getByTestId('revision-arreter'));

    expect(mockRepondre).toHaveBeenCalledWith(
      expect.objectContaining({ revisionId: 9, accepte: false, decision: 'stop' }),
      expect.anything(),
    );
  });
});
