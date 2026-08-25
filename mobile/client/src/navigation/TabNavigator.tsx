import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { HomeScreen } from '@/screens/HomeScreen';
import { BrowseProvidersScreen } from '@/screens/BrowseProvidersScreen';
import { BookingsListScreen } from '@/screens/BookingsListScreen';
import { ProfileScreen } from '@/screens/ProfileScreen';
import { Icon } from '@/ui';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { creerBarreOnglets } from '@/ui/BarreOnglets';
import { useNotifications } from '@/notifications';
import type { TabParamList } from './types';

const Tab = createBottomTabNavigator<TabParamList>();

/* La barre est construite UNE fois : passee en ligne a `tabBar`, React Navigation
   la verrait comme un composant neuf a chaque rendu et la demonterait. `Home` prend
   la place centrale — c'est l'accueil. */
const barreOnglets = creerBarreOnglets({ routeCentrale: 'Home' });

function useUnreadCount(): number | undefined {
  try {
    // eslint-disable-next-line react-hooks/rules-of-hooks
    const { data } = useNotifications();
    const count = data?.filter(n => !n.read_at).length ?? 0;
    return count > 0 ? count : undefined;
  } catch {
    return undefined;
  }
}

export function TabNavigator() {
  const theme = useThemeColors();
  const unreadCount = useUnreadCount();

  return (
    <Tab.Navigator
      tabBar={barreOnglets}
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.brand[500],
        tabBarInactiveTintColor: theme.textMuted,
      }}
    >
      {/*
        LE LIBELLÉ EST FRANÇAIS, LE NOM DE ROUTE RESTE ANGLAIS.

        Sans `tabBarLabel`, la barre affiche le nom de la route : « Home / Explore / Bookings /
        Profile » sous une app entièrement française — le seul endroit de l'app cliente à ne pas
        l'être. Vu à l'écran, sous « Bonjour, Client » et « Réserver un service ».

        On ne renomme PAS les routes : elles sont typées dans `TabParamList` et citées par tous les
        `navigate(...)` de l'app. Le libellé est de l'affichage, le nom est une adresse.
      */}
      <Tab.Screen
        name="Home"
        component={HomeScreen}
        options={{
          tabBarLabel: 'Accueil',
          tabBarIcon: ({ color, size }) => <Icon name="home-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="Explore"
        component={BrowseProvidersScreen}
        options={{
          tabBarLabel: 'Explorer',
          tabBarIcon: ({ color, size }) => <Icon name="search-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="Bookings"
        component={BookingsListScreen}
        options={{
          tabBarLabel: 'Réservations',
          tabBarIcon: ({ color, size }) => <Icon name="calendar-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="Profile"
        component={ProfileScreen}
        options={{
          tabBarLabel: 'Profil',
          tabBarIcon: ({ color, size }) => <Icon name="person-outline" size={size} color={color} />,
          tabBarBadge: unreadCount,
          tabBarBadgeStyle: { backgroundColor: colors.danger[500], fontSize: 10 },
        }}
      />
    </Tab.Navigator>
  );
}
