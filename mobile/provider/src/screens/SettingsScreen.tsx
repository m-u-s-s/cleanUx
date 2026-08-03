import React, { useState } from 'react';
import { View, Text, TouchableOpacity, Switch, StyleSheet, Alert, ScrollView } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, Divider, Badge } from '@/ui';
import { useAuth } from '@/auth';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';

const LANGUAGES = [
  { code: 'fr', label: 'Francais' },
  { code: 'nl', label: 'Nederlands' },
  { code: 'en', label: 'English' },
];

const APP_VERSION = '1.0.0';

export function SettingsScreen() {
  const styles = stylesFor(useThemeColors());

  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { user, setUser, logout } = useAuth();

  const [selectedLocale, setSelectedLocale] = useState<string>(
    (user as any)?.locale ?? 'fr',
  );
  const [darkMode, setDarkMode] = useState(false);
  const [pushEnabled, setPushEnabled] = useState(true);
  const [savingLocale, setSavingLocale] = useState(false);

  const handleSaveLocale = async () => {
    setSavingLocale(true);
    try {
      const res = await apiClient.put('/provider/profile', { locale: selectedLocale });
      setUser(res.data.user ?? res.data);
      Alert.alert('Langue mise a jour');
    } catch {
      Alert.alert('Erreur', 'Impossible de sauvegarder la langue.');
    } finally {
      setSavingLocale(false);
    }
  };

  const handleLogout = () => {
    Alert.alert('Deconnexion', 'Voulez-vous vous deconnecter ?', [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Deconnexion', style: 'destructive', onPress: logout },
    ]);
  };

  return (
    <SafeAreaView style={styles.container} testID="settings-screen">
      <ScrollView contentContainerStyle={styles.scroll}>
        <Text style={styles.pageTitle} accessibilityRole="header">
          Parametres
        </Text>

        {/* Language */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Langue</Text>
          <View style={styles.card}>
            {LANGUAGES.map((lang, idx) => (
              <React.Fragment key={lang.code}>
                <TouchableOpacity
                  style={styles.row}
                  onPress={() => setSelectedLocale(lang.code)}
                  accessibilityRole="radio"
                  accessibilityState={{ checked: selectedLocale === lang.code }}
                  accessibilityLabel={lang.label}
                >
                  <Text style={styles.rowLabel}>{lang.label}</Text>
                  {selectedLocale === lang.code && (
                    <Badge label="Actif" variant="success" />
                  )}
                </TouchableOpacity>
                {idx < LANGUAGES.length - 1 && <Divider />}
              </React.Fragment>
            ))}
          </View>
          {selectedLocale !== ((user as any)?.locale ?? 'fr') && (
            <Button
              label="Sauvegarder la langue"
              onPress={handleSaveLocale}
              fullWidth
              size="sm"
              loading={savingLocale}
            />
          )}
        </View>

        {/* Notifications */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Notifications</Text>
          <View style={styles.card}>
            <View style={styles.row}>
              <View style={styles.rowTextBlock}>
                <Text style={styles.rowLabel}>Notifications push</Text>
                <Text style={styles.rowHint}>Nouvelles missions, messages, mises a jour</Text>
              </View>
              <Switch
                value={pushEnabled}
                onValueChange={setPushEnabled}
                trackColor={{ true: colors.brand[500] }}
                accessibilityLabel="Activer les notifications push"
              />
            </View>
            <Divider />
            <TouchableOpacity
              style={styles.row}
              onPress={() => navigation.navigate('NotificationPreferences')}
              accessibilityRole="button"
              accessibilityLabel="Gerer les preferences de notifications"
            >
              <Text style={styles.rowLabel}>Preferences avancees</Text>
              <Text style={styles.rowChevron}>›</Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* Appearance */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Apparence</Text>
          <View style={styles.card}>
            <View style={styles.row}>
              <Text style={styles.rowLabel}>Mode sombre</Text>
              <Switch
                value={darkMode}
                onValueChange={setDarkMode}
                trackColor={{ true: colors.brand[500] }}
                accessibilityLabel="Activer le mode sombre"
              />
            </View>
            <Divider />
            <TouchableOpacity
              style={styles.row}
              onPress={() => navigation.navigate('Appearance')}
              accessibilityRole="button"
              accessibilityLabel="Options d'apparence avancees"
            >
              <Text style={styles.rowLabel}>Options avancees</Text>
              <Text style={styles.rowChevron}>›</Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* Legal */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Legal</Text>
          <View style={styles.card}>
            <TouchableOpacity
              style={styles.row}
              onPress={() => navigation.navigate('Legal', { type: 'terms' })}
              accessibilityRole="button"
            >
              <Text style={styles.rowLabel}>Conditions d'utilisation</Text>
              <Text style={styles.rowChevron}>›</Text>
            </TouchableOpacity>
            <Divider />
            <TouchableOpacity
              style={styles.row}
              onPress={() => navigation.navigate('Legal', { type: 'privacy' })}
              accessibilityRole="button"
            >
              <Text style={styles.rowLabel}>Politique de confidentialite</Text>
              <Text style={styles.rowChevron}>›</Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* App info */}
        <View style={styles.section}>
          <View style={styles.card}>
            <View style={styles.row}>
              <Text style={styles.rowLabel}>Version de l'application</Text>
              <Text style={styles.rowValue}>{APP_VERSION}</Text>
            </View>
          </View>
        </View>

        {/* Logout */}
        <Button
          label="Se deconnecter"
          onPress={handleLogout}
          variant="danger"
          fullWidth
        />
      </ScrollView>
    </SafeAreaView>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { flex: 1, backgroundColor: t.page },
  scroll: { padding: spacing.md, paddingBottom: spacing['2xl'] },
  pageTitle: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginBottom: spacing.lg,
  },
  section: { marginBottom: spacing.md },
  sectionTitle: {
    fontSize: typography.fontSize.xs,
    fontWeight: typography.fontWeight.semibold,
    color: t.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginBottom: spacing.xs,
    paddingHorizontal: spacing.xs,
  },
  card: {
    backgroundColor: t.card,
    borderRadius: radius.md,
    ...shadows.xs,
    overflow: 'hidden',
    marginBottom: spacing.xs,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.md,
    gap: spacing.sm,
  },
  rowTextBlock: { flex: 1 },
  rowLabel: {
    flex: 1,
    fontSize: typography.fontSize.base,
    color: t.text,
  },
  rowHint: { fontSize: typography.fontSize.xs, color: t.textMuted, marginTop: 2 },
  rowValue: { fontSize: typography.fontSize.sm, color: t.textSecondary },
  rowChevron: { fontSize: 18, color: t.textMuted },
});
