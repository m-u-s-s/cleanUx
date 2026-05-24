import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Button } from '@/ui';
import { useAuth } from '@/auth';
import { colors, spacing, typography } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

export function HomeScreen() {
  const { user } = useAuth();
  const navigation = useNavigation<Nav>();

  return (
    <Screen>
      <Text style={styles.greeting}>
        Bonjour{user?.name ? `, ${user.name}` : ''} 👋
      </Text>
      <Text style={styles.subtitle}>Que pouvons-nous faire pour vous ?</Text>
      <View style={styles.cta}>
        <Button
          label="Réserver un service"
          onPress={() => navigation.navigate('BookingWizard')}
          size="lg"
          fullWidth
        />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  greeting: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginTop: spacing.lg,
  },
  subtitle: {
    fontSize: typography.fontSize.base,
    color: colors.surface[500],
    marginTop: spacing.xs,
    marginBottom: spacing.xl,
  },
  cta: {
    marginTop: spacing.md,
  },
});
