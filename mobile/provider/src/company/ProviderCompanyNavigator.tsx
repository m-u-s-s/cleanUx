import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { CompanyOverviewScreen } from '@/screens/company/CompanyOverviewScreen';
import { CompanyDispatchScreen } from '@/screens/company/CompanyDispatchScreen';
import { CompanyFieldTeamsScreen } from '@/screens/company/CompanyFieldTeamsScreen';
import { CompanyTasksScreen } from '@/screens/company/CompanyTasksScreen';
import { CompanyChannelsScreen } from '@/screens/company/CompanyChannelsScreen';
import { CompanyProfileScreen } from '@/screens/company/CompanyProfileScreen';
import { Icon } from '@/ui';
import { useAuth, can } from '@/auth';
import { apparenceDeBarre } from '@/ui/glassBars';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ProviderCompanyTabParamList } from '@/navigation/types';

const Tab = createBottomTabNavigator<ProviderCompanyTabParamList>();

/**
 * L'ESPACE SOCIÉTÉ PRESTATAIRE — le métier d'un gérant, borné par ce que le serveur lui accorde.
 *
 * POURQUOI UN NAVIGATEUR À PART. Ces écrans pendaient d'une liste de boutons dans l'onglet Profil.
 * Un gérant ouvre son application POUR répartir les missions du jour : c'est son écran d'accueil,
 * pas un réglage à deux gestes de profondeur. La répartition est donc le premier onglet.
 *
 * `CompanyMembers` n'est PAS un onglet : la composition de l'équipe se consulte depuis les équipes
 * terrain, où elle a un sens (qui affecter), plutôt qu'en cinquième onglet d'une barre qui devient
 * illisible. Il reste atteignable sur la pile racine.
 *
 * CE NAVIGATEUR NE MONTE PAS `usePresenceHeartbeat()`, ET C'EST LE POINT.
 *
 * `TabNavigator` le monte — c'est son unique point de montage, délibérément, parce qu'un
 * prestataire de terrain connecté est un prestataire joignable. Un GÉRANT au bureau, lui, n'est pas
 * disponible pour intervenir : le battement le ferait apparaître en ligne dans le dispatch de sa
 * propre société, et le moteur d'affectation le proposerait pour des missions qu'il ne fera pas.
 * Même raison que dans `AdminNavigator`.
 *
 * LES ONGLETS SUIVENT LES CLÉS DU SERVEUR. `can_manage_company` ouvre cet espace, mais il ne dit
 * pas TOUT : un responsable qualité l'a sans avoir `missions.dispatch` ni `team.view`. Les six
 * onglets s'affichaient à tous ceux qui entraient ici, dont deux mènent à des API qui répondent 403
 * depuis le lot 1. Accueil, Tâches et Profil restent inconditionnels — l'accueil est l'écran de
 * cet espace, les tâches sont bornées dans la requête, et le profil est la seule porte de sortie.
 */
export function ProviderCompanyNavigator() {
  const theme = useThemeColors();
  const { user } = useAuth();

  const peutRepartir = can(user, 'missions.dispatch');
  const peutVoirLesEquipes = can(user, 'team.view');

  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: true,
        tabBarActiveTintColor: colors.brand[500],
        tabBarInactiveTintColor: theme.textMuted,
        ...apparenceDeBarre(theme),
      }}
    >
      {/*
        L'ACCUEIL PASSE DEVANT LA RÉPARTITION.
        Un gérant ouvre son application pour savoir où en est la journée — combien de missions,
        combien en retard, et surtout combien n'ont encore personne. Il descend ensuite dans la
        répartition pour agir. L'ordre inverse lui demandait de deviner l'état avant de le voir.
      */}
      <Tab.Screen
        name="CompanyOverviewTab"
        component={CompanyOverviewScreen}
        options={{
          title: 'Ma société',
          tabBarLabel: 'Accueil',
          tabBarIcon: ({ color, size }) => <Icon name="business-outline" size={size} color={color} />,
        }}
      />
      {peutRepartir && (
        <Tab.Screen
          name="CompanyDispatchTab"
          component={CompanyDispatchScreen}
          options={{
            title: 'Répartition',
            tabBarLabel: 'Répartition',
            tabBarIcon: ({ color, size }) => <Icon name="git-branch-outline" size={size} color={color} />,
          }}
        />
      )}
      {peutVoirLesEquipes && (
        <Tab.Screen
          name="CompanyFieldTeamsTab"
          component={CompanyFieldTeamsScreen}
          options={{
            title: 'Équipes terrain',
            tabBarLabel: 'Équipes',
            tabBarIcon: ({ color, size }) => <Icon name="people-outline" size={size} color={color} />,
          }}
        />
      )}
      <Tab.Screen
        name="CompanyTasksTab"
        component={CompanyTasksScreen}
        options={{
          title: 'Tâches',
          tabBarLabel: 'Tâches',
          tabBarIcon: ({ color, size }) => <Icon name="checkbox-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="CompanyChannelsTab"
        component={CompanyChannelsScreen}
        options={{
          title: 'Canaux',
          tabBarLabel: 'Canaux',
          tabBarIcon: ({ color, size }) => <Icon name="chatbubbles-outline" size={size} color={color} />,
        }}
      />
      {/*
        LE PROFIL EST UN ONGLET, PARCE QUE LA BARRE EST LA SEULE SURFACE PERMANENTE DE CET ESPACE.
        Sans lui, un gérant entré ici ne pouvait plus se déconnecter ni redescendre sur le terrain :
        `RootNavigator` déclarait bien une route `Profile`, mais aucun `navigate('Profile')`
        n'existait dans l'application. Déclarer n'est pas rendre joignable. Même rôle que l'onglet
        « Profil » de la console d'administration.
      */}
      <Tab.Screen
        name="CompanyProfileTab"
        component={CompanyProfileScreen}
        options={{
          title: 'Mon compte',
          tabBarLabel: 'Profil',
          tabBarIcon: ({ color, size }) => <Icon name="person-outline" size={size} color={color} />,
        }}
      />
    </Tab.Navigator>
  );
}
