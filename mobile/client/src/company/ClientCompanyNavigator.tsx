import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { CompanyOverviewScreen } from '@/screens/company/CompanyOverviewScreen';
import { CompanySitesScreen } from '@/screens/company/CompanySitesScreen';
import { CompanyBookingsScreen } from '@/screens/company/CompanyBookingsScreen';
import { CompanyBillingScreen } from '@/screens/company/CompanyBillingScreen';
import { Icon } from '@/ui';
import { apparenceDeBarre } from '@/ui/glassBars';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ClientCompanyTabParamList } from '@/navigation/types';

const Tab = createBottomTabNavigator<ClientCompanyTabParamList>();

/**
 * L'ESPACE SOCIÉTÉ CLIENTE — quatre onglets, et le métier d'un responsable de sites.
 *
 * POURQUOI UN NAVIGATEUR À PART. Ces écrans pendaient d'un bouton dans le profil, entre les moyens
 * de paiement et la langue. Quelqu'un qui suit vingt immeubles ouvre l'application POUR eux.
 *
 * LES ONGLETS SONT CEUX DU QUOTIDIEN : où j'interviens (locaux), ce qui est prévu (réservations),
 * ce que ça coûte (facturation). `CompanyMembers` et `CompanyContracts` restent sur la pile —
 * la composition de l'équipe et les contrats-cadres se consultent, ils ne se surveillent pas tous
 * les matins, et une barre à six onglets devient illisible.
 */
export function ClientCompanyNavigator() {
  const theme = useThemeColors();

  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: true,
        tabBarActiveTintColor: colors.brand[500],
        tabBarInactiveTintColor: theme.textMuted,
        ...apparenceDeBarre(theme),
      }}
    >
      <Tab.Screen
        name="CompanyOverviewTab"
        component={CompanyOverviewScreen}
        options={{
          title: 'Mon entreprise',
          tabBarLabel: 'Accueil',
          tabBarIcon: ({ color, size }) => <Icon name="business-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="CompanySitesTab"
        component={CompanySitesScreen}
        options={{
          title: 'Mes locaux',
          tabBarLabel: 'Locaux',
          tabBarIcon: ({ color, size }) => <Icon name="location-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="CompanyBookingsTab"
        component={CompanyBookingsScreen}
        options={{
          title: 'Réservations',
          tabBarLabel: 'Réservations',
          tabBarIcon: ({ color, size }) => <Icon name="calendar-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="CompanyBillingTab"
        component={CompanyBillingScreen}
        options={{
          title: 'Facturation',
          tabBarLabel: 'Facturation',
          tabBarIcon: ({ color, size }) => <Icon name="receipt-outline" size={size} color={color} />,
        }}
      />
    </Tab.Navigator>
  );
}
