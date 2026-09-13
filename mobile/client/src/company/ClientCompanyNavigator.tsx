import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { CompanyOverviewScreen } from '@/screens/company/CompanyOverviewScreen';
import { CompanySitesScreen } from '@/screens/company/CompanySitesScreen';
import { CompanyBookingsScreen } from '@/screens/company/CompanyBookingsScreen';
import { CompanyBillingScreen } from '@/screens/company/CompanyBillingScreen';
import { CompanyProfileScreen } from '@/screens/company/CompanyProfileScreen';
import { Icon, apparenceDEnTete } from '@/ui';
import { creerBarreOnglets } from '@/ui/BarreOnglets';
import type { ClientCompanyTabParamList } from '@/navigation/types';
import { traduireMaintenant } from '@/i18n';

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
/* UNE SEULE BARRE POUR TOUTE LA PLATEFORME. Ces espaces avaient leur propre habillage — une
   plaque de verre derriere la barre par defaut de React Navigation — et la difference se voyait
   des qu'on passait d'un espace a l'autre. C'est la meme barre « Remontee » que l'accueil. */
const barreOnglets = creerBarreOnglets();

export function ClientCompanyNavigator() {

  return (
    <Tab.Navigator
      tabBar={barreOnglets}
      screenOptions={{
        headerShown: true,
        ...apparenceDEnTete(),
      }}
    >
      <Tab.Screen
        name="CompanyOverviewTab"
        component={CompanyOverviewScreen}
        options={{
          title: traduireMaintenant('client_company_navigator.mon_entreprise'),
          tabBarLabel: traduireMaintenant('client_company_navigator.accueil'),
          tabBarIcon: ({ color, size }) => <Icon name="business-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="CompanySitesTab"
        component={CompanySitesScreen}
        options={{
          title: traduireMaintenant('client_company_navigator.mes_locaux'),
          tabBarLabel: traduireMaintenant('client_company_navigator.locaux'),
          tabBarIcon: ({ color, size }) => <Icon name="location-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="CompanyBookingsTab"
        component={CompanyBookingsScreen}
        options={{
          title: traduireMaintenant('client_company_navigator.reservations'),
          tabBarLabel: traduireMaintenant('client_company_navigator.reservations'),
          tabBarIcon: ({ color, size }) => <Icon name="calendar-outline" size={size} color={color} />,
        }}
      />
      <Tab.Screen
        name="CompanyBillingTab"
        component={CompanyBillingScreen}
        options={{
          title: traduireMaintenant('client_company_navigator.facturation'),
          tabBarLabel: traduireMaintenant('client_company_navigator.facturation_2'),
          tabBarIcon: ({ color, size }) => <Icon name="receipt-outline" size={size} color={color} />,
        }}
      />
      {/*
        LE PROFIL EST UN ONGLET, PARCE QUE LA BARRE EST LA SEULE SURFACE PERMANENTE DE CET ESPACE.
        Sans lui, quelqu'un entré ici ne pouvait ni revenir à son espace personnel ni se
        déconnecter : `RootNavigator` déclarait bien une route `Profile`, mais aucun
        `navigate('Profile')` n'existait dans l'application. Déclarer n'est pas rendre joignable.
      */}
      <Tab.Screen
        name="CompanyProfileTab"
        component={CompanyProfileScreen}
        options={{
          title: traduireMaintenant('client_company_navigator.mon_compte'),
          tabBarLabel: traduireMaintenant('client_company_navigator.profil'),
          tabBarIcon: ({ color, size }) => <Icon name="person-outline" size={size} color={color} />,
        }}
      />
    </Tab.Navigator>
  );
}
