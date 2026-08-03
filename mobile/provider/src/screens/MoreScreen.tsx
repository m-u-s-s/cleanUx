import React from 'react';
import { View, Text, TouchableOpacity, ScrollView, StyleSheet, Alert } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Ionicons } from '@expo/vector-icons';
import { Avatar, Badge, Divider, Icon } from '@/ui';
import { useAuth } from '@/auth';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';

interface MenuItem {
  label: string;
  icon: keyof typeof Ionicons.glyphMap;
  screen?: keyof RootStackParamList;
  onPress?: () => void;
  badge?: string;
  badgeVariant?: 'success' | 'warning' | 'danger' | 'brand' | 'neutral';
}

export function MoreScreen() {
  const styles = stylesFor(useThemeColors());

  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { user, logout } = useAuth();

  const navigate = (screen: keyof RootStackParamList) =>
    navigation.navigate(screen as any);

  const menuSections: Array<{ title: string; items: MenuItem[] }> = [
    {
      title: 'Compte',
      items: [
        { label: 'Disponibilités', icon: 'calendar-outline', screen: 'Availability' },
        { label: 'Badges', icon: 'ribbon-outline', screen: 'Badges' },
        { label: 'Avis reçus', icon: 'star-outline', screen: 'ProviderRatings' },
      ],
    },
    {
      title: 'Finances',
      items: [
        // Same nesting trap as the dashboard quick action: the Earnings tab lives inside
        // MainTabs, so it needs the nested param or the tap does nothing.
        {
          label: 'Revenus détaillés',
          icon: 'wallet-outline',
          screen: 'MainTabs',
          onPress: () => navigation.navigate('MainTabs', { screen: 'Earnings' }),
        },
        { label: 'Stripe Connect', icon: 'card-outline', screen: 'StripeOnboarding' },
      ],
    },
    {
      title: 'Support',
      items: [
        { label: 'Messagerie', icon: 'chatbubbles-outline', screen: 'ProviderChatList' },
        { label: 'Litiges', icon: 'alert-circle-outline', screen: 'ProviderDisputes' },
        { label: 'Vérification KYC', icon: 'shield-checkmark-outline', screen: 'KYC' },
      ],
    },
    {
      title: 'Preferences',
      items: [
        { label: 'Langue', icon: 'language-outline', screen: 'Language' },
        { label: 'Apparence', icon: 'color-palette-outline', screen: 'Appearance' },
        { label: 'Notifications', icon: 'notifications-outline', screen: 'NotificationPreferences' },
      ],
    },
    {
      title: 'Legale',
      items: [
        { label: "Conditions d'utilisation", icon: 'document-text-outline', screen: 'Legal', onPress: () => navigation.navigate('Legal', { type: 'terms' }) },
        { label: 'Confidentialite', icon: 'lock-closed-outline', onPress: () => navigation.navigate('Legal', { type: 'privacy' }) },
      ],
    },
  ];

  const handleLogout = () => {
    Alert.alert('Deconnexion', 'Voulez-vous vous deconnecter ?', [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Deconnexion', style: 'destructive', onPress: logout },
    ]);
  };

  return (
    <SafeAreaView style={styles.container} testID="more-screen">
      <ScrollView contentContainerStyle={styles.scroll}>
        {/* Profile header */}
        <View style={styles.profileSection}>
          <Avatar name={user?.name ?? '?'} size={56} />
          <View style={styles.profileInfo}>
            <Text style={styles.profileName}>{user?.name ?? 'Prestataire'}</Text>
            <Text style={styles.profileEmail}>{user?.email}</Text>
          </View>
        </View>

        {/* Menu sections */}
        {menuSections.map((section) => (
          <View key={section.title} style={styles.section}>
            <Text style={styles.sectionTitle} accessibilityRole="header">
              {section.title}
            </Text>
            <View style={styles.card}>
              {section.items.map((item, idx) => (
                <React.Fragment key={item.label}>
                  <TouchableOpacity
                    style={styles.menuItem}
                    onPress={item.onPress ?? (() => item.screen && navigate(item.screen))}
                    accessibilityRole="button"
                    accessibilityLabel={item.label}
                  >
                    <Icon name={item.icon} size={20} color={colors.brand[500]} />
                    <Text style={styles.menuLabel}>{item.label}</Text>
                    {item.badge && (
                      <Badge label={item.badge} variant={item.badgeVariant ?? 'neutral'} />
                    )}
                    <Icon name="chevron-forward-outline" size={16} color={colors.surface[400]} />
                  </TouchableOpacity>
                  {idx < section.items.length - 1 && <Divider />}
                </React.Fragment>
              ))}
            </View>
          </View>
        ))}

        {/* Logout */}
        <View style={styles.section}>
          <View style={styles.card}>
            <TouchableOpacity
              style={styles.menuItem}
              onPress={handleLogout}
              accessibilityRole="button"
              accessibilityLabel="Se deconnecter"
            >
              <Icon name="log-out-outline" size={20} color={colors.danger[500]} />
              <Text style={[styles.menuLabel, styles.dangerLabel]}>Se deconnecter</Text>
            </TouchableOpacity>
          </View>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { flex: 1, backgroundColor: t.bg },
  scroll: { padding: spacing.md, paddingBottom: spacing['2xl'] },
  profileSection: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.md,
    ...shadows.xs,
  },
  profileInfo: { flex: 1 },
  profileName: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  profileEmail: { fontSize: typography.fontSize.sm, color: t.textSecondary, marginTop: 2 },
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
  },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.md,
  },
  menuLabel: {
    flex: 1,
    fontSize: typography.fontSize.base,
    color: t.text,
  },
  dangerLabel: { color: colors.danger[600] },
});
