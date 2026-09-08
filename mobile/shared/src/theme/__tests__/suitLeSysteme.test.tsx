import React from 'react';
import { Text } from 'react-native';
import { Appearance } from 'react-native';
import { render, act } from '@testing-library/react-native';
import { useColorScheme } from '../useColorScheme';

jest.mock('@react-native-async-storage/async-storage', () => ({
  __esModule: true,
  default: { getItem: jest.fn().mockResolvedValue(null), setItem: jest.fn().mockResolvedValue(undefined) },
}));

jest.mock('../../api/client', () => ({ __esModule: true, apiClient: { post: jest.fn() } }));

function Sonde() {
  const { colorScheme } = useColorScheme();

  return <Text testID="schema">{colorScheme}</Text>;
}

/**
 * LE THÈME DE L'APPAREIL CHANGE PENDANT QUE L'APPLICATION TOURNE.
 *
 * `Appearance.getColorScheme()` était lu à chaque rendu, mais rien ne provoquait ce rendu :
 * `listeners` ne porte que le choix ENREGISTRÉ. En mode « système », basculer le téléphone en
 * sombre ne changeait donc rien tant qu'on ne relançait pas l'application.
 *
 * Mesuré sur l'émulateur le 2026-09-08 : Android passé en sombre, l'application restée claire.
 */
describe('useColorScheme suit le système', () => {
  let ecouteur: (() => void) | null = null;

  beforeEach(() => {
    ecouteur = null;
    jest.spyOn(Appearance, 'addChangeListener').mockImplementation((cb: any) => {
      ecouteur = cb;

      return { remove: jest.fn() } as any;
    });
  });

  afterEach(() => jest.restoreAllMocks());

  it('rend le schéma du système au démarrage', () => {
    jest.spyOn(Appearance, 'getColorScheme').mockReturnValue('light');

    const { getByTestId } = render(<Sonde />);

    expect(getByTestId('schema').props.children).toBe('light');
  });

  it('suit la bascule du système sans redémarrage', () => {
    const lecture = jest.spyOn(Appearance, 'getColorScheme').mockReturnValue('light');

    const { getByTestId } = render(<Sonde />);
    expect(getByTestId('schema').props.children).toBe('light');

    // L'appareil bascule : le système prévient, l'écran doit suivre.
    lecture.mockReturnValue('dark');
    act(() => ecouteur?.());

    expect(getByTestId('schema').props.children).toBe('dark');
  });

  it('témoin : sans notification, rien ne bouge — c’était tout le défaut', () => {
    const lecture = jest.spyOn(Appearance, 'getColorScheme').mockReturnValue('light');

    const { getByTestId } = render(<Sonde />);

    lecture.mockReturnValue('dark');
    // Pas d'appel à l'écouteur : le rendu précédent reste en place.
    expect(getByTestId('schema').props.children).toBe('light');
  });

  it('témoin : l’application s’abonne bien à l’apparence', () => {
    jest.spyOn(Appearance, 'getColorScheme').mockReturnValue('light');

    render(<Sonde />);

    expect(Appearance.addChangeListener).toHaveBeenCalled();
  });
});
