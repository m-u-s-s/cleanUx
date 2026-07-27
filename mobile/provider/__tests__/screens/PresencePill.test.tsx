import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react-native';

const mockStatus = { current: 'online' };
jest.mock('@/presence', () => ({
  usePresence: () => ({ status: mockStatus.current, error: null, isPending: false, setPresenceStatus: jest.fn(), goOnline: jest.fn() }),
}));

import { PresencePill } from '@/screens/components/PresencePill';

describe('PresencePill', () => {
  it('affiche le libellé du statut courant', () => {
    mockStatus.current = 'on_break';
    render(<PresencePill onPress={jest.fn()} />);
    expect(screen.getByText('En pause')).toBeTruthy();
  });

  it('appelle onPress au tap', () => {
    const onPress = jest.fn();
    mockStatus.current = 'online';
    render(<PresencePill onPress={onPress} />);
    fireEvent.press(screen.getByTestId('presence-pill'));
    expect(onPress).toHaveBeenCalled();
  });

  // 'busy' n'est couvert par aucun des deux cas ci-dessus : c'est le seul statut dont le
  // libellé/variante ('Occupé' / urgent) resterait sans filet — contrairement à 'offline',
  // qui est l'état par défaut au chargement et serait donc repéré immédiatement en QA
  // manuelle, 'busy' est posé automatiquement par BookingObserver en cours de mission et
  // une régression silencieuse (libellé inversé avec 'on_break' par ex.) passerait inaperçue.
  it('affiche le libellé du statut occupé', () => {
    mockStatus.current = 'busy';
    render(<PresencePill onPress={jest.fn()} />);
    expect(screen.getByText('Occupé')).toBeTruthy();
  });
});
