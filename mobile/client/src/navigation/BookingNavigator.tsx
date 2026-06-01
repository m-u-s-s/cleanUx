import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { BookingProvider } from '@/booking';
import { BookingStep1Service } from '@/screens/booking/BookingStep1Service';
import { BookingStep2Details } from '@/screens/booking/BookingStep2Details';
import { BookingStep3Coordinates } from '@/screens/booking/BookingStep3Coordinates';
import { BookingStep4Scheduling } from '@/screens/booking/BookingStep4Scheduling';
import { BookingStepProvider } from '@/screens/booking/BookingStepProvider';
import { BookingProviderSearchScreen } from '@/screens/booking/BookingProviderSearchScreen';
import { BookingCompanySearchScreen } from '@/screens/booking/BookingCompanySearchScreen';
import { BookingStep5Confirmation } from '@/screens/booking/BookingStep5Confirmation';
import { colors } from '@/theme';
import type { BookingStackParamList } from './types';

const Stack = createNativeStackNavigator<BookingStackParamList>();

export function BookingNavigator() {
  return (
    <BookingProvider>
      <Stack.Navigator
        screenOptions={{
          headerStyle: { backgroundColor: colors.surface[50] },
          headerTintColor: colors.surface[900],
        }}
      >
        <Stack.Screen
          name="BookingStep1"
          component={BookingStep1Service}
          options={{ title: 'Étape 1/6' }}
        />
        <Stack.Screen
          name="BookingStep2"
          component={BookingStep2Details}
          options={{ title: 'Étape 2/6' }}
        />
        <Stack.Screen
          name="BookingStep3"
          component={BookingStep3Coordinates}
          options={{ title: 'Étape 3/6' }}
        />
        <Stack.Screen
          name="BookingStep4"
          component={BookingStep4Scheduling}
          options={{ title: 'Étape 4/6' }}
        />
        <Stack.Screen
          name="BookingStepProvider"
          component={BookingStepProvider}
          options={{ title: 'Étape 5/6' }}
        />
        <Stack.Screen
          name="BookingProviderSearch"
          component={BookingProviderSearchScreen}
          options={{ title: 'Choisir un prestataire' }}
        />
        <Stack.Screen
          name="BookingCompanySearch"
          component={BookingCompanySearchScreen}
          options={{ title: 'Choisir une société' }}
        />
        <Stack.Screen
          name="BookingStep5"
          component={BookingStep5Confirmation}
          options={{ title: 'Confirmation' }}
        />
      </Stack.Navigator>
    </BookingProvider>
  );
}
