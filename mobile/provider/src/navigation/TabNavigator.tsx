import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { DashboardScreen } from '@/screens/DashboardScreen';
import { MissionsScreen } from '@/screens/MissionsScreen';
import { EarningsScreen } from '@/screens/EarningsScreen';
import { ProfileScreen } from '@/screens/ProfileScreen';
import { Icon } from '@/ui';
import { usePresenceHeartbeat } from '@/presence';
import { colors } from '@/theme';
import type { TabParamList } from './types';

const Tab = createBottomTabNavigator<TabParamList>();

export function TabNavigator() {
  // Point de montage UNIQUE du battement de cœur de présence. TabNavigator est rendu une seule
  // fois par session authentifiée et n'est jamais démonté tant que le prestataire est connecté :
  // le battement ne dépend donc ni de l'onglet affiché, ni du dashboard, et ne part jamais
  // depuis l'écran de connexion (contrairement à un montage à la racine de l'app).
  usePresenceHeartbeat();

  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.brand[500],
        tabBarInactiveTintColor: colors.surface[400],
        tabBarStyle: {
          backgroundColor: colors.surface[50],
          borderTopColor: colors.surface[200],
        },
      }}
    >
      <Tab.Screen
        name="Dashboard"
        component={DashboardScreen}
        options={{ tabBarIcon: ({ color, size }) => <Icon name="grid-outline" size={size} color={color} /> }}
      />
      <Tab.Screen
        name="Missions"
        component={MissionsScreen}
        options={{ tabBarIcon: ({ color, size }) => <Icon name="briefcase-outline" size={size} color={color} /> }}
      />
      <Tab.Screen
        name="Earnings"
        component={EarningsScreen}
        options={{ tabBarIcon: ({ color, size }) => <Icon name="wallet-outline" size={size} color={color} /> }}
      />
      <Tab.Screen
        name="Profile"
        component={ProfileScreen}
        options={{ tabBarIcon: ({ color, size }) => <Icon name="person-outline" size={size} color={color} /> }}
      />
    </Tab.Navigator>
  );
}
