/**
 * Le composant de pièce jointe : image affichée, fichier annoncé et ouvrable, rien sans pièce.
 */
import React from 'react';
import { Linking } from 'react-native';
import { fireEvent, render } from '@testing-library/react-native';
import { PieceJointeDuMessage } from '../PieceJointeDuMessage';

const LIEN = 'https://brio.test/api/v2/chat/messages/7/attachment/appareil?viewer=3&signature=abc';

describe('la pièce jointe d’un message', () => {
  beforeEach(() => {
    jest.spyOn(Linking, 'openURL').mockResolvedValue(true);
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it('affiche une image avec l’adresse signée du serveur', () => {
    const { getByTestId } = render(
      <PieceJointeDuMessage piece={{ url: LIEN, mime_type: 'image/jpeg', size_bytes: 90_000 }} />
    );

    expect(getByTestId('piece-jointe-image').props.source).toEqual({ uri: LIEN });
    expect(getByTestId('piece-jointe-image-ouvrir')).toBeTruthy();
  });

  it('annonce ce qui n’est pas une image, avec son poids', () => {
    const { getByTestId, getByText } = render(
      <PieceJointeDuMessage piece={{ url: LIEN, mime_type: 'application/pdf', size_bytes: 2_097_152 }} />
    );

    expect(getByTestId('piece-jointe-fichier')).toBeTruthy();
    expect(getByText('Document PDF')).toBeTruthy();
    expect(getByText('2.0 Mo')).toBeTruthy();
  });

  it('ouvre la pièce au doigt', () => {
    const { getByTestId } = render(
      <PieceJointeDuMessage piece={{ url: LIEN, mime_type: 'application/pdf', size_bytes: null }} />
    );

    fireEvent.press(getByTestId('piece-jointe-fichier'));

    expect(Linking.openURL).toHaveBeenCalledWith(LIEN);
  });

  it('tait le poids quand le serveur ne le connaît pas', () => {
    const { queryByText, getByText } = render(
      <PieceJointeDuMessage piece={{ url: LIEN, mime_type: 'audio/mpeg', size_bytes: null }} />
    );

    expect(getByText('Message vocal')).toBeTruthy();
    expect(queryByText('0 Ko')).toBeNull();
  });

  /** LE TÉMOIN : un message sans pièce ne rend rien du tout — pas une bulle vide de plus. */
  it('ne rend rien sans pièce jointe', () => {
    const { queryByTestId, rerender } = render(<PieceJointeDuMessage piece={null} />);

    expect(queryByTestId('piece-jointe-image')).toBeNull();
    expect(queryByTestId('piece-jointe-fichier')).toBeNull();

    rerender(<PieceJointeDuMessage />);

    expect(queryByTestId('piece-jointe-image')).toBeNull();
    expect(queryByTestId('piece-jointe-fichier')).toBeNull();
  });
});
