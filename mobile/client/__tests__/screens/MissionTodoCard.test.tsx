import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { MissionTodoCard } from '@/screens/components/MissionTodoCard';

/**
 * MA LISTE DE TÂCHES.
 *
 * Deux garanties, et ce sont celles qui empêchent les deux abus symétriques :
 *
 *   - ce que la liste ENGAGE est dit AVANT le champ de saisie, avec le temps qu'il reste ;
 *   - une fenêtre fermée montre son MOTIF au lieu de se taire, faute de quoi elle passe pour une
 *     panne et la personne recommence.
 */

let mockListe: unknown = null;
const mockAjouter = jest.fn();
const mockRetirer = jest.fn();

jest.mock('@/booking/onsite', () => ({
  useTodoList: () => ({ data: mockListe }),
  useAjouterTache: () => ({ mutate: mockAjouter, isPending: false }),
  useRetirerTache: () => ({ mutate: mockRetirer, isPending: false }),
  // La consigne d'acces vit dans la meme carte : sans ce bouchon, le composant tombe sur un
  // crochet absent et le test mesurerait une panne de montage au lieu de la liste.
  useConsigneDAcces: () => ({ mutate: jest.fn(), isPending: false }),
}));

jest.mock('@/ui', () => {
  const { Text, TouchableOpacity, TextInput: RNTextInput, View } = require('react-native');

  return {
    Button: ({ label, onPress, testID }: any) => (
      <TouchableOpacity onPress={onPress} testID={testID}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    TextInput: ({ value, onChangeText, testID }: any) => (
      <RNTextInput value={value} onChangeText={onChangeText} testID={testID} />
    ),
    Icon: () => <View />,
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

const OUVERTE = {
  engine: 'domicile',
  window: { open: true, closes_at: null, minutes_left: 27, reason: null },
  items: [],
  suggestions: ['Vérifier accès client'],
};

describe('Ma liste de tâches', () => {
  beforeEach(() => {
    mockListe = null;
    mockAjouter.mockClear();
    mockRetirer.mockClear();
  });

  /** Une course n'a rien à cocher. */
  it('ne s’affiche pas sur une mission de véhicule', () => {
    mockListe = { ...OUVERTE, engine: 'vehicule' };
    render(<MissionTodoCard bookingId={77} />);

    expect(screen.queryByTestId('ma-todo-list')).toBeNull();
  });

  it('dit ce que la liste engage, et le temps qu’il reste', () => {
    mockListe = OUVERTE;
    render(<MissionTodoCard bookingId={77} />);

    expect(screen.getByText(/ne pourra pas terminer/)).toBeTruthy();
    expect(screen.getByText(/encore 27 min/)).toBeTruthy();
  });

  it('ajoute une tâche saisie', () => {
    mockListe = OUVERTE;
    render(<MissionTodoCard bookingId={77} />);

    fireEvent.changeText(screen.getByTestId('todo-saisie'), 'Nettoyer la hotte');
    fireEvent.press(screen.getByTestId('todo-ajouter'));

    expect(mockAjouter).toHaveBeenCalledWith('Nettoyer la hotte', expect.anything());
  });

  it('ajoute une suggestion en un tap', () => {
    mockListe = OUVERTE;
    render(<MissionTodoCard bookingId={77} />);

    fireEvent.press(screen.getByTestId('suggestion-Vérifier accès client'));

    expect(mockAjouter).toHaveBeenCalledWith('Vérifier accès client');
  });

  it('retire une tâche que le serveur déclare retirable', () => {
    mockListe = {
      ...OUVERTE,
      items: [{ id: 5, label: 'À retirer', source: 'client', done: false, is_required: true, removable: true }],
    };
    render(<MissionTodoCard bookingId={77} />);

    fireEvent.press(screen.getByTestId('retirer-tache-5'));

    expect(mockRetirer).toHaveBeenCalledWith(5, expect.anything());
  });

  /** LE TÉMOIN INVERSE : ce que le serveur ne déclare pas retirable n'offre aucun bouton. */
  it('n’offre pas de retrait sur une tâche déjà faite', () => {
    mockListe = {
      ...OUVERTE,
      items: [{ id: 6, label: 'Faite', source: 'client', done: true, is_required: true, removable: false }],
    };
    render(<MissionTodoCard bookingId={77} />);

    expect(screen.queryByTestId('retirer-tache-6')).toBeNull();
  });

  it('montre le motif quand la fenêtre est fermée', () => {
    mockListe = {
      ...OUVERTE,
      window: { open: false, closes_at: null, minutes_left: 0, reason: 'La liste est figée depuis 10:30.' },
    };
    render(<MissionTodoCard bookingId={77} />);

    expect(screen.getByTestId('todo-figee')).toBeTruthy();
    expect(screen.queryByTestId('todo-saisie')).toBeNull();
  });
});
