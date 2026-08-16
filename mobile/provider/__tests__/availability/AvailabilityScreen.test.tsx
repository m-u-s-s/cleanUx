import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';

const mockCreer = jest.fn();
const mockModifier = jest.fn();
const mockSupprimer = jest.fn();
const mockFermer = jest.fn();
const mockRouvrir = jest.fn();

/*
 * DEUX CRÉNEAUX QUI ENCADRENT LA CONVENTION DE JOUR.
 *
 * `weekday = 0` est un DIMANCHE côté serveur (convention Carbon). L'écran précédent portait
 * `['Lun','Mar',…,'Dim']` et l'indexait directement : le dimanche s'affichait « Lun », et les sept
 * jours étaient décalés d'un cran. Un décalage invisible en lecture — sept étiquettes plausibles —
 * qui se découvre le jour où quelqu'un se déplace un lundi pour un créneau du dimanche.
 */
const mockDonnees = {
  slots: [
    { id: 1, weekday: 0, start_time: '10:00:00', end_time: '14:00:00', timezone: 'Europe/Brussels', is_active: true, valid_from: null, valid_until: null },
    { id: 2, weekday: 1, start_time: '08:00:00', end_time: '17:00:00', timezone: 'Europe/Brussels', is_active: true, valid_from: null, valid_until: null },
  ],
  exceptions: [
    { id: 9, date: '2026-08-20', exception_type: 'closed', start_time: null, end_time: null, reason: 'Congé' },
  ],
};

jest.mock('@/availability', () => ({
  ...jest.requireActual('@/availability/labels'),
  useAvailability: () => ({ data: mockDonnees, isLoading: false, isError: false, refetch: jest.fn() }),
  useCreateSlot: () => ({ mutate: mockCreer, isPending: false }),
  useUpdateSlot: () => ({ mutate: mockModifier, isPending: false }),
  useDeleteSlot: () => ({ mutate: mockSupprimer, isPending: false }),
  useCloseDay: () => ({ mutate: mockFermer, isPending: false }),
  useDeleteException: () => ({ mutate: mockRouvrir, isPending: false }),
}));

jest.mock('@/theme', () => ({
  ...jest.requireActual('@/theme'),
  useThemeColors: jest.requireActual('@/theme/useThemeColors').useThemeColors,
}));

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');

  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    Button: ({ label, onPress, testID }: any) => <Text testID={testID} onPress={onPress}>{label}</Text>,
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Skeleton: () => <View />,
    ErrorState: ({ message }: any) => <Text>{message}</Text>,
  };
});

import { AvailabilityScreen } from '../../src/screens/AvailabilityScreen';

describe('AvailabilityScreen', () => {
  beforeEach(() => {
    [mockCreer, mockModifier, mockSupprimer, mockFermer, mockRouvrir].forEach(m => m.mockClear());
  });

  it('affiche les créneaux au lieu de « aucune disponibilité »', () => {
    const { getByText, queryByText } = render(<AvailabilityScreen />);

    expect(getByText('10:00 — 14:00')).toBeTruthy();
    expect(getByText('08:00 — 17:00')).toBeTruthy();
    expect(queryByText(/Aucune disponibilité/)).toBeNull();
  });

  /** Le régression-test du décalage : weekday 0 est un dimanche, pas un lundi. */
  it('range chaque créneau sous le bon jour', () => {
    const { getByTestId } = render(<AvailabilityScreen />);

    expect(getByTestId('jour-0')).toHaveTextContent(/Dimanche/);
    expect(getByTestId('jour-0')).toHaveTextContent(/10:00 — 14:00/);

    expect(getByTestId('jour-1')).toHaveTextContent(/Lundi/);
    expect(getByTestId('jour-1')).toHaveTextContent(/08:00 — 17:00/);
  });

  it('marque « Fermé » les jours sans créneau', () => {
    const { getByTestId } = render(<AvailabilityScreen />);

    // Mardi (2) n'a aucun créneau dans le jeu de test.
    expect(getByTestId('jour-2')).toHaveTextContent(/Fermé/);
  });

  it('crée un créneau avec le jour de la carte où l’on a cliqué', async () => {
    const { getByTestId } = render(<AvailabilityScreen />);

    fireEvent.press(getByTestId('ajouter-3')); // mercredi
    fireEvent.press(getByTestId('debut-09:00'));
    fireEvent.press(getByTestId('fin-12:00'));
    fireEvent.press(getByTestId('enregistrer-creneau'));

    await waitFor(() => expect(mockCreer).toHaveBeenCalled());
    expect(mockCreer.mock.calls[0][0]).toEqual({
      weekday: 3,
      start_time: '09:00',
      end_time: '12:00',
    });
  });

  it('refuse une fin antérieure au début sans appeler l’API', async () => {
    const { getByTestId } = render(<AvailabilityScreen />);

    fireEvent.press(getByTestId('ajouter-4'));
    fireEvent.press(getByTestId('debut-14:00'));
    fireEvent.press(getByTestId('fin-09:00'));
    fireEvent.press(getByTestId('enregistrer-creneau'));

    await waitFor(() => expect(mockCreer).not.toHaveBeenCalled());
  });

  it('modifie un créneau existant sans en créer un second', async () => {
    const { getByTestId } = render(<AvailabilityScreen />);

    fireEvent.press(getByTestId('modifier-2'));
    fireEvent.press(getByTestId('fin-16:00'));
    fireEvent.press(getByTestId('enregistrer-creneau'));

    await waitFor(() => expect(mockModifier).toHaveBeenCalled());
    expect(mockModifier.mock.calls[0][0]).toMatchObject({ id: 2, end_time: '16:00' });
    expect(mockCreer).not.toHaveBeenCalled();
  });

  /**
   * FERMER UNE DATE POSE UNE EXCEPTION, IL NE SUPPRIME PAS LA SEMAINE.
   *
   * C'est le défaut de la page web : son bouton « Bloquer » supprime les créneaux du jour — donc
   * ferme TOUS les mardis à venir pour fermer un mardi. Ici la semaine type reste intacte.
   */
  it('ferme une date par une exception, sans toucher aux créneaux', async () => {
    const { getByTestId, getAllByTestId } = render(<AvailabilityScreen />);

    fireEvent.press(getByTestId('ouvrir-fermeture'));

    const dates = getAllByTestId(/^fermer-\d{4}-\d{2}-\d{2}$/);
    const premiere = dates[0];
    expect(premiere).toBeDefined();
    fireEvent.press(premiere!);

    await waitFor(() => expect(mockFermer).toHaveBeenCalled());
    expect(mockFermer.mock.calls[0][0]).toHaveProperty('date');
    expect(mockSupprimer).not.toHaveBeenCalled();
  });

  it('liste les jours fermés et permet de les rouvrir', async () => {
    const { getByText, getByTestId } = render(<AvailabilityScreen />);

    expect(getByText('Congé')).toBeTruthy();

    fireEvent.press(getByTestId('rouvrir-9'));

    await waitFor(() => expect(mockRouvrir).toHaveBeenCalledWith(9));
  });
});
