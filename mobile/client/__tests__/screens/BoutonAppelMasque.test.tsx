import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { Alert, Linking } from 'react-native';
import { BoutonAppelMasque } from '@brio/shared';

/**
 * APPELER PAR LE NUMÉRO RELAIS.
 *
 * Le service existait depuis longtemps et n'était appelé de NULLE PART — ni mobile, ni web. Ce test
 * porte sur les deux garanties qui rendent le bouton utile :
 *
 *   - il compose le numéro RELAIS, jamais celui de l'autre ;
 *   - quand la ligne est fermée, il reste visible et DIT pourquoi. Un bouton qui s'évapore fait
 *     chercher, puis appeler le support.
 */

let mockLigne: unknown = null;

jest.mock('@brio/shared/cancellation/appelMasque', () => ({
  useLigneMasqueeClient: () => ({ data: mockLigne }),
  useLigneMasqueePrestataire: () => ({ data: mockLigne }),
}));

describe('Bouton d’appel masqué', () => {
  beforeEach(() => {
    mockLigne = null;
    // `restoreAllMocks` ne remet pas a zero l'historique des espions poses sur les objets partages
    // de React Native : sans ce nettoyage, l'appel du test precedent compte encore ici.
    jest.clearAllMocks();
    jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);
    jest.spyOn(Linking, 'openURL').mockResolvedValue(true as never);
  });

  afterEach(() => jest.restoreAllMocks());

  it('compose le numéro relais, jamais celui du prestataire', () => {
    mockLigne = {
      available: true,
      proxy_number: '+3228080808',
      masked_peer_number: '•••• 42',
      expires_at: null,
      message: null,
    };

    render(<BoutonAppelMasque role="client" bookingId={77} />);
    fireEvent.press(screen.getByTestId('appeler'));

    expect(Linking.openURL).toHaveBeenCalledWith('tel:+3228080808');
  });

  /** LE TÉMOIN : ligne fermée, le bouton reste et explique. */
  it('reste visible et dit pourquoi quand la ligne est fermée', () => {
    mockLigne = {
      available: false,
      proxy_number: null,
      masked_peer_number: null,
      expires_at: null,
      message: 'Aucun prestataire n’est encore assigné.',
    };

    render(<BoutonAppelMasque role="client" bookingId={77} />);
    fireEvent.press(screen.getByTestId('appeler'));

    expect(Linking.openURL).not.toHaveBeenCalled();
    expect(Alert.alert).toHaveBeenCalledWith(
      'Appel indisponible',
      'Aucun prestataire n’est encore assigné.',
    );
  });
});
