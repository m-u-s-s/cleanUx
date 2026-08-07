/**
 * LE CHOIX D'ESPACE EST UNE SOURCE DE VÉRITÉ UNIQUE, PAS UN ÉTAT PAR APPELANT.
 *
 * POURQUOI CE FICHIER EXISTE. `useClientSpacePreference` était un hook à `useState` local. Deux
 * appelants, deux états indépendants :
 *
 *   - `RootNavigator` en détenait un — celui qui décide de l'espace rendu ;
 *   - `ProfileScreen` en détenait un autre.
 *
 * Presser « Changer d'espace » depuis le profil mettait à jour l'état DU PROFIL et écrivait dans
 * AsyncStorage. L'instance du navigateur, dont le `useEffect` a des dépendances `[]`, avait lu la
 * clé une fois au montage et ne la relisait jamais : elle n'apprenait rien. À l'écran, rien ne se
 * passait — jusqu'au prochain lancement de l'application.
 *
 * Les SÉLECTEURS d'espace fonctionnaient, eux, et c'est ce qui a masqué le défaut : `RootNavigator`
 * leur passe son PROPRE `choose` en prop, donc la même instance.
 *
 * CE TEST NE BOUCHONNE PAS LE HOOK. Les tests de navigation le mockent — légitimement, ils mesurent
 * l'aiguillage — mais un mock qui enregistre `clear()` prouve seulement qu'on a appelé une
 * fonction, jamais que quelque chose en découle. C'est exactement ce qui a laissé passer ce
 * défaut-ci.
 */
import React from 'react';
import { Pressable, Text } from 'react-native';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

import { useClientSpacePreference } from '@/company/useClientSpacePreference';

/** Le rôle de `RootNavigator` : il LIT le choix, et c'est lui qui doit réagir. */
function Aiguillage() {
  const { space, isLoading } = useClientSpacePreference();

  return <Text testID="aiguillage">{isLoading ? 'chargement' : (space ?? 'aucun')}</Text>;
}

/** Le rôle de `ProfileScreen` : il ÉCRIT le choix, depuis un tout autre endroit de l'arbre. */
function Profil() {
  const { choose, clear } = useClientSpacePreference();

  return (
    <>
      <Pressable testID="aller-societe" onPress={() => void choose('clientCompany')}>
        <Text>Espace entreprise</Text>
      </Pressable>
      <Pressable testID="changer-espace" onPress={() => void clear()}>
        <Text>Changer d’espace</Text>
      </Pressable>
    </>
  );
}

describe('Préférence d’espace cliente — source de vérité partagée', () => {
  beforeEach(async () => {
    await AsyncStorage.clear();
  });

  it('propage à l’aiguillage un choix fait depuis un autre composant', async () => {
    render(
      <>
        <Aiguillage />
        <Profil />
      </>,
    );

    await waitFor(() => expect(screen.getByTestId('aiguillage')).toHaveTextContent('aucun'));

    fireEvent.press(screen.getByTestId('aller-societe'));
    await waitFor(() => expect(screen.getByTestId('aiguillage')).toHaveTextContent('clientCompany'));

    /*
     * LE GESTE EXACT QUI NE FAISAIT RIEN.
     *
     * Sans source de vérité partagée, l'aiguillage restait bloqué sur `clientCompany` : la
     * responsable de sites voyait son bouton s'enfoncer et l'écran ne pas bouger.
     */
    fireEvent.press(screen.getByTestId('changer-espace'));
    await waitFor(() => expect(screen.getByTestId('aiguillage')).toHaveTextContent('aucun'));
  });
});
