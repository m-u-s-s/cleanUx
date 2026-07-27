import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react-native';

const mockStatus = { current: 'online' };
const mockSetPresenceStatus = jest.fn();
const mockGoOnline = jest.fn();

jest.mock('@/presence', () => ({
  // PRESENCE_LABELS/PRESENCE_VARIANTS viennent du vrai module (source unique partagée avec
  // PresenceToggle) : on ne duplique pas les chaînes françaises ici, seul usePresence est mocké.
  ...jest.requireActual('../../src/presence/labels'),
  usePresence: () => ({
    status: mockStatus.current,
    error: null,
    isPending: false,
    setPresenceStatus: mockSetPresenceStatus,
    goOnline: mockGoOnline,
  }),
}));

// PulseDot mocké pour exposer sa prop `variant` dans le DOM de test (elle n'est sinon visible
// nulle part : le vrai PulseDot ne rend qu'une couleur de fond via reanimated).
jest.mock('@/ui', () => {
  const { View } = require('react-native');
  return {
    PulseDot: ({ variant }: { variant: string }) => <View testID={`pulse-${variant}`} />,
  };
});

import { PresencePill } from '@/screens/components/PresencePill';

beforeEach(() => {
  mockSetPresenceStatus.mockClear();
  mockGoOnline.mockClear();
});

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

  // 'busy' n'est couvert par aucun des deux cas ci-dessus. Le libellé ('Occupé') est vérifié
  // ici, et la variante ('urgent') est vérifiée séparément plus bas via le PulseDot mocké : la
  // variante n'a pas de représentation textuelle, donc rien d'autre ne la met en défaut.
  it('affiche le libellé du statut occupé', () => {
    mockStatus.current = 'busy';
    render(<PresencePill onPress={jest.fn()} />);
    expect(screen.getByText('Occupé')).toBeTruthy();
  });

  // Ces trois cas font échouer le test si `PRESENCE_VARIANTS` (dans src/presence/labels.ts)
  // associe le mauvais variant à un statut — voir task-8-report.md pour la preuve par mutation
  // (mapping cassé volontairement, run rouge capturé, puis restauré).
  it.each([
    ['online', 'success'],
    ['on_break', 'primary'],
    ['busy', 'urgent'],
  ] as const)('pour le statut %s, associe la variante %s au PulseDot', (status, expectedVariant) => {
    mockStatus.current = status;
    render(<PresencePill onPress={jest.fn()} />);
    expect(screen.getByTestId(`pulse-${expectedVariant}`)).toBeTruthy();
  });

  it('ne masque le PulseDot que pour le statut hors ligne', () => {
    mockStatus.current = 'offline';
    render(<PresencePill onPress={jest.fn()} />);
    expect(screen.queryByTestId(/^pulse-/)).toBeNull();
  });

  // Invariant central du composant : PresencePill est un affichage seul, le seul chemin
  // d'écriture reste PresenceToggle. Si une future édition faisait appeler setPresenceStatus
  // ou goOnline depuis ici, seule une lecture du code le remarquerait aujourd'hui — ce test le
  // fait échouer en CI à la place.
  it("n'écrit jamais le statut au tap : setPresenceStatus et goOnline ne sont jamais appelés", () => {
    mockStatus.current = 'online';
    render(<PresencePill onPress={jest.fn()} />);
    fireEvent.press(screen.getByTestId('presence-pill'));
    expect(mockSetPresenceStatus).not.toHaveBeenCalled();
    expect(mockGoOnline).not.toHaveBeenCalled();
  });

  it("expose un accessibilityLabel qui explique l'action du tap (pas seulement le statut)", () => {
    mockStatus.current = 'online';
    render(<PresencePill onPress={jest.fn()} />);
    expect(screen.getByLabelText(/Toucher pour ouvrir les actions/)).toBeTruthy();
  });
});
