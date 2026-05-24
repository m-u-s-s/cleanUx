import React, { useState } from 'react';
import { View, Text, Alert, StyleSheet } from 'react-native';
import { Screen, Button } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'Tips'>;

const PRESETS = [10, 15, 20];

export function TipsScreen({ route, navigation }: Props) {
  const { bookingId } = route.params;
  const [selected, setSelected] = useState<number | null>(null);
  const [sending, setSending] = useState(false);

  const handleSend = async () => {
    if (selected === null) return;
    setSending(true);
    try {
      await apiClient.post(`/client/bookings/${bookingId}/tip`, { percentage: selected });
      Alert.alert('Merci !', 'Votre pourboire a été envoyé.', [{ text: 'OK', onPress: () => navigation.goBack() }]);
    } catch (e: any) {
      Alert.alert('Erreur', e.message);
    } finally {
      setSending(false);
    }
  };

  return (
    <Screen>
      <Text style={styles.title}>Pourboire</Text>
      <Text style={styles.subtitle}>Merci de valoriser le travail du prestataire</Text>
      <View style={styles.presets}>
        {PRESETS.map(p => (
          <Button
            key={p}
            label={`${p}%`}
            variant={selected === p ? 'primary' : 'secondary'}
            onPress={() => setSelected(p)}
          />
        ))}
      </View>
      <Button
        label="Envoyer"
        onPress={handleSend}
        fullWidth
        size="lg"
        disabled={selected === null}
        loading={sending}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginTop: spacing.md,
    marginBottom: spacing.xs,
  },
  subtitle: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
    marginBottom: spacing.lg,
  },
  presets: {
    flexDirection: 'row',
    gap: spacing.sm,
    justifyContent: 'center',
    marginBottom: spacing.xl,
  },
});
