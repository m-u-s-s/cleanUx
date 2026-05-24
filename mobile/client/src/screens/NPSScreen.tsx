import React, { useState } from 'react';
import { View, Text, Alert, StyleSheet } from 'react-native';
import { Screen, Button } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'NPS'>;

export function NPSScreen({ navigation }: Props) {
  const [score, setScore] = useState<number | null>(null);
  const [sending, setSending] = useState(false);

  const handleSubmit = async () => {
    if (score === null) return;
    setSending(true);
    try {
      await apiClient.post('/client/nps', { score });
      Alert.alert('Merci !', 'Votre feedback compte.', [{ text: 'OK', onPress: () => navigation.goBack() }]);
    } catch {
      // silent — best-effort NPS
    } finally {
      setSending(false);
    }
  };

  return (
    <Screen>
      <Text style={styles.title}>Recommanderiez-vous CleanUx ?</Text>
      <View style={styles.scores}>
        {[...Array(11)].map((_, i) => (
          <Text
            key={i}
            style={[styles.score, score === i && styles.scoreActive]}
            onPress={() => setScore(i)}
          >
            {i}
          </Text>
        ))}
      </View>
      <View style={styles.labels}>
        <Text style={styles.labelText}>Pas du tout</Text>
        <Text style={styles.labelText}>Absolument</Text>
      </View>
      <Button
        label="Envoyer"
        onPress={handleSubmit}
        fullWidth
        disabled={score === null}
        loading={sending}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginTop: spacing.lg,
    marginBottom: spacing.xl,
    textAlign: 'center',
  },
  scores: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: spacing.sm },
  score: {
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: colors.surface[100],
    textAlign: 'center',
    lineHeight: 30,
    fontSize: typography.fontSize.sm,
    color: colors.surface[600],
  },
  scoreActive: { backgroundColor: colors.brand[500], color: '#fff' },
  labels: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: spacing.xl },
  labelText: { fontSize: typography.fontSize.xs, color: colors.surface[400] },
});
