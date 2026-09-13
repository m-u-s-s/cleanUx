import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { HomeScreen } from '@/screens/HomeScreen';
import { RentalHubScreen } from '@/screens/RentalHubScreen';
import { BookingsListScreen } from '@/screens/BookingsListScreen';
import { ProfileScreen } from '@/screens/ProfileScreen';
import { ChatListScreen } from '@/screens/ChatListScreen';
import { Icon } from '@/ui';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { creerBarreOnglets } from '@/ui/BarreOnglets';
import { useNotifications } from '@/notifications';
import type { TabParamList } from './types';
import { traduireMaintenant } from '@/i18n';

const Tab = createBottomTabNavigator<TabParamList>();

/* La barre est construite UNE fois : passee en ligne a `tabBar`, React Navigation
   la verrait comme un composant neuf a chaque rendu et la demonterait. */
const barreOnglets = creerBarreOnglets();

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
      /*
       * L'ACCUEIL EST DÉCLARÉ TROISIÈME, IL RESTE L'ÉCRAN D'OUVERTURE.
       *
       * La barre « Remontée » n'a plus de place centrale : l'ordre de déclaration EST l'ordre
       * affiché, et l'accueil se met au milieu des cinq. Sans `initialRouteName`, React Navigation
       * ouvrirait alors sur « Explorer » — un déplacement de rang qui change l'écran de démarrage.
       */
      initialRouteName="Home"
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
      {/*
        « EXPLORER » A CEDE SA PLACE A LA LOCATION.

        La recherche de prestataires n'a pas disparu : elle passe sur la pile et garde une entree
        dans le profil. Retirer un onglet sans rouvrir de porte a l'ecran qu'il montait est le
        defaut que ce depot a paye sept fois.
      */}
      <Tab.Screen
        name="Location"
        component={RentalHubScreen}
        options={{
          tabBarLabel: traduireMaintenant('location.onglet'),
          tabBarIcon: ({ color, size }) => <Icon name="key-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="Bookings"
        component={BookingsListScreen}
        options={{
          tabBarLabel: traduireMaintenant('tab_navigator.reservations'),
          tabBarIcon: ({ color, size }) => <Icon name="calendar-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="Home"
        component={HomeScreen}
        options={{
          tabBarLabel: traduireMaintenant('tab_navigator.accueil'),
          tabBarIcon: ({ color, size }) => <Icon name="home-outline" size={size} color={color} />,
        }}
      />
      {/*
        LE CINQUIÈME ONGLET. La messagerie n'était atteignable que par une entrée du profil, à
        deux gestes d'un message reçu. Elle garde son entrée là-bas : la barre ne remplace pas le
        profil, elle raccourcit le chemin.
      */}
      <Tab.Screen
        name="Messages"
        component={ChatListScreen}
        options={{
          tabBarLabel: traduireMaintenant('nav.messagerie'),
          tabBarIcon: ({ color, size }) => <Icon name="chatbubble-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="Profile"
        component={ProfileScreen}
        options={{
          tabBarLabel: traduireMaintenant('tab_navigator.profil'),
          tabBarIcon: ({ color, size }) => <Icon name="person-outline" size={size} color={color} />,
          tabBarBadge: unreadCount,
          tabBarBadgeStyle: { backgroundColor: colors.danger[500], fontSize: 10 },
        }}
      />
    </Tab.Navigator>
  );
}
