import React, { useState } from 'react';
import { View, Text, Image, Alert, StyleSheet } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { Screen, Button } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

export function AiQuoteScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const [imageUri, setImageUri] = useState<string | null>(null);
  const [result, setResult] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const pickImage = async () => {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) { Alert.alert(tr('ai_quote.permission_requise')); return; }
    const res = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.8 });
    if (!res.canceled && res.assets[0]) setImageUri(res.assets[0].uri);
  };

  const handleEstimate = async () => {
    if (!imageUri) return;
    setLoading(true);
    try {
      const formData = new FormData();
      formData.append('photo', { uri: imageUri, type: 'image/jpeg', name: 'quote.jpg' } as any);
      const res = await apiClient.post('/client/ai-quote/photo', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      setResult(res.data.estimate ?? res.data.data?.estimate ?? JSON.stringify(res.data));
    } catch (e: any) {
      Alert.alert(tr('ai_quote.erreur'), e.message ?? 'Estimation impossible');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Screen scroll>
      <Text style={styles.title}>{tr('ai_quote.devis_ia')}</Text>
      <Text style={styles.subtitle}>
        {tr('ai_quote.prenez_une_photo_de_la')}
      </Text>
      {imageUri ? (
        <Image source={{ uri: imageUri }} style={styles.preview} />
      ) : (
        <View style={styles.placeholder}>
          <Text style={styles.placeholderText}>{tr('ai_quote.aucune_photo_selectionnee')}</Text>
        </View>
      )}
      <View style={styles.actions}>
        <Button label={tr('ai_quote.choisir_une_photo')} onPress={pickImage} variant="secondary" fullWidth />
        <Button label={tr('ai_quote.estimer')} onPress={handleEstimate} fullWidth disabled={!imageUri} loading={loading} />
      </View>
      {result && (
        <View style={styles.resultCard}>
          <Text style={styles.resultTitle}>{tr('ai_quote.estimation')}</Text>
          <Text style={styles.resultText}>{result}</Text>
        </View>
      )}
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
  preview: { width: '100%', height: 250, borderRadius: radius.md, marginBottom: spacing.md },
  placeholder: {
    width: '100%',
    height: 250,
    borderRadius: radius.md,
    backgroundColor: t.inputBg,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.md,
  },
  placeholderText: { color: t.textMuted },
  actions: { gap: spacing.sm, marginBottom: spacing.lg },
  resultCard: {
    backgroundColor: t.tint.success,
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.soft,
  },
  resultTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.success,
    marginBottom: spacing.xs,
  },
  resultText: { fontSize: typography.fontSize.base, color: t.text },
});
