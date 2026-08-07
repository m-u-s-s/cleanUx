/**
 * LE CHOIX D'ESPACE EST UNE SOURCE DE VÉRITÉ UNIQUE, PAS UN ÉTAT PAR APPELANT.
 *
 * POURQUOI CE FICHIER EXISTE. `useSpacePreference` était un hook à `useState` local. Deux
 * appelants, deux états indépendants :
 *
 *   - `RootNavigator` en détenait un — celui qui décide de l'espace rendu ;
 *   - `ProfileScreen`, `AdminProfileScreen` et `CompanyProfileScreen` en détenaient d'autres.
 *
 * Presser « Changer d'espace » ou « Aller à l'espace terrain » mettait à jour l'état DE L'ÉCRAN et
 * écrivait dans AsyncStorage. L'instance du navigateur, dont le `useEffect` a des dépendances `[]`,
 * avait lu la clé une fois au montage et ne la relisait jamais : rien ne se passait à l'écran
 * jusqu'au prochain lancement.
 *
 * Le SÉLECTEUR fonctionnait, et c'est ce qui a masqué le défaut : `RootNavigator` lui passe son
 * PROPRE `choose` en prop, donc la même instance.
 *
 * CE TEST NE BOUCHONNE PAS LE HOOK. Les tests de navigation le mockent — légitimement, ils mesurent
 * l'aiguillage — mais un mock qui enregistre `choose('provider')` prouve seulement qu'on a appelé
 * une fonction, jamais que quelque chose en découle. C'est exactement ce qui a laissé passer ce
 * défaut-ci, y compris dans les tests écrits le jour même pour garder cette sortie.
 */
import React from 'react';
import { Pressable, Text } from 'react-native';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

import { useSpacePreference } from '@/admin/useSpacePreference';

/** Le rôle de `RootNavigator` : il LIT le choix, et c'est lui qui doit réagir. */
function Aiguillage() {
  const { space, isLoading } = useSpacePreference();

  return <Text testID="aiguillage">{isLoading ? 'chargement' : (space ?? 'aucun')}</Text>;
}

/** Le rôle de `CompanyProfileScreen` : il ÉCRIT le choix, depuis un tout autre endroit de l'arbre. */
function ProfilSociete() {
  const { choose, clear } = useSpacePreference();

  return (
    <>
      <Pressable testID="aller-terrain" onPress={() => void choose('provider')}>
        <Text>Aller à l’espace terrain</Text>
      </Pressable>
      <Pressable testID="changer-espace" onPress={() => void clear()}>
        <Text>Changer d’espace</Text>
      </Pressable>
    </>
  );
}

describe('Préférence d’espace prestataire — source de vérité partagée', () => {
  beforeEach(async () => {
    await AsyncStorage.clear();
  });

  it('propage à l’aiguillage un choix fait depuis un autre composant', async () => {
    render(
      <>
        <Aiguillage />
        <ProfilSociete />
      </>,
    );

    await waitFor(() => expect(screen.getByTestId('aiguillage')).toHaveTextContent('aucun'));

    /*
     * LE GESTE QUI NE FAISAIT RIEN.
     *
     * C'est la sortie de l'espace société vers le terrain — ajoutée avec l'onglet Profil, et morte
     * à la naissance pour cette raison-là : elle appelait bien `choose('provider')`, et
     * `RootNavigator` ne l'apprenait jamais.
     */
    fireEvent.press(screen.getByTestId('aller-terrain'));
    await waitFor(() => expect(screen.getByTestId('aiguillage')).toHaveTextContent('provider'));

    fireEvent.press(screen.getByTestId('changer-espace'));
    await waitFor(() => expect(screen.getByTestId('aiguillage')).toHaveTextContent('aucun'));
  });
});
