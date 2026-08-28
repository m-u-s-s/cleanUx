import React, { useState } from 'react';
import { View, Text, TouchableOpacity, Alert, StyleSheet } from 'react-native';
import { Screen, Button, Badge } from '@/ui';
import { useAuth } from '@/auth';
import { choisirLaLangue, useTraduction, type Langue } from '@/i18n';
import { colors, spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

const LANGUAGES: Array<{ code: Langue; label: string; flag: string }> = [
  { code: 'fr', label: 'Français', flag: '🇫🇷' },
  { code: 'nl', label: 'Nederlands', flag: '🇳🇱' },
  { code: 'en', label: 'English', flag: '🇬🇧' },
];

export function LanguageScreen({ navigation }: any) {
  const styles = stylesFor(useThemeColors());
  const { t, langue } = useTraduction();

  const { user, setUser } = useAuth();
  const [selected, setSelected] = useState<Langue>(langue);
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    setSaving(true);
    try {
      // La langue s'applique AVANT le retour du serveur : c'est le choix de l'utilisateur,
      // pas une donnée à confirmer. L'écran est déjà traduit quand l'alerte s'affiche.
      await choisirLaLangue(selected, '/client/profile');
      setUser({ ...(user as any), locale: selected });
      Alert.alert(t('langue.enregistree'), '', [
        { text: t('commun.ok'), onPress: () => navigation.goBack() },
      ]);
    } catch {
      Alert.alert(t('commun.erreur'), t('langue.echec'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Screen>
      <Text style={styles.title}>{t('langue.titre')}</Text>
      {LANGUAGES.map(lang => (
        <TouchableOpacity
          key={lang.code}
          style={[styles.row, selected === lang.code && styles.rowActive]}
          onPress={() => setSelected(lang.code)}
          accessibilityRole="radio"
          accessibilityState={{ selected: selected === lang.code }}
          testID={`langue-${lang.code}`}
        >
          <Text style={styles.flag}>{lang.flag}</Text>
          <Text style={styles.label}>{lang.label}</Text>
          {selected === lang.code && <Badge label="✓" variant="success" />}
        </TouchableOpacity>
      ))}
      <Button
        label={t('langue.enregistrer')}
        onPress={handleSave}
        fullWidth
        loading={saving}
        disabled={selected === langue}
        testID="langue-enregistrer"
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
  rowActive: { backgroundColor: t.tint.brand },
  flag: { fontSize: 28 },
  label: { flex: 1, fontSize: typography.fontSize.base, color: t.text },
});
