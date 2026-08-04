/**
 * Les CHOIX et les FILTRES déclarés arrivent à l'écran.
 *
 * DEUX TROUS DE PARITÉ MESURÉS SUR LE REGISTRE.
 *
 * 1. Dix-sept champs de type « select » — quatorze dans des formulaires, trois dans des actions —
 *    retombaient sur une saisie TEXTE LIBRE. Le serveur valide avec `in:bronze,silver,gold`, et
 *    l'administrateur qui tape « Or » reçoit un 422 pour une liste qu'on ne lui a jamais montrée.
 *
 * 2. Soixante-dix filtres — soixante listes, dix booléens — n'étaient pas rendus du tout : l'écran
 *    ne cherchait que le filtre de recherche. Sur un domaine paginé de plusieurs milliers de
 *    lignes, « statut = litige ouvert » est la différence entre utilisable et décoratif.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

notifyManager.setScheduler((callback) => callback());

jest.mock('@/storage/secureStore');

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn(), goBack: jest.fn() }),
}));

import { apiClient } from '@/api';
import { ResourceListScreen } from '@/admin/console/ResourceListScreen';
import { FieldInput } from '@/admin/console/FieldInput';

const apiMock = new MockAdapter(apiClient);

const DESCRIPTEUR = {
  key: 'disputes',
  columns: [{ key: 'ref', label: 'Référence', type: 'text' }],
  filters: [
    { key: 'q', label: 'Rechercher', type: 'search', options: [] },
    {
      key: 'status',
      label: 'Statut',
      type: 'select',
      options: [
        { value: 'open', label: 'Ouvert' },
        { value: 'closed', label: 'Clos' },
      ],
    },
    { key: 'escalated', label: 'Escaladés', type: 'bool', options: [] },
  ],
  sorts: ['id'],
  default_sort: 'id',
  actions: [],
  form: [],
  global_actions: [],
};

const PAGE = { ok: true, resource: DESCRIPTEUR, rows: [], next_cursor: null };

function afficher() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={client}>
      <ResourceListScreen route={{ params: { resource: 'disputes', title: 'Litiges' } }} />
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  apiMock.reset();
  apiMock.onGet(/\/admin\/console\/disputes/).reply(200, PAGE);
});

describe('champ à choix', () => {
  it('un select rend ses options, pas une saisie libre', () => {
    const champ = {
      key: 'tier',
      label: 'Palier',
      type: 'select' as const,
      required: true,
      options: [
        { value: 'bronze', label: 'Bronze' },
        { value: 'gold', label: 'Or' },
      ],
    };

    render(<FieldInput field={champ} value="bronze" onChange={jest.fn()} />);

    // La valeur choisie s'affiche par son LIBELLÉ : « bronze » est un code, pas une réponse.
    expect(screen.getByText('Bronze')).toBeTruthy();
  });

  it('choisir une option rend la VALEUR, pas le libellé', () => {
    const onChange = jest.fn();

    render(
      <FieldInput
        field={{
          key: 'tier',
          label: 'Palier',
          type: 'select' as const,
          required: true,
          options: [
            { value: 'bronze', label: 'Bronze' },
            { value: 'gold', label: 'Or' },
          ],
        }}
        value="bronze"
        onChange={onChange}
      />,
    );

    fireEvent.press(screen.getByLabelText('Palier'));
    fireEvent.press(screen.getByText('Or'));

    /*
     * Le serveur valide avec `in:bronze,gold`. Envoyer « Or » — ce que l'administrateur LIT —
     * échouerait en 422 sur une liste qu'on vient pourtant de lui proposer.
     */
    expect(onChange).toHaveBeenCalledWith('gold');
  });
});

describe('filtres de liste', () => {
  it('un filtre à choix est rendu et part au serveur', async () => {
    afficher();

    fireEvent.press(await screen.findByLabelText('Statut'));
    fireEvent.press(screen.getByText('Ouvert'));

    await waitFor(() => {
      // `at(-1)` peut rendre `undefined` sur un historique vide : le dire plutôt que de
      // laisser l'erreur de type se poser sur la ligne suivante.
      const requete = apiMock.history.get.at(-1) ?? { params: undefined };

      /*
       * `filters[status]` et non `status` : c'est le format que le contrôleur lit. L'attente
       * naïve passait à côté du contrat réel — le filtre partait bien, le test disait le
       * contraire.
       *
       * Le filtre part au SERVEUR : filtrer localement ne verrait que la page déjà chargée, et
       * rendrait un résultat faux sans le dire sur un domaine paginé.
       */
      expect(requete.params).toMatchObject({ 'filters[status]': 'open' });
    });
  });

  it('un filtre booléen est rendu et part au serveur', async () => {
    afficher();

    // Un interrupteur répond à `valueChange`, pas à un appui : `press` ne déclenche rien sur lui.
    fireEvent(await screen.findByLabelText('Escaladés'), 'valueChange', true);

    await waitFor(() => {
      // `at(-1)` peut rendre `undefined` sur un historique vide : le dire plutôt que de
      // laisser l'erreur de type se poser sur la ligne suivante.
      const requete = apiMock.history.get.at(-1) ?? { params: undefined };

      // Un booléen part en 1, pas en `true` : une chaîne « true » serait lue comme vraie
      // même décochée, la chaîne non vide l'étant toujours.
      expect(requete.params?.['filters[escalated]']).toBe(1);
    });
  });

  it('un filtre choisi peut être retiré', async () => {
    afficher();

    fireEvent.press(await screen.findByLabelText('Statut'));
    fireEvent.press(screen.getByText('Ouvert'));

    // Sans retour en arrière, un filtre posé par erreur oblige à quitter l'écran pour l'annuler.
    fireEvent.press(await screen.findByLabelText('Statut'));
    fireEvent.press(screen.getByText('Tous'));

    await waitFor(() => {
      // `at(-1)` peut rendre `undefined` sur un historique vide : le dire plutôt que de
      // laisser l'erreur de type se poser sur la ligne suivante.
      const requete = apiMock.history.get.at(-1) ?? { params: undefined };

      expect(requete.params?.['filters[status]']).toBeUndefined();
    });
  });
});
