/**
 * Le client affiche un code que le prestataire scanne pour attester de sa présence.
 *
 * La géo-barrière fait basculer la session à 150 m de la porte : elle atteste d'une proximité,
 * pas d'une présence. Le code, lui, exige les deux appareils au même endroit.
 *
 * Ce qui est verrouillé ici : le code est demandé UNE seule fois à l'ouverture — chaque appel en
 * forge un neuf côté serveur et périme le précédent, si bien qu'une demande répétée le
 * remplacerait sous le nez du prestataire en train de le scanner. Et les six chiffres restent
 * lisibles, la caméra n'étant pas toujours coopérative.
 */
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';

const mockMutate = jest.fn();
const mockState: { data: unknown; isPending: boolean; isError: boolean } = {
  data: { session_id: 1, session_code: 'trip_x', code: '482951', expires_at: '2026-07-30T19:00:00Z' },
  isPending: false,
  isError: false,
};

const mockCompletionMutate = jest.fn();

jest.mock('@/tracking', () => ({
  useCompletionCode: () => ({
    mutate: mockCompletionMutate,
    data: { mission_id: 4, code: '731204', expires_at: '2026-07-30T21:00:00Z' },
    isPending: false,
    isError: false,
  }),
  usePresenceCode: () => ({
    mutate: mockMutate,
    data: mockState.data,
    isPending: mockState.isPending,
    isError: mockState.isError,
  }),
}));

jest.mock('react-native-qrcode-svg', () => {
  const { View } = require('react-native');

  return {
    __esModule: true,
    default: ({ value }: { value: string }) => <View testID="qr" accessibilityLabel={value} />,
  };
});

jest.mock('@/ui', () => {
  const { Text, TouchableOpacity } = require('react-native');

  return {
    Button: ({ label, onPress }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
  };
});

jest.mock('@/theme', () => ({
  colors: { brand: { 500: '#6366f1' }, surface: { 200: '#e2e8f0', 500: '#64748b', 600: '#475569', 900: '#0f172a' } },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24 },
  typography: { fontSize: { xs: 12, sm: 14, base: 16, lg: 18, '2xl': 24 }, fontWeight: { bold: '700' } },
  radius: { md: 14 },
  shadows: { xs: {} },
}));

import { PresenceCodeCard } from '@/screens/components/PresenceCodeCard';

beforeEach(() => {
  mockMutate.mockClear();
  mockCompletionMutate.mockClear();
  mockState.data = { session_id: 1, session_code: 'trip_x', code: '482951', expires_at: '2026-07-30T19:00:00Z' };
  mockState.isPending = false;
  mockState.isError = false;
});

describe('Code de présence côté client', () => {
  it('demande un code à l’ouverture', async () => {
    render(<PresenceCodeCard bookingId={7} />);

    await waitFor(() => expect(mockMutate).toHaveBeenCalledTimes(1));
  });

  /**
   * Défaut à éviter : demander le code périodiquement. Chaque appel en forge un neuf côté
   * serveur, ce qui invaliderait celui que le prestataire est en train de scanner.
   */
  it('ne redemande pas de code au re-rendu', async () => {
    const { rerender } = render(<PresenceCodeCard bookingId={7} />);
    await waitFor(() => expect(mockMutate).toHaveBeenCalledTimes(1));

    rerender(<PresenceCodeCard bookingId={7} />);
    rerender(<PresenceCodeCard bookingId={7} />);

    expect(mockMutate).toHaveBeenCalledTimes(1);
  });

  it('encode le code et la session dans le QR', () => {
    render(<PresenceCodeCard bookingId={7} />);

    // Le scanner du prestataire refuse une charge dont le type n'est pas le sien : l'étiquette
    // fait donc partie du contrat, au même titre que le code.
    expect(screen.getByLabelText(JSON.stringify({ t: 'brio.presence', v: 1, s: 1, c: '482951' }))).toBeTruthy();
  });

  /** Caméra sale, écran fêlé, lumière rasante : les chiffres doivent rester dictables. */
  it('affiche les six chiffres sous le QR', () => {
    render(<PresenceCodeCard bookingId={7} />);

    expect(screen.getByTestId('presence-code-digits')).toHaveTextContent('482951');
  });

  it('laisse redemander un code expiré', async () => {
    render(<PresenceCodeCard bookingId={7} />);
    await waitFor(() => expect(mockMutate).toHaveBeenCalledTimes(1));

    fireEvent.press(screen.getByLabelText('Générer un nouveau code'));

    expect(mockMutate).toHaveBeenCalledTimes(2);
  });

  it('n’affiche pas de QR tant que le code n’est pas arrivé', () => {
    mockState.data = undefined;
    mockState.isPending = true;

    render(<PresenceCodeCard bookingId={7} />);

    expect(screen.queryByTestId('qr')).toBeNull();
    expect(screen.getByTestId('presence-code-loading')).toBeTruthy();
  });

  it('reste lisible quand le serveur refuse', () => {
    mockState.data = undefined;
    mockState.isError = true;

    render(<PresenceCodeCard bookingId={7} />);

    expect(screen.queryByTestId('qr')).toBeNull();
    expect(screen.getByText('Code indisponible pour le moment.')).toBeTruthy();
  });

  /**
   * L'autre bout de la visite. L'étiquette du QR change avec lui : le scanner du prestataire
   * refuse un code de présence au moment de clôturer, et réciproquement.
   */
  it('affiche le code de fin avec sa propre étiquette', () => {
    render(<PresenceCodeCard bookingId={7} purpose="completion" />);

    expect(mockCompletionMutate).toHaveBeenCalledTimes(1);
    expect(mockMutate).not.toHaveBeenCalled();
    expect(screen.getByLabelText(JSON.stringify({ t: 'brio.completion', v: 1, s: 4, c: '731204' }))).toBeTruthy();
  });

  /** La clôture encaisse : le client doit lire ce qu'il valide, pas un texte d'arrivée. */
  it('annonce ce que la clôture déclenche', () => {
    render(<PresenceCodeCard bookingId={7} purpose="completion" />);

    expect(screen.getByText('Validez la fin de la prestation')).toBeTruthy();
  });
});
