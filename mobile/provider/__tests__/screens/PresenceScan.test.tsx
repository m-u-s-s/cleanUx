/**
 * Le prestataire confirme sa présence en scannant le code du client.
 *
 * La géo-barrière bascule la session à 150 m de la porte : elle atteste d'une proximité, pas
 * d'une présence. Lire le code sur l'écran du client exige les deux appareils au même endroit.
 *
 * Ce qui est verrouillé ici : un QR quelconque ne déclenche aucune tentative — le serveur
 * plafonne les essais, et les brûler sur un code-barres de paquet de céréales rendrait le vrai
 * code inutilisable. Et le motif du refus vient du serveur : « expiré » et « déjà utilisé » ne se
 * corrigent pas de la même façon.
 */
import React from 'react';
import { act, render, screen, waitFor } from '@testing-library/react-native';
import { Alert } from 'react-native';

const mockConfirm = jest.fn();
const mockGoBack = jest.fn();

jest.mock('@/tracking', () => ({
  useConfirmPresence: () => ({ mutate: mockConfirm, isPending: false }),
}));

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ goBack: mockGoBack, navigate: jest.fn() }),
}));

/** Pilote l'état de la permission caméra depuis chaque test. */
const cameraState: { permission: { granted: boolean } | null; onScan: ((e: { data: string }) => void) | null } = {
  permission: { granted: true },
  onScan: null,
};

jest.mock('expo-camera', () => {
  const { View } = require('react-native');

  return {
    useCameraPermissions: () => [cameraState.permission, jest.fn()],
    CameraView: ({ onBarcodeScanned, children }: any) => {
      cameraState.onScan = onBarcodeScanned ?? null;

      return <View testID="camera">{children}</View>;
    },
  };
});

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity } = require('react-native');

  return {
    Screen: ({ children, testID }: any) => <View testID={testID}>{children}</View>,
    Button: ({ label, onPress }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
  };
});

jest.mock('@/theme', () => ({
  colors: { surface: { 600: '#475569', 900: '#0f172a' } },
  spacing: { md: 16, lg: 24 },
  typography: { fontSize: { sm: 14, base: 16, xl: 20 }, fontWeight: { bold: '700', semibold: '600' } },
  radius: { md: 14 },
}));

import { PresenceScanScreen } from '@/screens/PresenceScanScreen';

const route = { params: { sessionId: 42 } } as any;

beforeEach(() => {
  mockConfirm.mockReset();
  mockGoBack.mockReset();
  cameraState.permission = { granted: true };
  cameraState.onScan = null;
  jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);
});

afterEach(() => jest.restoreAllMocks());

describe('Scan de présence côté prestataire', () => {
  it('envoie le code lu dans un QR CleanUx', () => {
    render(<PresenceScanScreen route={route} navigation={{} as any} />);

    act(() => cameraState.onScan?.({ data: JSON.stringify({ t: 'cleanux.presence', v: 1, s: 42, c: '482951' }) }));

    expect(mockConfirm).toHaveBeenCalledWith({ code: '482951' }, expect.anything());
  });

  /**
   * Le serveur brûle le code après cinq essais. Tenter sa chance sur un QR étranger consommerait
   * ce budget et rendrait le vrai code inutilisable.
   */
  it('ne tente rien avec un QR étranger', () => {
    render(<PresenceScanScreen route={route} navigation={{} as any} />);

    act(() => cameraState.onScan?.({ data: 'https://exemple.test/promo' }));

    expect(mockConfirm).not.toHaveBeenCalled();
  });

  it('ne tente rien avec un JSON d’un autre type', () => {
    render(<PresenceScanScreen route={route} navigation={{} as any} />);

    act(() => cameraState.onScan?.({ data: JSON.stringify({ t: 'autre.chose', c: '482951' }) }));

    expect(mockConfirm).not.toHaveBeenCalled();
  });

  /** « Expiré » et « déjà utilisé » n'appellent pas le même geste : le motif doit remonter. */
  it('affiche le motif de refus renvoyé par le serveur', async () => {
    mockConfirm.mockImplementation((_vars, opts) =>
      opts.onError({ response: { data: { errors: { code: ['Ce code a expiré.'] } } } }),
    );

    render(<PresenceScanScreen route={route} navigation={{} as any} />);
    act(() => cameraState.onScan?.({ data: JSON.stringify({ t: 'cleanux.presence', v: 1, s: 42, c: '482951' }) }));

    await waitFor(() =>
      expect(Alert.alert).toHaveBeenCalledWith('Code refusé', 'Ce code a expiré.'),
    );
  });

  it('revient en arrière une fois la présence confirmée', async () => {
    mockConfirm.mockImplementation((_vars, opts) => opts.onSuccess({ id: 42 }));
    (Alert.alert as jest.Mock).mockImplementation((_t, _m, buttons?: any[]) => buttons?.[0]?.onPress?.());

    render(<PresenceScanScreen route={route} navigation={{} as any} />);
    act(() => cameraState.onScan?.({ data: JSON.stringify({ t: 'cleanux.presence', v: 1, s: 42, c: '482951' }) }));

    await waitFor(() => expect(mockGoBack).toHaveBeenCalled());
  });

  it('demande la caméra plutôt que d’échouer en silence', () => {
    cameraState.permission = { granted: false };

    render(<PresenceScanScreen route={route} navigation={{} as any} />);

    expect(screen.getByTestId('presence-scan-denied')).toBeTruthy();
    expect(screen.getByLabelText('Autoriser la caméra')).toBeTruthy();
  });
});
