import React from 'react';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { ModulesScreen } from '@/modules';
import type { RootStackParamList } from '@/navigation/types';

/**
 * Le répertoire des modules, branché sur l'hôte WebView de cette application.
 *
 * L'écran lui-même est PARTAGÉ entre les deux applications : ce qui change ici est la façon
 * d'ouvrir un module, chaque application ayant son propre hôte embarqué. Le catalogue, lui, vient
 * du serveur, qui déduit le contexte du jeton.
 */
export function ModulesRoute() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  return (
    <ModulesScreen
      onOuvrir={(module) =>
        navigation.navigate('EmbeddedModule', { path: module.path, title: module.label })
      }
    />
  );
}
