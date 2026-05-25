import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { HomeScreen } from '@/screens/HomeScreen';
import { BrowseProvidersScreen } from '@/screens/BrowseProvidersScreen';
import { BookingsListScreen } from '@/screens/BookingsListScreen';
import { ProfileScreen } from '@/screens/ProfileScreen';
import { Icon } from '@/ui';
import { colors } from '@/theme';
import { useNotifications } from '@/notifications';
import type { TabParamList } from './types';

const Tab = createBottomTabNavigator<TabParamList>();

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
  const unreadCount = useUnreadCount();

  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.brand[500],
        tabBarInactiveTintColor: colors.surface[400],
        tabBarStyle: { backgroundColor: colors.surface[50], borderTopColor: colors.surface[200] },
      }}
    >
      <Tab.Screen
        name="Home"
        component={HomeScreen}
        options={{ tabBarIcon: ({ color, size }) => <Icon name="home-outline" size={size} color={color} /> }}
      />
      <Tab.Screen
        name="Explore"
        component={BrowseProvidersScreen}
        options={{ tabBarIcon: ({ color, size }) => <Icon name="search-outline" size={size} color={color} /> }}
      />
      <Tab.Screen
        name="Bookings"
        component={BookingsListScreen}
        options={{ tabBarIcon: ({ color, size }) => <Icon name="calendar-outline" size={size} color={color} /> }}
      />
      <Tab.Screen
        name="Profile"
        component={ProfileScreen}
        options={{
          tabBarIcon: ({ color, size }) => <Icon name="person-outline" size={size} color={color} />,
          tabBarBadge: unreadCount,
          tabBarBadgeStyle: { backgroundColor: colors.danger[500], fontSize: 10 },
        }}
      />
    </Tab.Navigator>
  );
}
