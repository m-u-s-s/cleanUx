import React, { useState } from 'react';
import { View, Text, Alert, StyleSheet, ActivityIndicator } from 'react-native';
import { Screen, Button } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';
import { useQuery } from '@tanstack/react-query';

type Props = NativeStackScreenProps<RootStackParamList, 'Tips'>;

interface TipSuggestion {
  label: string;
  percent: number;
  amount_cents: number;
  amount_formatted: string;
}

export function TipsScreen({ route, navigation }: Props) {
  const styles = stylesFor(useThemeColors());

  const { bookingId } = route.params;
  const [selected, setSelected] = useState<TipSuggestion | null>(null);
  const [sending, setSending] = useState(false);

  const { data: suggestions = [], isLoading } = useQuery<TipSuggestion[]>({
    queryKey: ['tips', 'suggestions', bookingId],
    queryFn: async () => {
      const res = await apiClient.get(`/client/bookings/${bookingId}/tip/suggestions`);
      return res.data.data ?? [];
    },
  });

  const handleSend = async () => {
    if (selected === null) return;
    setSending(true);
    try {
      await apiClient.post(`/client/bookings/${bookingId}/tip`, {
        amount_cents: selected.amount_cents,
        preset_percent: selected.percent,
        preset_label: selected.label,
      });
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
      {isLoading ? (
        <ActivityIndicator size="large" color={colors.brand[500]} style={{ marginVertical: spacing.xl }} />
      ) : suggestions.length === 0 ? (
        <Text style={styles.noSuggestions}>Aucune suggestion disponible pour ce service.</Text>
      ) : (
        <View style={styles.presets}>
          {suggestions.map(s => (
            <Button
              key={s.percent}
              label={`${s.label}\n${s.amount_formatted}`}
              variant={selected?.percent === s.percent ? 'primary' : 'secondary'}
              onPress={() => setSelected(s)}
            />
          ))}
        </View>
      )}
      <Button
        label="Envoyer"
        onPress={handleSend}
        fullWidth
        size="lg"
        disabled={selected === null || sending}
        loading={sending}
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginTop: spacing.md,
    marginBottom: spacing.xs,
  },
  subtitle: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    marginBottom: spacing.lg,
  },
  presets: {
    flexDirection: 'row',
    gap: spacing.sm,
    justifyContent: 'center',
    marginBottom: spacing.xl,
  },
  noSuggestions: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    textAlign: 'center',
    marginVertical: spacing.xl,
  },
});
