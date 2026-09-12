import React from 'react';
import { View } from 'react-native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Icon } from '@/ui';
import { apparenceDeBarre } from '@/ui/glassBars';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { AdminHomeScreen } from './AdminHomeScreen';
import { AdminDirectoryScreen } from './AdminDirectoryScreen';
import { AdminProfileScreen } from './AdminProfileScreen';
import { CatalogCountriesScreen } from './catalogue/CatalogCountriesScreen';
import type { AdminTabParamList } from './types';
import { traduireMaintenant } from '@/i18n';

const Tab = createBottomTabNavigator<AdminTabParamList>();

/**
 * La console d'administration, montée à la place de l'espace prestataire.
 *
 * ELLE NE MONTE PAS `usePresenceHeartbeat`, contrairement au `TabNavigator` prestataire. Le
 * battement de présence est un signal de TERRAIN : l'émettre pour un administrateur assis devant
 * son téléphone le ferait apparaître disponible dans le dispatch, et fausserait les états de
 * présence sur lesquels s'appuie l'affectation des missions.
 *
 * QUATRE onglets. La profondeur reste dans l'annuaire — une barre à sept entrées sur un téléphone
 * se lit moins bien qu'une liste groupée et cherchable — mais le catalogue y gagne sa place :
 * c'est le seul domaine qu'on vient ouvrir ou fermer en déplacement, et le chercher dans une
 * liste de quatre-vingts modules à chaque fois n'a aucun sens.
 */
export function AdminNavigator() {
  const theme = useThemeColors();
  return (
    <View testID="admin-navigator" style={{ flex: 1 }}>
      <Tab.Navigator
        screenOptions={{
          headerShown: false,
          tabBarActiveTintColor: colors.brand[500],
          tabBarInactiveTintColor: theme.textMuted,
          ...apparenceDeBarre(theme),
        }}
      >
        <Tab.Screen
          name="AdminHome"
          component={AdminHomeScreen}
          options={{
            title: traduireMaintenant('admin_navigator.vue_d_ensemble'),
            tabBarLabel: traduireMaintenant('admin_navigator.accueil'),
            tabBarIcon: ({ color, size }) => <Icon name="speedometer-outline" size={size} color={color} />,
          }}
        />
        <Tab.Screen
          name="AdminDirectory"
          component={AdminDirectoryScreen}
          options={{
            title: traduireMaintenant('admin_navigator.modules'),
            tabBarLabel: traduireMaintenant('admin_navigator.modules_2'),
            tabBarIcon: ({ color, size }) => <Icon name="grid-outline" size={size} color={color} />,
          }}
        />
        <Tab.Screen
          name="AdminCatalog"
          component={CatalogCountriesScreen}
          options={{
            title: traduireMaintenant('admin_navigator.catalogue'),
            tabBarLabel: traduireMaintenant('admin_navigator.catalogue_2'),
            tabBarIcon: ({ color, size }) => <Icon name="sparkles-outline" size={size} color={color} />,
          }}
        />
        <Tab.Screen
          name="AdminProfile"
          component={AdminProfileScreen}
          options={{
            title: traduireMaintenant('admin_navigator.profil'),
            tabBarLabel: traduireMaintenant('admin_navigator.profil_2'),
            tabBarIcon: ({ color, size }) => <Icon name="person-outline" size={size} color={color} />,
          }}
        />
      </Tab.Navigator>
    </View>
  );
}
