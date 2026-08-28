import React, { useState } from 'react';
import { View, Text, Alert, StyleSheet } from 'react-native';
import { Screen, Button } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';
import { useTraduction } from '@/i18n';

type Props = NativeStackScreenProps<RootStackParamList, 'NPS'>;

export function NPSScreen({ navigation }: Props) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const [score, setScore] = useState<number | null>(null);
  const [sending, setSending] = useState(false);

  const handleSubmit = async () => {
    if (score === null) return;
    setSending(true);
    try {
      await apiClient.post('/client/nps', { score });
      Alert.alert(tr('n_p_s.merci'), tr('n_p_s.votre_feedback_compte'), [{ text: 'OK', onPress: () => navigation.goBack() }]);
    } catch {
      // silent — best-effort NPS
    } finally {
      setSending(false);
    }
  };

  return (
    <Screen>
      <Text style={styles.title}>{tr('n_p_s.recommanderiez_vous_brio')}</Text>
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
        <Text style={styles.labelText}>{tr('n_p_s.pas_du_tout')}</Text>
        <Text style={styles.labelText}>{tr('n_p_s.absolument')}</Text>
      </View>
      <Button
        label={tr('n_p_s.envoyer')}
        onPress={handleSubmit}
        fullWidth
        disabled={score === null}
        loading={sending}
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginTop: spacing.lg,
    marginBottom: spacing.xl,
    textAlign: 'center',
  },
  scores: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: spacing.sm },
  score: {
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: t.inputBg,
    textAlign: 'center',
    lineHeight: 30,
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
  },
  scoreActive: { backgroundColor: colors.brand[500], color: t.card },
  labels: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: spacing.xl },
  labelText: { fontSize: typography.fontSize.xs, color: t.textMuted },
});
