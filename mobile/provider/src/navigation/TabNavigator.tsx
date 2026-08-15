import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { DashboardScreen } from '@/screens/DashboardScreen';
import { MissionsScreen } from '@/screens/MissionsScreen';
import { EarningsScreen } from '@/screens/EarningsScreen';
import { ProfileScreen } from '@/screens/ProfileScreen';
import { Icon } from '@/ui';
import { apparenceDeBarre } from '@/ui/glassBars';
import { usePresenceHeartbeat } from '@/presence';
import { OfferHost } from '@/offers';
import { TripTrackingHost } from '@/tracking';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { TabParamList } from './types';

const Tab = createBottomTabNavigator<TabParamList>();

export function TabNavigator() {
  const theme = useThemeColors();
  // Point de montage UNIQUE du battement de cœur de présence. TabNavigator est rendu une seule
  // fois par session authentifiée et n'est jamais démonté tant que le prestataire est connecté :
  // le battement ne dépend donc ni de l'onglet affiché, ni du dashboard, et ne part jamais
  // depuis l'écran de connexion (contrairement à un montage à la racine de l'app).
  usePresenceHeartbeat();

  return (
    <>
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.brand[500],
        tabBarInactiveTintColor: theme.textMuted,
        ...apparenceDeBarre(theme),
      }}
    >
      {/*
        LE LIBELLÉ EST FRANÇAIS, LE NOM DE ROUTE RESTE ANGLAIS.

        Sans `tabBarLabel`, la barre affiche le nom de la route : « Dashboard / Missions /
        Earnings / Profile » sous une application par ailleurs entièrement française. Le même
        défaut existait côté client et avait été corrigé là-bas seulement — celui-ci est resté un
        jour de plus, jusqu'à ce qu'on ouvre l'application.

        On ne renomme PAS les routes : elles sont typées dans `TabParamList` et citées par tous les
        `navigate(...)`. Le libellé est de l'affichage, le nom est une adresse.
      */}
      <Tab.Screen
        name="Dashboard"
        component={DashboardScreen}
        options={{
          tabBarLabel: 'Accueil',
          tabBarIcon: ({ color, size }) => <Icon name="grid-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="Missions"
        component={MissionsScreen}
        options={{
          tabBarLabel: 'Missions',
          tabBarIcon: ({ color, size }) => <Icon name="briefcase-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="Earnings"
        component={EarningsScreen}
        options={{
          tabBarLabel: 'Revenus',
          tabBarIcon: ({ color, size }) => <Icon name="wallet-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="Profile"
        component={ProfileScreen}
        options={{
          tabBarLabel: 'Profil',
          tabBarIcon: ({ color, size }) => <Icon name="person-outline" size={size} color={color} />,
        }}
      />
    </Tab.Navigator>
    {/*
      LA MODALE D'OFFRE, MONTEE ICI ET NULLE PART AILLEURS.

      Meme raison que le battement de presence juste au-dessus : cette pile est montee UNE FOIS par
      session terrain et n'est jamais demontee. La modale s'ouvre donc quel que soit l'onglet
      affiche — tableau de bord, missions, gains — au lieu de dependre d'un ecran que le prestataire
      aurait pu quitter. Une offre qui n'apparait que sur le tableau de bord est une offre qu'on
      rate en consultant ses gains.

      Elle ne vit PAS a la racine de l'application : un gerant de societe ou un administrateur ne
      doit pas voir de modale d'offre, et leurs piles n'incluent pas celle-ci.
    */}
    <OfferHost />
    {/*
      LE SUIVI EN DIRECT, POUR LA MEME RAISON QUE LES DEUX AU-DESSUS.

      Il vivait dans un ecran que le prestataire devait ouvrir et garder ouvert — en conduisant.
      Le parcours normal etant « En route » puis « Je suis arrive », la session de suivi naissait
      sans une seule position, et le client ne voyait jamais approcher son prestataire.

      Ici, le releve suit la MISSION et non l'ecran affiche. Il s'arrete de lui-meme des qu'aucune
      mission n'est en route.
    */}
    <TripTrackingHost />
    </>
  );
}
