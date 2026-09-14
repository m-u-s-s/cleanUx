import React from 'react';
import { render, screen } from '@testing-library/react-native';

import { Icon } from '../Icon';

/**
 * LE REPLI ANNONÇAIT LE NOM IONICONS ANGLAIS.
 *
 * `accessibilityLabel={accessibilityLabel ?? name}` : les 81 `<Icon/>` du dépôt n'en passent
 * aucune, et un lecteur d'écran francophone entendait « chevron-forward », « person-outline »,
 * « alert-circle-outline ». Une icône décorative se masque ; elle ne s'épelle pas.
 */
describe('Icon — ce que le lecteur d’écran entend', () => {
  it('se tait quand aucune étiquette n’est donnée', () => {
    render(<Icon name="chevron-forward" />);

    expect(screen.queryByLabelText('chevron-forward')).toBeNull();
  });

  it('est masquée aux technologies d’assistance quand elle est décorative', () => {
    render(<Icon name="person-outline" />);

    const rendus = screen.UNSAFE_root.findAll(
      n => n.props?.importantForAccessibility === 'no-hide-descendants',
    );

    expect(rendus.length).toBeGreaterThan(0);
  });

  /** LE TÉMOIN POSITIF : une icône qui porte du sens reste annoncée. */
  it('annonce l’étiquette donnée par l’appelant', () => {
    render(<Icon name="trash-outline" accessibilityLabel="Supprimer la pièce jointe" />);

    expect(screen.getByLabelText('Supprimer la pièce jointe')).toBeTruthy();
  });

  it('n’annonce jamais le nom du glyphe à la place de l’étiquette', () => {
    render(<Icon name="alert-circle-outline" accessibilityLabel="Attention" />);

    expect(screen.queryByLabelText('alert-circle-outline')).toBeNull();
  });
});
