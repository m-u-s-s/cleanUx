import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

export function ProfileScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Profile</Text>
      <View style={styles.actions}>
        <Button
          label="Mes moyens de paiement"
          onPress={() => navigation.navigate('SavedPaymentMethods')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Messagerie"
          onPress={() => navigation.navigate('ChatList')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Notifications"
          onPress={() => navigation.navigate('Notifications')}
          variant="secondary"
          fullWidth
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.surface[50],
    padding: spacing.lg,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
    marginBottom: spacing.xl,
  },
  actions: {
    width: '100%',
    gap: spacing.sm,
  },
});
