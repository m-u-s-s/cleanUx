import React from 'react';
import { View } from 'react-native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Icon } from '@/ui';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { AdminHomeScreen } from './AdminHomeScreen';
import { AdminDirectoryScreen } from './AdminDirectoryScreen';
import { AdminProfileScreen } from './AdminProfileScreen';
import type { AdminTabParamList } from './types';

const Tab = createBottomTabNavigator<AdminTabParamList>();

/**
 * La console d'administration, montée à la place de l'espace prestataire.
 *
 * ELLE NE MONTE PAS `usePresenceHeartbeat`, contrairement au `TabNavigator` prestataire. Le
 * battement de présence est un signal de TERRAIN : l'émettre pour un administrateur assis devant
 * son téléphone le ferait apparaître disponible dans le dispatch, et fausserait les états de
 * présence sur lesquels s'appuie l'affectation des missions.
 *
 * Trois onglets seulement. La profondeur est dans l'annuaire, pas dans la barre : une barre à sept
 * entrées sur un téléphone se lit moins bien qu'une liste groupée et cherchable.
 */
export function AdminNavigator() {
  const theme = useThemeColors();
  return (
    <View testID="admin-navigator" style={{ flex: 1 }}>
      <Tab.Navigator
        screenOptions={{
          headerShown: false,
          tabBarActiveTintColor: colors.brand[500],
          tabBarInactiveTintColor: colors.surface[400],
          tabBarStyle: {
            backgroundColor: theme.bg,
            borderTopColor: theme.border,
          },
        }}
      >
        <Tab.Screen
          name="AdminHome"
          component={AdminHomeScreen}
          options={{
            title: 'Vue d’ensemble',
            tabBarLabel: 'Accueil',
            tabBarIcon: ({ color, size }) => <Icon name="speedometer-outline" size={size} color={color} />,
          }}
        />
        <Tab.Screen
          name="AdminDirectory"
          component={AdminDirectoryScreen}
          options={{
            title: 'Modules',
            tabBarLabel: 'Modules',
            tabBarIcon: ({ color, size }) => <Icon name="grid-outline" size={size} color={color} />,
          }}
        />
        <Tab.Screen
          name="AdminProfile"
          component={AdminProfileScreen}
          options={{
            title: 'Profil',
            tabBarLabel: 'Profil',
            tabBarIcon: ({ color, size }) => <Icon name="person-outline" size={size} color={color} />,
          }}
        />
      </Tab.Navigator>
    </View>
  );
}
