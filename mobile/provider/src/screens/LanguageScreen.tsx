import React, { useState } from 'react';
import { View, Text, TouchableOpacity, Alert, StyleSheet } from 'react-native';
import { Screen, Button, Badge } from '@/ui';
import { apiClient } from '@/api';
import { useAuth } from '@/auth';
import { colors, spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

const LANGUAGES = [
  { code: 'fr', label: 'Français', flag: '🇫🇷' },
  { code: 'nl', label: 'Nederlands', flag: '🇳🇱' },
  { code: 'en', label: 'English', flag: '🇬🇧' },
];

export function LanguageScreen({ navigation }: any) {
  const styles = stylesFor(useThemeColors());

  const { user, setUser } = useAuth();
  const [selected, setSelected] = useState((user as any)?.locale ?? 'fr');
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    setSaving(true);
    try {
      const res = await apiClient.put('/provider/profile', { locale: selected });
      setUser(res.data.user ?? res.data);
      Alert.alert('Langue mise à jour', '', [{ text: 'OK', onPress: () => navigation.goBack() }]);
    } catch {
      Alert.alert('Erreur');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Screen>
      <Text style={styles.title}>Langue</Text>
      {LANGUAGES.map(lang => (
        <TouchableOpacity
          key={lang.code}
          style={[styles.row, selected === lang.code && styles.rowActive]}
          onPress={() => setSelected(lang.code)}
        >
          <Text style={styles.flag}>{lang.flag}</Text>
          <Text style={styles.label}>{lang.label}</Text>
          {selected === lang.code && <Badge label="✓" variant="success" />}
        </TouchableOpacity>
      ))}
      <Button
        label="Sauvegarder"
        onPress={handleSave}
        fullWidth
        loading={saving}
        disabled={selected === (user as any)?.locale}
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginBottom: spacing.lg,
    marginTop: spacing.md,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.md,
    borderRadius: radius.md,
    marginBottom: spacing.xs,
  },
  rowActive: { backgroundColor: colors.brand[50] },
  flag: { fontSize: 28 },
  label: { flex: 1, fontSize: typography.fontSize.base, color: t.text },
});
